<?php

/**
 * OpenRouter text provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Text;

use KritiAI\Core\Provider;
use KritiAI\Core\Text_Provider;

/**
 * Class OpenRouter_Text
 */
class OpenRouter_Text extends Provider implements Text_Provider
{

  /**
   * Generate text via the OpenRouter OpenAI-compatible chat API.
   *
   * @param string $prompt Prompt text.
   * @param array  $params Parameters.
   * @return array|\WP_Error
   */
  public function generate_text($prompt, $params)
  {
    $settings = $this->settings();
    $model    = $this->get_model($params);

    // "auto" was offered in earlier versions; OpenRouter's router id is "openrouter/auto".
    if ('' === $model || 'auto' === $model) {
      $model = 'openrouter/auto';
    }

    $body = array(
      'model'       => $model,
      'messages'    => array(
        array(
          'role'    => 'user',
          'content' => (string) $prompt,
        ),
      ),
      'temperature' => isset($params['temperature']) ? (float) $params['temperature'] : (float) $settings['temperature'],
      'max_tokens'  => isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) $settings['max_tokens'],
    );

    $top_k = $this->get_top_k($params);
    if ($top_k > 0) {
      $body['top_k'] = $top_k;
    }

    $url     = $this->get_base_url('https://openrouter.ai/api') . '/v1/chat/completions';
    $headers = array(
      'Authorization' => 'Bearer ' . $this->get_api_key(),
      'HTTP-Referer'  => home_url('/'),
      'X-Title'       => 'Kriti AI',
    );

    $response = $this->http_post_json($url, $body, $headers, (int) $settings['text_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenRouter request failed.', 'kriti-ai')));
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    // OpenRouter can return HTTP 200 with an error payload when the upstream model fails.
    if (! empty($data['error'])) {
      $message = is_array($data['error']) && ! empty($data['error']['message']) ? $data['error']['message'] : __('OpenRouter request failed.', 'kriti-ai');
      return new \WP_Error('kriti_ai_provider_error', $message);
    }

    $text  = isset($data['choices'][0]['message']['content']) ? (string) $data['choices'][0]['message']['content'] : '';
    $usage = isset($data['usage']) ? $data['usage'] : array();

    if ('' === trim($text)) {
      return new \WP_Error('kriti_ai_empty_response', __('OpenRouter returned an empty response.', 'kriti-ai'));
    }

    return array(
      'content'    => trim($text),
      'tokens_in'  => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0,
      'tokens_out' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
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
      'url'     => $this->get_base_url('https://openrouter.ai/api') . '/v1/key',
      'headers' => array('Authorization' => 'Bearer ' . $this->get_api_key()),
    );
  }
}
