<?php

/**
 * Google Gemini text provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Text;

use KritiAI\Core\Provider;
use KritiAI\Core\Text_Provider;

/**
 * Class Gemini_Text
 */
class Gemini_Text extends Provider implements Text_Provider
{

  /**
   * Generate text via the generateContent endpoint.
   *
   * @param string $prompt The full prompt.
   * @param array  $params Parameters.
   * @return array
   */

  public function generate_text($prompt, $params = array())
  {
    $settings = $this->settings();

    // 1. Clean and normalize the model slug (prevents 'models/models/...' and legacy invalid model IDs)
    $model = $this->get_model($params);
    $clean_model = $this->normalize_model($model);

    // Fallback to latest standard Flash model if empty
    if (empty($clean_model)) {
      $clean_model = 'gemini-3.6-flash';
    }

    $raw_base_url = rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/');
    $base_url     = preg_replace('#/v1beta(/models)?/?$#i', '', $raw_base_url);
    $url          = sprintf(
      '%s/v1beta/models/%s:generateContent?key=%s',
      $base_url,
      rawurlencode($clean_model),
      rawurlencode($this->get_api_key())
    );

    // 2. Build configuration with updated token limits
    $generation_config = array(
      'maxOutputTokens' => isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) $settings['max_tokens'],
    );

    // Optional temperature (Note: Temperature is handled automatically or deprecated in certain reasoning models)
    if (isset($params['temperature']) || isset($settings['temperature'])) {
      $generation_config['temperature'] = isset($params['temperature']) ? (float) $params['temperature'] : (float) $settings['temperature'];
    }

    $body = array(
      'contents'         => array(
        array(
          'role'  => 'user',
          'parts' => array(
            array(
              'text' => (string) $prompt,
            ),
          ),
        ),
      ),
      'generationConfig' => $generation_config,
    );

    $response = $this->http_post_json($url, $body, array(), (int) $settings['text_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error(
        'kriti_ai_provider_error',
        $this->extract_error($response, __('Gemini request failed.', 'kriti-ai'))
      );
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $text = '';

    // 3. Extract output text (ignoring internal thought parts if present)
    if (isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
      foreach ($data['candidates'][0]['content']['parts'] as $part) {
        // Skip thought process objects returned by Gemini thinking models
        if (isset($part['thought']) && true === $part['thought']) {
          continue;
        }

        if (isset($part['text'])) {
          $text .= $part['text'];
        }
      }
    }

    if ('' === trim($text)) {
      return new \WP_Error('rai_empty_response', __('Gemini returned an empty response.', 'kriti-ai'));
    }

    $usage = isset($data['usageMetadata']) ? $data['usageMetadata'] : array();

    return array(
      'content'        => trim($text),
      'tokens_in'      => isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : 0,
      'tokens_out'     => isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] : 0,
      'tokens_thought' => isset($usage['thoughtsTokenCount']) ? (int) $usage['thoughtsTokenCount'] : 0,
      'model'          => $clean_model,
    );
  }

  /**
   * Normalize Gemini model names that have changed upstream.
   *
   * @param string $model Model name.
   * @return string
   */
  private function normalize_model($model)
  {
    $model = preg_replace('/^models\//', '', trim((string) $model));

    $legacy_models = array(
      'gemini-3.6-flash'      => 'gemini-3.6-flash',
      'gemini-3.1-pro'        => 'gemini-3.1-pro',
      'gemini-3.5-flash-lite' => 'gemini-3.5-flash-lite',
    );

    return isset($legacy_models[$model]) ? $legacy_models[$model] : $model;
  }
}
