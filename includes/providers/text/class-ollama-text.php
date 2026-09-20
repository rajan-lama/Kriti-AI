<?php

/**
 * Ollama (local models) text provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Text;

use KritiAI\Core\Provider;
use KritiAI\Core\Text_Provider;

/**
 * Class Ollama_Text
 */
class Ollama_Text extends Provider implements Text_Provider
{

  /**
   * Generate text via the local /api/generate endpoint.
   *
   * @param string $prompt The full prompt.
   * @param array  $params Parameters.
   * @return array
   */
  public function generate_text($prompt, $params)
  {
    $settings = $this->settings();

    $url  = $this->get_base_url('http://localhost:11434') . '/api/generate';
    $body = array(
      'model'   => $this->get_model($params),
      'prompt'  => (string) $prompt,
      'stream'  => false,
      'think'   => false,
      'options' => array(
        'temperature' => isset($params['temperature']) ? (float) $params['temperature'] : (float) $settings['temperature'],
        'num_predict' => isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) $settings['max_tokens'],
      ),
    );

    $timeout  = (int) $settings['text_timeout'];
    $response = $this->http_post_json($url, $body, array(), $timeout);

    if (! is_wp_error($response) && 400 === wp_remote_retrieve_response_code($response) && false !== strpos(wp_remote_retrieve_body($response), 'think')) {
      unset($body['think']);
      $response = $this->http_post_json($url, $body, array(), $timeout);
    }

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Ollama request failed. Is the Ollama server running?', 'kriti-ai')));
    }

    $data = $this->parse_generate_response(wp_remote_retrieve_body($response));

    if (is_wp_error($data)) {
      return $data;
    }

    if (isset($data['done_reason']) && 'length' === $data['done_reason']) {
      return new \WP_Error(
        'kriti_ai_response_truncated',
        __('Ollama reached the maximum token limit. Increase Max Tokens and try again.', 'kriti-ai')
      );
    }

    if (empty($data['response'])) {
      return new \WP_Error('kriti_ai_empty_response', __('Ollama returned an empty response.', 'kriti-ai'));
    }

    return array(
      'content'    => trim((string) $data['response']),
      'tokens_in'  => isset($data['prompt_eval_count']) ? (int) $data['prompt_eval_count'] : 0,
      'tokens_out' => isset($data['eval_count']) ? (int) $data['eval_count'] : 0,
      'model'      => $this->get_model($params),
    );
  }

  /**
   * Parse Ollama generate responses in streaming or non-streaming JSON form.
   *
   * @param string $body Raw HTTP response body.
   * @return array|\WP_Error
   */
  private function parse_generate_response($body)
  {
    $decoded = json_decode((string) $body, true);

    if (is_array($decoded)) {
      return $decoded;
    }

    $content    = '';
    $tokens_in  = 0;
    $tokens_out = 0;

    $lines = preg_split('/\r\n|\r|\n/', (string) $body);

    foreach ($lines as $line) {
      $line = trim($line);

      if ('' === $line) {
        continue;
      }

      $chunk = json_decode($line, true);

      if (! is_array($chunk)) {
        return new \WP_Error('kriti_ai_provider_error', __('Ollama returned an invalid streaming response.', 'kriti-ai'));
      }

      if (! empty($chunk['error'])) {
        return new \WP_Error('kriti_ai_provider_error', (string) $chunk['error']);
      }

      if (isset($chunk['response'])) {
        $content .= (string) $chunk['response'];
      }

      if (isset($chunk['prompt_eval_count'])) {
        $tokens_in = (int) $chunk['prompt_eval_count'];
      }

      if (isset($chunk['eval_count'])) {
        $tokens_out = (int) $chunk['eval_count'];
      }
    }

    return array(
      'response'          => $content,
      'prompt_eval_count' => $tokens_in,
      'eval_count'        => $tokens_out,
    );
  }
}
