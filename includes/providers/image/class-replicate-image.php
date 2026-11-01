<?php

/**
 * Replicate image provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Image;

use KritiAI\Core\Provider;
use KritiAI\Core\Image_Provider;

/**
 * Class Replicate_Image
 */
class Replicate_Image extends Provider implements Image_Provider
{

  /**
   * Generate an image via the Replicate predictions API.
   *
   * Accepts either an official model ("owner/name") or a pinned version
   * ("owner/name:version").
   *
   * @param string $prompt Description of the image.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function generate_image($prompt, $params)
  {
    $settings = $this->settings();
    $timeout  = (int) $settings['media_timeout'];
    $model    = $this->get_model($params);
    $model    = $model ? $model : 'black-forest-labs/flux-schnell';
    $base_url = $this->get_base_url('https://api.replicate.com');

    $input = array(
      'prompt'       => (string) $prompt,
      'aspect_ratio' => isset($params['aspect_ratio']) ? (string) $params['aspect_ratio'] : '1:1',
    );

    if (false !== strpos($model, ':')) {
      list(, $version) = explode(':', $model, 2);
      $url             = $base_url . '/v1/predictions';
      $body            = array(
        'version' => $version,
        'input'   => $input,
      );
    } else {
      $url  = $base_url . '/v1/models/' . implode('/', array_map('rawurlencode', explode('/', $model))) . '/predictions';
      $body = array('input' => $input);
    }

    $headers = array(
      'Authorization' => 'Bearer ' . $this->get_api_key(),
      'Prefer'        => 'wait',
    );

    $response = $this->http_post_json($url, $body, $headers, $timeout);

    if (is_wp_error($response) || ! in_array(wp_remote_retrieve_response_code($response), array(200, 201), true)) {
      return new \WP_Error('kriti_ai_provider_error', $this->replicate_error($response));
    }

    $data = $this->wait_for_prediction(json_decode(wp_remote_retrieve_body($response), true), $timeout);

    if (is_wp_error($data)) {
      return $data;
    }

    $output  = isset($data['output']) ? $data['output'] : array();
    $img_url = is_array($output) && ! empty($output[0]) ? $output[0] : (is_string($output) ? $output : '');

    if (! $img_url) {
      return new \WP_Error('kriti_ai_empty_response', __('Replicate returned no output image URL.', 'kriti-ai'));
    }

    $download = $this->http_get($img_url, array(), $timeout);

    if (is_wp_error($download) || 200 !== wp_remote_retrieve_response_code($download)) {
      return new \WP_Error('kriti_ai_download_failed', __('Could not download image from Replicate.', 'kriti-ai'));
    }

    $mime = strtolower(trim(explode(';', (string) wp_remote_retrieve_header($download, 'content-type'))[0]));
    $exts = array(
      'image/png'  => 'png',
      'image/jpeg' => 'jpg',
      'image/webp' => 'webp',
    );

    if (! isset($exts[$mime])) {
      $mime = 'image/webp';
    }

    $file = $this->write_tmp_file(wp_remote_retrieve_body($download), $exts[$mime]);

    if (is_wp_error($file)) {
      return $file;
    }

    return array(
      'tmp_path'  => $file,
      'mime'      => $mime,
      'extension' => $exts[$mime],
      'model'     => $model,
    );
  }

  /**
   * Poll a prediction until it finishes. "Prefer: wait" usually returns a
   * finished prediction, but slow models come back still processing.
   *
   * @param array $data Prediction payload.
   * @param int   $timeout Maximum seconds to wait.
   * @return array|\WP_Error
   */
  private function wait_for_prediction($data, $timeout)
  {
    $deadline = time() + $timeout;
    $headers  = array('Authorization' => 'Bearer ' . $this->get_api_key());

    while (is_array($data) && isset($data['status']) && in_array($data['status'], array('starting', 'processing'), true)) {
      if (empty($data['urls']['get']) || time() >= $deadline) {
        return new \WP_Error('kriti_ai_timeout', __('Replicate did not finish generating the image in time. Increase the media timeout and try again.', 'kriti-ai'));
      }

      sleep(2);

      $poll = $this->http_get($data['urls']['get'], $headers, 30);

      if (is_wp_error($poll) || 200 !== wp_remote_retrieve_response_code($poll)) {
        return new \WP_Error('kriti_ai_provider_error', $this->replicate_error($poll));
      }

      $data = json_decode(wp_remote_retrieve_body($poll), true);
    }

    if (! is_array($data) || ! isset($data['status'])) {
      return new \WP_Error('kriti_ai_provider_error', __('Replicate returned an invalid response.', 'kriti-ai'));
    }

    if ('succeeded' !== $data['status']) {
      $error = ! empty($data['error']) && is_string($data['error']) ? $data['error'] : __('Replicate image generation failed.', 'kriti-ai');
      return new \WP_Error('kriti_ai_provider_error', $error);
    }

    return $data;
  }

  /**
   * Replicate returns errors as {"title": "...", "detail": "..."}.
   *
   * @param array|\WP_Error $response HTTP response.
   * @return string
   */
  private function replicate_error($response)
  {
    if (! is_wp_error($response)) {
      $data = json_decode((string) wp_remote_retrieve_body($response), true);
      if (! empty($data['detail']) && is_string($data['detail'])) {
        return $data['detail'];
      }
    }

    return $this->extract_error($response, __('Replicate image request failed.', 'kriti-ai'));
  }

  /**
   * Request used by the connection test.
   *
   * @return array
   */
  protected function connection_request()
  {
    return array(
      'url'     => $this->get_base_url('https://api.replicate.com') . '/v1/account',
      'headers' => array('Authorization' => 'Bearer ' . $this->get_api_key()),
    );
  }
}
