<?php

/**
 * Anthropic Claude text provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Text;

use KritiAI\Core\Provider;
use KritiAI\Core\Text_Provider;

/**
 * Class Anthropic_Text
 */
class Anthropic_Text extends Provider implements Text_Provider
{

  /**
   * Generate text via the Messages API.
   *
   * @param string $prompt The full prompt.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function generate_text($prompt, $params)
  {
    $settings = $this->settings();
    $model    = $this->get_model($params);
    $model    = $model ? $model : 'claude-opus-5-5';

    // Current Claude models reject temperature/top_p/top_k, so sampling params are not sent.
    $body = array(
      'model'      => $model,
      'max_tokens' => isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) $settings['max_tokens'],
      'messages'   => array(
        array(
          'role'    => 'user',
          'content' => (string) $prompt,
        ),
      ),
    );

    $url     = $this->get_base_url('https://api.anthropic.com') . '/v1/messages';
    $headers = array(
      'x-api-key'         => $this->get_api_key(),
      'anthropic-version' => '2023-06-01',
    );

    // Opus / Sonnet / Fable 5.x: re-run a declined request on a fallback model server-side.
    if (preg_match('/^claude-(opus|sonnet|fable)-5/', $model)) {
      $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
      $body['fallbacks']         = 'default';
    }

    $response = $this->http_post_json($url, $body, $headers, (int) $settings['text_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Anthropic request failed.', 'kriti-ai')));
    }

    $data  = json_decode(wp_remote_retrieve_body($response), true);
    $text  = '';
    $usage = isset($data['usage']) ? $data['usage'] : array();
    $stop  = isset($data['stop_reason']) ? (string) $data['stop_reason'] : '';

    if ('refusal' === $stop) {
      return new \WP_Error('kriti_ai_provider_refusal', __('Claude declined this request. Try rephrasing the prompt.', 'kriti-ai'));
    }

    if (isset($data['content']) && is_array($data['content'])) {
      foreach ($data['content'] as $block) {
        if (isset($block['type']) && 'text' === $block['type'] && isset($block['text'])) {
          $text .= $block['text'];
        }
      }
    }

    if ('' === trim($text)) {
      if ('max_tokens' === $stop) {
        return new \WP_Error('kriti_ai_response_truncated', __('Claude reached the maximum token limit before writing a response. Increase Max tokens and try again.', 'kriti-ai'));
      }
      return new \WP_Error('kriti_ai_empty_response', __('Anthropic returned an empty response.', 'kriti-ai'));
    }

    return array(
      'content'    => trim($text),
      'tokens_in'  => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0,
      'tokens_out' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0,
      'model'      => isset($data['model']) ? (string) $data['model'] : $model,
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
      'url'     => $this->get_base_url('https://api.anthropic.com') . '/v1/models',
      'headers' => array(
        'x-api-key'         => $this->get_api_key(),
        'anthropic-version' => '2023-06-01',
      ),
    );
  }
}
