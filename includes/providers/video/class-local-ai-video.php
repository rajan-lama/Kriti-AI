<?php

/**
 * Local AI video provider.
 *
 * Generates videos through a self-hosted server that exposes the
 * OpenAI-compatible /v1/videos endpoints (create, poll, download content).
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Video;

/**
 * Class Local_AI_Video
 */
class Local_AI_Video extends OpenAI_Video
{

  /**
   * Start a video job on the local server.
   *
   * @param string $prompt Video description.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function start_video($prompt, $params)
  {
    $error = \KritiAI\Providers\Local_AI::check_config($this->config, $this->get_model($params));

    return $error ? $error : parent::start_video($prompt, $params);
  }

  /**
   * Poll a video job on the local server.
   *
   * @param string $external_id Server-side job id.
   * @param array  $params Original parameters.
   * @return array|\WP_Error
   */
  public function poll_video($external_id, $params)
  {
    $error = \KritiAI\Providers\Local_AI::check_config($this->config, $this->get_model($params));

    return $error ? $error : parent::poll_video($external_id, $params);
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
