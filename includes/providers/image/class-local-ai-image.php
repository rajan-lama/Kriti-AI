<?php

/**
 * Local AI image provider.
 *
 * Generates images through a self-hosted server that exposes the
 * OpenAI-compatible /v1/images/generations endpoint (e.g. LocalAI).
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Image;

/**
 * Class Local_AI_Image
 */
class Local_AI_Image extends OpenAI_Image
{

  /**
   * Generate an image on the local server.
   *
   * @param string $prompt Description of the image.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function generate_image($prompt, $params)
  {
    $error = \KritiAI\Providers\Local_AI::check_config($this->config, $this->get_model($params));

    return $error ? $error : parent::generate_image($prompt, $params);
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
