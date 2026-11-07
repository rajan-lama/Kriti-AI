<?php

/**
 * Ollama embedding provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Embedding;

use KritiAI\Core\Provider;
use KritiAI\Core\Embedding_Provider;

/**
 * Class Ollama_Embedding
 */
class Ollama_Embedding extends Provider implements Embedding_Provider
{

  /**
   * Embed texts via the /api/embed endpoint. The model must already be pulled on the server.
   *
   * @param string[] $texts Texts.
   * @param array    $params Optional: model.
   * @return array|\WP_Error
   */
  public function generate_embeddings($texts, $params = array())
  {
    $texts = array_values(array_map('strval', (array) $texts));

    if (! $texts) {
      return new \WP_Error('kriti_ai_empty_input', __('There is nothing to embed.', 'kriti-ai'));
    }

    $model    = $this->get_model($params);
    $response = $this->http_post_json(
      $this->get_base_url('http://localhost:11434') . '/api/embed',
      array(
        'model' => $model,
        'input' => $texts,
      ),
      array(),
      (int) $this->settings()['text_timeout']
    );

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      $message = $this->extract_error($response, __('Ollama embedding request failed. Is the server running?', 'kriti-ai'));

      if (! is_wp_error($response) && 404 === wp_remote_retrieve_response_code($response)) {
        /* translators: %s: model name. */
        $message = sprintf(__('The embedding model “%1$s” is not installed on the Ollama server. Install it with: ollama pull %1$s', 'kriti-ai'), $model);
      }

      return new \WP_Error('kriti_ai_provider_error', $message);
    }

    $data    = json_decode(wp_remote_retrieve_body($response), true);
    $vectors = array();

    foreach (isset($data['embeddings']) && is_array($data['embeddings']) ? $data['embeddings'] : array() as $item) {
      if (is_array($item)) {
        $vectors[] = array_map('floatval', $item);
      }
    }

    if (count($vectors) !== count($texts)) {
      return new \WP_Error('kriti_ai_empty_response', __('Ollama returned a different number of embeddings than texts.', 'kriti-ai'));
    }

    return array(
      'vectors'    => $vectors,
      'dimensions' => count($vectors[0]),
      'tokens_in'  => isset($data['prompt_eval_count']) ? (int) $data['prompt_eval_count'] : 0,
      'model'      => $model,
    );
  }

  /**
   * Connection test: the server must answer, and the embedding model must be installed on it.
   *
   * Reaching the server is not enough: with the model missing every embedding request fails.
   *
   * @return true|\WP_Error
   */
  public function test_connection()
  {
    $request  = $this->connection_request();
    $response = $this->http_get($request['url'], $request['headers'], 20);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_connection_failed', $this->extract_error($response, __('Could not reach the Ollama server. Is it running, and is the URL right?', 'kriti-ai')));
    }

    $data      = json_decode(wp_remote_retrieve_body($response), true);
    $installed = array();

    foreach (isset($data['models']) && is_array($data['models']) ? $data['models'] : array() as $item) {
      if (! empty($item['name'])) {
        $installed[] = (string) $item['name'];
      }
    }

    $model = $this->get_model(array());

    // A name without a tag means ":latest" to Ollama.
    if (in_array($model, $installed, true) || in_array($model . ':latest', $installed, true)) {
      return true;
    }

    return new \WP_Error(
      'kriti_ai_model_missing',
      sprintf(
        /* translators: %s: model name. */
        __('Connected to Ollama, but the embedding model “%1$s” is not installed. Install it with: ollama pull %1$s', 'kriti-ai'),
        $model
      )
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
      'url'     => $this->get_base_url('http://localhost:11434') . '/api/tags',
      'headers' => array(),
    );
  }
}
