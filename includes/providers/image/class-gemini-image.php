<?php

/**
 * Gemini image provider.
 *
 * Uses the Gemini generateContent API with image-capable models
 * (e.g. gemini-3.1-flash-image, gemini-2.5-flash-image) which return
 * generated images as base64 inlineData parts.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Image;

use KritiAI\Core\Provider;
use KritiAI\Core\Image_Provider;

/**
 * Class Gemini_Image
 */
class Gemini_Image extends Provider implements Image_Provider
{

	/**
	 * Generate an image using the Gemini generateContent API.
	 *
	 * Supported image models: gemini-3.1-flash-image, gemini-2.5-flash-image,
	 * gemini-3-pro-image, gemini-3.1-flash-lite-image.
	 *
	 * @param string $prompt Description of the image.
	 * @param array  $params Parameters.
	 * @return array|\WP_Error
	 */
	public function generate_image($prompt, $params)
	{
		$settings = $this->settings();
		$model    = $this->get_model($params);
		$model    = $model ? $model : 'gemini-3.1-flash-image';
		$model    = $this->normalize_image_model($model);

		$raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
		$base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);
		$url          = $base_url . '/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($this->get_api_key());

		// Build prompt: include aspect ratio hint if specified.
		$aspect_ratio = isset($params['aspect_ratio']) ? (string) $params['aspect_ratio'] : '1:1';
		$full_prompt  = (string) $prompt;
		if (! empty($aspect_ratio) && '1:1' !== $aspect_ratio) {
			$full_prompt .= ' Aspect ratio: ' . $aspect_ratio . '.';
		}

		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array('text' => $full_prompt),
					),
				),
			),
		);

		$response = $this->http_post_json($url, $body, array(), (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Gemini image request failed.', 'kriti-ai')));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		// Find the first inlineData image part in any candidate.
		$b64      = '';
		$mime     = 'image/png';
		$ext      = 'png';
		$parts    = isset($data['candidates'][0]['content']['parts']) ? $data['candidates'][0]['content']['parts'] : array();

		foreach ($parts as $part) {
			if (! empty($part['inlineData']['data'])) {
				$b64  = $part['inlineData']['data'];
				$mime = isset($part['inlineData']['mimeType']) ? $part['inlineData']['mimeType'] : 'image/png';
				$ext  = ('image/webp' === $mime) ? 'webp' : (('image/jpeg' === $mime) ? 'jpg' : 'png');
				break;
			}
		}

		if (empty($b64)) {
			return new \WP_Error('kriti_ai_empty_response', __('Gemini returned no image data. Try a more specific prompt.', 'kriti-ai'));
		}

		$binary = base64_decode($b64); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding Gemini API image payload.

		if (false === $binary) {
			return new \WP_Error('kriti_ai_decoding_error', __('Could not decode the image data from Gemini.', 'kriti-ai'));
		}

		$file = $this->write_tmp_file($binary, $ext);

		if (is_wp_error($file)) {
			return $file;
		}

		return array(
			'tmp_path'  => $file,
			'mime'      => $mime,
			'extension' => $ext,
			'model'     => $model,
		);
	}

	/**
	 * Normalize legacy or invalid Gemini image model slugs.
	 *
	 * @param string $model Model slug.
	 * @return string
	 */
	private function normalize_image_model($model)
	{
		$model = preg_replace('/^models\//', '', trim((string) $model));

		$map = array(
			'gemini-3.1-flash-image-preview' => 'gemini-3.1-flash-image',
			'gemini-3-pro-image-preview'     => 'gemini-3-pro-image',
			'imagen-3.0-generate-002'        => 'gemini-3.1-flash-image',
			'imagen-3.0-generate-001'        => 'gemini-3.1-flash-image',
		);

		return isset($map[$model]) ? $map[$model] : $model;
	}
}
