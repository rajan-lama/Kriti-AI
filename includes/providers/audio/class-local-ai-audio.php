<?php

/**
 * Local AI audio (text-to-speech) provider.
 *
 * Synthesizes speech through a self-hosted server that exposes the
 * OpenAI-compatible /v1/audio/speech endpoint (e.g. LocalAI).
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Audio;

/**
 * Class Local_AI_Audio
 */
class Local_AI_Audio extends OpenAI_Audio
{

  /**
   * Synthesize speech on the local server.
   *
   * @param string $text Text to speak.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function synthesize_audio($text, $params)
  {
    $error = \KritiAI\Providers\Local_AI::check_config($this->config, $this->get_model($params));

    return $error ? $error : parent::synthesize_audio($text, $params);
  }

  /**
   * Always use the configured local server, never api.openai.com.
   *
   * @param string $fallback_url Ignored.
   * @return string
   */
  protected function get_base_url($fallback_url)
  {
    return \KritiAI\Providers\Local_AI::base_url($this->config);
  }

  /**
   * Test the local server with GET /v1/models.
   *
   * @return true|\WP_Error
   */
  public function test_connection()
  {
    $error = \KritiAI\Providers\Local_AI::check_url($this->config);

    return $error ? $error : parent::test_connection();
  }
}
