<?php

/**
 * Google Veo video provider.
 *
 * Uses the Gemini predictLongRunning endpoint for asynchronous video
 * generation (veo-3.1-generate-preview, veo-3.1-lite-generate-preview,
 * veo-3.1-fast-generate-preview). After starting the job the worker
 * polls the returned operation name until the video is ready.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Video;

use KritiAI\Core\Provider;
use KritiAI\Core\Video_Provider;

/**
 * Class Gemini_Video
 */
class Gemini_Video extends Provider implements Video_Provider
{

	/**
	 * Start async video generation via predictLongRunning.
	 *
	 * @param string $prompt Video description.
	 * @param array  $params Parameters.
	 * @return array|\WP_Error
	 */
	public function start_video($prompt, $params)
	{
		$settings = $this->settings();
		$model    = $this->get_model($params);
		$model    = $model ? $model : 'veo-3.1-lite-generate-preview';
		$model    = $this->normalize_video_model($model);

		$raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
		$base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);
		$url          = $base_url
			. '/v1beta/models/' . rawurlencode($model) . ':predictLongRunning'
			. '?key=' . rawurlencode($this->get_api_key());

		$aspect_ratio    = isset($params['aspect_ratio']) ? (string) $params['aspect_ratio'] : '16:9';
		$duration_seconds = isset($params['duration']) ? (int) $params['duration'] : 8;

		// Veo supports 5–8 seconds. Clamp to valid range.
		$duration_seconds = max(4, min(8, $duration_seconds));

		$body = array(
			'instances' => array(
				array(
					'prompt' => (string) $prompt,
				),
			),
			'parameters' => array(
				'aspectRatio'       => $aspect_ratio,
				'durationSeconds'   => $duration_seconds,
				'sampleCount'       => 1,
			),
		);

		$response = $this->http_post_json($url, $body, array(), (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Gemini video request failed.', 'kriti-ai')));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		// The response contains a long-running operation name.
		if (empty($data['name'])) {
			return new \WP_Error('kriti_ai_missing_video_id', __('Gemini did not return a video operation name.', 'kriti-ai'));
		}

		return array(
			'status'      => 'pending',
			'external_id' => (string) $data['name'],
		);
	}

	/**
	 * Poll a running video generation via the operations endpoint.
	 *
	 * @param string $external_id Operation name (e.g. operations/...).
	 * @param array  $params      Original parameters.
	 * @return array|\WP_Error
	 */
	public function poll_video($external_id, $params)
	{
		$settings = $this->settings();

		$raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
		$base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);

		// external_id may already include the full path e.g. "operations/abc123"
		$op_path = ltrim((string) $external_id, '/');
		if (false === strpos($op_path, 'operations/')) {
			$op_path = 'operations/' . $op_path;
		}

		$url = $base_url . '/v1beta/' . $op_path . '?key=' . rawurlencode($this->get_api_key());

		$response = $this->http_get($url, array(), (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Gemini video polling failed.', 'kriti-ai')));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		// Not done yet.
		if (empty($data['done'])) {
			return array(
				'status'      => 'pending',
				'external_id' => $external_id,
			);
		}

		// Failed.
		if (! empty($data['error'])) {
			$msg = isset($data['error']['message']) ? $data['error']['message'] : __('Gemini video generation failed.', 'kriti-ai');
			return new \WP_Error('kriti_ai_video_failed', $msg);
		}

		// Successful — extract video URI from response.
		$video_uri = '';
		if (isset($data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'])) {
			$video_uri = $data['response']['generateVideoResponse']['generatedSamples'][0]['video']['uri'];
		} elseif (isset($data['response']['videos'][0]['uri'])) {
			$video_uri = $data['response']['videos'][0]['uri'];
		}

		if (empty($video_uri)) {
			return new \WP_Error('kriti_ai_missing_video_url', __('Gemini completed but returned no video URI.', 'kriti-ai'));
		}

		return $this->download_video($video_uri);
	}

	/**
	 * Download the completed video from its URI.
	 *
	 * @param string $uri Video URI (may be a full https URL or gs:// path).
	 * @return array|\WP_Error
	 */
	private function download_video($uri)
	{
		// If URI is already a plain https URL, download directly.
		if (0 === strpos($uri, 'https://') || 0 === strpos($uri, 'http://')) {
			$download_url = add_query_arg('key', rawurlencode($this->get_api_key()), $uri);
		} else {
			// Strip gs:// prefix and use media download endpoint.
			$resource     = ltrim(str_replace('gs://', '', $uri), '/');
			$raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
			$base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);
			$download_url = $base_url . '/v1beta/media/' . rawurlencode($resource) . '?key=' . rawurlencode($this->get_api_key());
		}

		$response = $this->http_get($download_url, array(), 300);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_video_download', $this->extract_error($response, __('Could not download the generated video.', 'kriti-ai')));
		}

		$file = $this->write_tmp_file(wp_remote_retrieve_body($response), 'mp4');

		if (is_wp_error($file)) {
			return $file;
		}

		return array(
			'status'    => 'completed',
			'tmp_path'  => $file,
			'mime'      => 'video/mp4',
			'extension' => 'mp4',
		);
	}

	/**
	 * Normalize legacy Veo model slugs to current API model names.
	 *
	 * @param string $model Model slug.
	 * @return string
	 */
	private function normalize_video_model($model)
	{
		$model = preg_replace('/^models\//', '', trim((string) $model));

		$map = array(
			'veo-2.0-generate-001'       => 'veo-3.1-lite-generate-preview',
			'veo-3.0'                    => 'veo-3.1-generate-preview',
			'gemini-omni-flash'          => 'veo-3.1-fast-generate-preview',
		);

		return isset($map[$model]) ? $map[$model] : $model;
	}
}
