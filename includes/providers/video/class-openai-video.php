<?php

/**
 * OpenAI Sora video provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Video;

use KritiAI\Core\Provider;
use KritiAI\Core\Video_Provider;

/**
 * Class OpenAI_Video
 */
class OpenAI_Video extends Provider implements Video_Provider
{

  /**
   * Start async video generation.
   *
   * @param string $prompt Video description.
   * @param array  $params Parameters.
   * @return array
   */
  public function start_video($prompt, $params)
  {
    $settings = $this->settings();

    $size = isset($params['resolution']) ? (string) $params['resolution'] : '1280x720';

    // Normalize common size shorthands.
    if ('720p' === $size) {
      $size = '1280x720';
    } elseif ('1080p' === $size) {
      $size = '1920x1080';
    }

    $body = array(
      'model'  => $this->get_model($params),
      'prompt' => (string) $prompt,
      'size'   => $size,
    );

    if (! empty($params['duration'])) {
      $body['duration'] = (int) $params['duration'];
    }

    if (! empty($params['quality'])) {
      $body['quality'] = (string) $params['quality'];
    }

    $url     = $this->get_base_url('https://api.openai.com') . '/v1/videos';
    $headers = array(
      'Authorization' => 'Bearer ' . $this->get_api_key(),
    );

    $response = $this->http_post_json($url, $body, $headers, (int) $settings['media_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenAI video request failed.', 'kriti-ai')));
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['id'])) {
      return new \WP_Error('kriti_ai_missing_video_id', __('OpenAI did not return a video job id.', 'kriti-ai'));
    }

    return array(
      'status'      => 'pending',
      'external_id' => (string) $data['id'],
    );
  }

  /**
   * Poll a running video generation.
   *
   * @param string $external_id OpenAI video id.
   * @param array  $params Original parameters.
   * @return array
   */
  public function poll_video($external_id, $params)
  {
    $settings = $this->settings();

    $url     = $this->get_base_url('https://api.openai.com') . '/v1/videos/' . rawurlencode($external_id);
    $headers = array(
      'Authorization' => 'Bearer ' . $this->get_api_key(),
    );

    $response = $this->http_get($url, $headers, (int) $settings['media_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenAI video polling failed.', 'kriti-ai')));
    }

    $data   = json_decode(wp_remote_retrieve_body($response), true);
    $id     = isset($data['id']) ? $data['id'] : $external_id;
    $status = isset($data['status']) ? strtolower((string) $data['status']) : '';

    if ('completed' === $status || 'succeeded' === $status) {
      return $this->download_completed($id, $settings);
    }

    if ('failed' === $status || 'cancelled' === $status || 'expired' === $status) {
      $error = ! empty($data['error']['message']) ? $data['error']['message'] : __('The video job was not completed.', 'kriti-ai');
      return new \WP_Error('kriti_ai_video_failed', $error);
    }

    return array(
      'status'      => 'pending',
      'external_id' => $id,
    );
  }

  /**
   * Download the finished video file.
   *
   * @param string $id Video id.
   * @param array  $settings Plugin settings.
   * @return array|WP_Error
   */
  private function download_completed($id, $settings)
  {
    $url     = $this->get_base_url('https://api.openai.com') . '/v1/videos/' . rawurlencode($id) . '/content';
    $headers = array(
      'Authorization' => 'Bearer ' . $this->get_api_key(),
      'Accept'        => 'application/vnd.openai.video+mp4',
    );

    $response = $this->http_get($url, $headers, 300);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_video_download', $this->extract_error($response, __('Could not download the generated video.', 'kriti-ai')));
    }

    $file = $this->write_tmp_file(wp_remote_retrieve_body($response), 'mp4');

    if (is_wp_error($file)) {
      return $file;
    }

    return array(
      'status'    => 'completed',
      'tmp_path'  => $file,
      'mime'      => 'video/mp4',
      'extension' => 'mp4',
    );
  }
}
