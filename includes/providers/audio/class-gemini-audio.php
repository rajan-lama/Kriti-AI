<?php

/**
 * Google Gemini Text-to-Speech provider.
 *
 * Uses the Gemini generateContent API (gemini-2.5-flash-preview-tts and
 * compatible TTS models) with responseModalities=["AUDIO"]. The API
 * returns raw PCM audio (audio/L16) as base64 inlineData, which this
 * provider wraps into a valid WAV file for WordPress Media Library storage.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Audio;

use KritiAI\Core\Provider;
use KritiAI\Core\Audio_Provider;

/**
 * Class Gemini_Audio
 */
class Gemini_Audio extends Provider implements Audio_Provider
{

	/**
	 * Synthesize speech via the Gemini generateContent API.
	 *
	 * @param string $text   Text to speak.
	 * @param array  $params Parameters.
	 * @return array|\WP_Error
	 */
	public function synthesize_audio($text, $params)
	{
		$settings = $this->settings();

		$model = $this->get_model($params);
		if (empty($model)) {
			$model = 'gemini-2.5-flash-preview-tts';
		}
		$model = $this->normalize_tts_model($model);

		$raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
		$base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);
		$url          = $base_url . '/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($this->get_api_key());

		// Optional voice name for speech config (supported by newer TTS models).
		$voice_name = isset($params['voice']) && ! empty($params['voice']) ? (string) $params['voice'] : 'Kore';

		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array('text' => (string) $text),
					),
				),
			),
			'generationConfig' => array(
				'responseModalities' => array('AUDIO'),
				'speechConfig'       => array(
					'voiceConfig' => array(
						'prebuiltVoiceConfig' => array(
							'voiceName' => $voice_name,
						),
					),
				),
			),
		);

		$response = $this->http_post_json($url, $body, array(), (int) $settings['media_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Gemini TTS request failed.', 'kriti-ai')));
		}

		$data  = json_decode(wp_remote_retrieve_body($response), true);
		$parts = isset($data['candidates'][0]['content']['parts']) ? $data['candidates'][0]['content']['parts'] : array();

		$audio_b64 = '';
		$mime_type = '';
		foreach ($parts as $part) {
			if (! empty($part['inlineData']['data'])) {
				$audio_b64 = $part['inlineData']['data'];
				$mime_type = isset($part['inlineData']['mimeType']) ? (string) $part['inlineData']['mimeType'] : '';
				break;
			}
		}

		if (empty($audio_b64)) {
			return new \WP_Error('kriti_ai_empty_response', __('Gemini TTS returned no audio data.', 'kriti-ai'));
		}

		$pcm_binary = base64_decode($audio_b64); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding Gemini TTS audio payload.

		if (false === $pcm_binary) {
			return new \WP_Error('kriti_ai_decoding_error', __('Could not decode the audio data from Gemini.', 'kriti-ai'));
		}

		// Detect sample rate from mimeType (e.g. audio/L16;codec=pcm;rate=24000).
		$sample_rate = 24000;
		if (preg_match('/rate=(\d+)/i', $mime_type, $m)) {
			$sample_rate = (int) $m[1];
		}

		// Wrap raw PCM in a WAV container so WordPress and browsers can handle it.
		$wav_binary = $this->pcm_to_wav($pcm_binary, $sample_rate);

		$file = $this->write_tmp_file($wav_binary, 'wav');

		if (is_wp_error($file)) {
			return $file;
		}

		return array(
			'tmp_path'  => $file,
			'mime'      => 'audio/wav',
			'extension' => 'wav',
		);
	}

	/**
	 * Wrap raw 16-bit PCM data in a RIFF/WAV container.
	 *
	 * @param string $pcm         Raw signed 16-bit LE PCM audio.
	 * @param int    $sample_rate Sample rate in Hz.
	 * @param int    $channels    Number of audio channels (1 = mono).
	 * @param int    $bit_depth   Bits per sample.
	 * @return string WAV binary.
	 */
	private function pcm_to_wav($pcm, $sample_rate = 24000, $channels = 1, $bit_depth = 16)
	{
		$data_size   = strlen($pcm);
		$byte_rate   = $sample_rate * $channels * ($bit_depth / 8);
		$block_align = $channels * ($bit_depth / 8);
		$chunk_size  = 36 + $data_size;

		$header  = 'RIFF';
		$header .= pack('V', $chunk_size);    // Chunk size.
		$header .= 'WAVE';
		$header .= 'fmt ';
		$header .= pack('V', 16);              // Subchunk1 size (PCM).
		$header .= pack('v', 1);               // Audio format: PCM = 1.
		$header .= pack('v', $channels);       // Num channels.
		$header .= pack('V', $sample_rate);    // Sample rate.
		$header .= pack('V', $byte_rate);      // Byte rate.
		$header .= pack('v', $block_align);    // Block align.
		$header .= pack('v', $bit_depth);      // Bits per sample.
		$header .= 'data';
		$header .= pack('V', $data_size);      // Subchunk2 size.

		return $header . $pcm;
	}

	/**
	 * Normalize TTS model names.
	 *
	 * @param string $model Model slug.
	 * @return string
	 */
	private function normalize_tts_model($model)
	{
		$model = preg_replace('/^models\//', '', trim((string) $model));

		$map = array(
			'gemini-3.1-flash-tts'        => 'gemini-3.1-flash-tts-preview',
			'gemini-3.1-flash-live'       => 'gemini-2.5-flash-preview-tts',
			'en-US-Neural2-C'             => 'gemini-2.5-flash-preview-tts',
			'gemini-2.5-flash-preview-tts' => 'gemini-2.5-flash-preview-tts',
		);

		return isset($map[$model]) ? $map[$model] : $model;
	}
}
