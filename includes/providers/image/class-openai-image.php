<?php

/**
 * OpenAI image provider (DALL-E 2 / DALL-E 3).
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Image;

use KritiAI\Core\Provider;
use KritiAI\Core\Image_Provider;

/**
 * Class OpenAI_Image
 */
class OpenAI_Image extends Provider implements Image_Provider
{

	/**
	 * Generate an image using DALL-E.
	 *
	 * @param string $prompt Description of the image.
	 * @param array  $params Parameters.
	 * @return array|\WP_Error
	 */
	public function generate_image($prompt, $params)
	{
		$settings = $this->settings();
		$model    = $this->get_model($params);
		$model    = $model ? $model : 'dall-e-3';

		$size   = isset($params['resolution']) ? (string) $params['resolution'] : '1024x1024';
		$style  = isset($params['style']) ? (string) $params['style'] : 'vivid';
		$format = isset($params['response_format']) ? (string) $params['response_format'] : 'b64_json';

		$body = array(
			'model'           => $model,
			'prompt'          => (string) $prompt,
			'n'               => 1,
			'size'            => $size,
			'response_format' => $format,
		);

		if ('dall-e-3' === $model) {
			$body['style']   = $style;
			$body['quality'] = isset($params['quality']) ? (string) $params['quality'] : 'standard';
		}

		$url     = $this->get_base_url('https://api.openai.com') . '/v1/images/generations';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->get_api_key(),
		);

		$response = $this->http_post_json($url, $body, $headers, (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenAI image request failed.', 'kriti-ai')));
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (! empty($data['data'][0]['b64_json'])) {
			$binary = base64_decode($data['data'][0]['b64_json']); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding raw base64 image data from OpenAI.
		} elseif (! empty($data['data'][0]['url'])) {
			$download = $this->http_get($data['data'][0]['url'], array(), (int) $settings['media_timeout']);
			if (is_wp_error($download) || 200 !== wp_remote_retrieve_response_code($download)) {
				return new \WP_Error('kriti_ai_download_failed', __('Failed to download image from OpenAI.', 'kriti-ai'));
			}
			$binary = wp_remote_retrieve_body($download);
		} else {
			return new \WP_Error('kriti_ai_empty_response', __('OpenAI image generation returned no data.', 'kriti-ai'));
		}

		$file = $this->write_tmp_file($binary, 'png');

		if (is_wp_error($file)) {
			return $file;
		}

		return array(
			'tmp_path'  => $file,
			'mime'      => 'image/png',
			'extension' => 'png',
			'model'     => $model,
		);
	}
}
