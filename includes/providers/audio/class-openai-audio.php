<?php

/**
 * OpenAI audio (text-to-speech) provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Audio;

use KritiAI\Core\Provider;
use KritiAI\Core\Audio_Provider;

/**
 * Class OpenAI_Audio
 */
class OpenAI_Audio extends Provider implements Audio_Provider
{

	/**
	 * Synthesize speech via the audio/speech endpoint.
	 *
	 * @param string $text Text to speak.
	 * @param array  $params Parameters.
	 * @return array
	 */
	public function synthesize_audio($text, $params)
	{
		$settings = $this->settings();

		$body = array(
			'model'           => $this->get_model($params),
			'input'           => (string) $text,
			'voice'           => isset($params['voice']) ? (string) $params['voice'] : 'alloy',
			'speed'           => isset($params['speed']) ? (float) $params['speed'] : 1.0,
			'response_format' => isset($params['format']) ? (string) $params['format'] : 'mp3',
		);

		$url     = $this->get_base_url('https://api.openai.com') . '/v1/audio/speech';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->get_api_key(),
		);

		$response = $this->http_post_json($url, $body, $headers, (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenAI audio request failed.', 'kriti-ai')));
		}

		$mime = wp_remote_retrieve_header($response, 'content-type');
		$mime = $mime ? $mime : 'audio/mpeg';

		$ext = $this->mime_to_extension($mime);

		$file = $this->write_tmp_file(wp_remote_retrieve_body($response), $ext);

		if (is_wp_error($file)) {
			return $file;
		}

		return array(
			'tmp_path'  => $file,
			'mime'      => $mime,
			'extension' => $ext,
		);
	}

	/**
	 * Map a mime type to a file extension.
	 *
	 * @param string $mime Mime type.
	 * @return string
	 */
	private function mime_to_extension($mime)
	{
		$map = array(
			'audio/mpeg'  => 'mp3',
			'audio/mp3'   => 'mp3',
			'audio/ogg'   => 'ogg',
			'audio/wav'   => 'wav',
			'audio/x-wav' => 'wav',
			'audio/flac'  => 'flac',
			'audio/aac'   => 'aac',
			'audio/x-m4a' => 'm4a',
			'audio/mp4'   => 'm4a',
		);

		return isset($map[$mime]) ? $map[$mime] : 'mp3';
	}
}
