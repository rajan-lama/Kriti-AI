<?php

/**
 * Stability AI image provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Image;

use KritiAI\Core\Provider;
use KritiAI\Core\Image_Provider;

/**
 * Class Stability_Image
 */
class Stability_Image extends Provider implements Image_Provider
{

  /**
   * Aspect ratios accepted by the Stable Image API.
   *
   * @var string[]
   */
  private static $aspect_ratios = array('16:9', '1:1', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21');

  /**
   * Generate an image using the Stable Image API (SD3.5, Core or Ultra).
   *
   * @param string $prompt Description of the image.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function generate_image($prompt, $params)
  {
    $settings = $this->settings();
    $model    = $this->normalize_model($this->get_model($params));

    $aspect_ratio = isset($params['aspect_ratio']) ? (string) $params['aspect_ratio'] : '1:1';
    if (! in_array($aspect_ratio, self::$aspect_ratios, true)) {
      $aspect_ratio = '1:1';
    }

    $fields = array(
      'prompt'        => (string) $prompt,
      'output_format' => 'png',
      'aspect_ratio'  => $aspect_ratio,
    );

    // "core" and "ultra" have their own endpoints; SD3.5 models share /sd3 with a model field.
    if ('core' === $model || 'ultra' === $model) {
      $endpoint = $model;
    } else {
      $endpoint        = 'sd3';
      $fields['model'] = $model;
    }

    $url      = $this->get_base_url('https://api.stability.ai') . '/v2beta/stable-image/generate/' . $endpoint;
    $boundary = 'kriti-ai-' . wp_generate_password(24, false);

    $response = wp_remote_post(
      $url,
      array(
        'timeout'     => (int) $settings['media_timeout'],
        'redirection' => 5,
        'httpversion' => '1.1',
        'headers'     => array(
          'Authorization' => 'Bearer ' . $this->get_api_key(),
          'Accept'        => 'image/*',
          'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
        ),
        'body'        => $this->multipart_body($fields, $boundary),
      )
    );

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->stability_error($response));
    }

    $binary = wp_remote_retrieve_body($response);

    if (empty($binary)) {
      return new \WP_Error('kriti_ai_empty_response', __('Stability AI returned an empty response.', 'kriti-ai'));
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

  /**
   * Map retired SD3 model ids to their SD3.5 replacements.
   *
   * @param string $model Model slug.
   * @return string
   */
  private function normalize_model($model)
  {
    $map = array(
      ''                => 'sd3.5-large',
      'sd3-large'       => 'sd3.5-large',
      'sd3-large-turbo' => 'sd3.5-large-turbo',
      'sd3-medium'      => 'sd3.5-medium',
    );

    return isset($map[$model]) ? $map[$model] : $model;
  }

  /**
   * Build a multipart/form-data body from text fields.
   *
   * @param array  $fields Field name => value.
   * @param string $boundary Multipart boundary.
   * @return string
   */
  private function multipart_body($fields, $boundary)
  {
    $body = '';

    foreach ($fields as $name => $value) {
      $body .= '--' . $boundary . "\r\n";
      $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
      $body .= $value . "\r\n";
    }

    return $body . '--' . $boundary . "--\r\n";
  }

  /**
   * Stability returns errors as {"name": "...", "errors": ["..."]}.
   *
   * @param array|\WP_Error $response HTTP response.
   * @return string
   */
  private function stability_error($response)
  {
    if (! is_wp_error($response)) {
      $data = json_decode((string) wp_remote_retrieve_body($response), true);
      if (! empty($data['errors']) && is_array($data['errors'])) {
        return implode(' ', array_map('strval', $data['errors']));
      }
    }

    return $this->extract_error($response, __('Stability AI image request failed.', 'kriti-ai'));
  }

  /**
   * Request used by the connection test.
   *
   * @return array
   */
  protected function connection_request()
  {
    return array(
      'url'     => $this->get_base_url('https://api.stability.ai') . '/v1/user/account',
      'headers' => array('Authorization' => 'Bearer ' . $this->get_api_key()),
    );
  }
}
