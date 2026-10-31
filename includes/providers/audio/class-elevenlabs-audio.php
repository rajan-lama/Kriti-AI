<?php

/**
 * ElevenLabs audio (text-to-speech) provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Audio;

use KritiAI\Core\Provider;
use KritiAI\Core\Audio_Provider;

/**
 * Class ElevenLabs_Audio
 */
class ElevenLabs_Audio extends Provider implements Audio_Provider
{

  /**
   * Default voice (Rachel) used when none is provided.
   *
   * @var string
   */
  const DEFAULT_VOICE = '21m00Tcm4TlvDq8ikWAM';

  /**
   * Synthesize speech via the text-to-speech endpoint.
   *
   * @param string $text Text to speak.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function synthesize_audio($text, $params)
  {
    $settings = $this->settings();
    $model    = $this->get_model($params);
    $model    = $model ? $model : 'eleven_multilingual_v2';

    // ElevenLabs needs a voice id; names like "alloy" belong to other providers.
    $voice = ! empty($params['voice']) && preg_match('/^[A-Za-z0-9]{20}$/', (string) $params['voice']) ? (string) $params['voice'] : self::DEFAULT_VOICE;
    $url   = $this->get_base_url('https://api.elevenlabs.io') . '/v1/text-to-speech/' . rawurlencode($voice) . '?output_format=mp3_44100_128';

    $body = array(
      'text'           => (string) $text,
      'model_id'       => $model,
      'voice_settings' => array(
        'stability'         => isset($params['stability']) ? (float) $params['stability'] : 0.5,
        'similarity_boost'  => isset($params['similarity_boost']) ? (float) $params['similarity_boost'] : 0.75,
        'style'             => isset($params['style']) && is_numeric($params['style']) ? (float) $params['style'] : 0.0,
        'use_speaker_boost' => true,
      ),
    );

    $headers = array(
      'xi-api-key' => $this->get_api_key(),
      'Accept'     => 'audio/mpeg',
    );

    $response = $this->http_post_json($url, $body, $headers, (int) $settings['media_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('ElevenLabs request failed.', 'kriti-ai')));
    }

    $audio = wp_remote_retrieve_body($response);

    if ('' === $audio) {
      return new \WP_Error('kriti_ai_empty_response', __('ElevenLabs returned no audio data.', 'kriti-ai'));
    }

    $file = $this->write_tmp_file($audio, 'mp3');

    if (is_wp_error($file)) {
      return $file;
    }

    return array(
      'tmp_path'  => $file,
      'mime'      => 'audio/mpeg',
      'extension' => 'mp3',
    );
  }

  /**
   * Request used by the connection test.
   *
   * @return array
   */
  protected function connection_request()
  {
    return array(
      'url'     => $this->get_base_url('https://api.elevenlabs.io') . '/v1/user',
      'headers' => array('xi-api-key' => $this->get_api_key()),
    );
  }
}
