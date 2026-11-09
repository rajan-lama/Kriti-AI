<?php

/**
 * Gemini embedding provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Embedding;

use KritiAI\Core\Provider;
use KritiAI\Core\Embedding_Provider;

/**
 * Class Gemini_Embedding
 */
class Gemini_Embedding extends Provider implements Embedding_Provider
{

  /**
   * Embed texts via the batchEmbedContents endpoint.
   *
   * @param string[] $texts Texts.
   * @param array    $params Optional: model, dimensions.
   * @return array|\WP_Error
   */
  public function generate_embeddings($texts, $params = array())
  {
    $texts = array_values(array_map('strval', (array) $texts));

    if (! $texts) {
      return new \WP_Error('kriti_ai_empty_input', __('There is nothing to embed.', 'kriti-ai'));
    }

    $model    = preg_replace('/^models\//', '', $this->get_model($params));
    $base_url = preg_replace('#/v1beta(/models)?/?$#i', '', rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/'));
    $url      = sprintf('%s/v1beta/models/%s:batchEmbedContents?key=%s', $base_url, rawurlencode($model), rawurlencode($this->get_api_key()));

    $requests = array();

    foreach ($texts as $text) {
      $request = array(
        'model'   => 'models/' . $model,
        'content' => array('parts' => array(array('text' => $text))),
      );

      if (! empty($params['dimensions'])) {
        $request['outputDimensionality'] = (int) $params['dimensions'];
      }

      $requests[] = $request;
    }

    $response = $this->http_post_json($url, array('requests' => $requests), array(), (int) $this->settings()['text_timeout']);

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('Gemini embedding request failed.', 'kriti-ai')));
    }

    $data    = json_decode(wp_remote_retrieve_body($response), true);
    $vectors = array();

    foreach (isset($data['embeddings']) && is_array($data['embeddings']) ? $data['embeddings'] : array() as $item) {
      if (isset($item['values']) && is_array($item['values'])) {
        $vectors[] = array_map('floatval', $item['values']);
      }
    }

    if (count($vectors) !== count($texts)) {
      return new \WP_Error('kriti_ai_empty_response', __('Gemini returned a different number of embeddings than texts.', 'kriti-ai'));
    }

    return array(
      'vectors'    => $vectors,
      'dimensions' => count($vectors[0]),
      'tokens_in'  => 0, // This endpoint does not report usage.
      'model'      => $model,
    );
  }

  /**
   * Request used by the connection test: the configured model itself, so a wrong key and a wrong
   * model name are both caught here and not at indexing time.
   *
   * @return array
   */
  protected function connection_request()
  {
    $base  = preg_replace('#/v1beta(/models)?/?$#i', '', rtrim($this->get_base_url('https://generativelanguage.googleapis.com'), '/'));
    $model = preg_replace('/^models\//', '', $this->get_model(array()));

    return array(
      'url'     => $base . '/v1beta/models/' . rawurlencode($model) . '?key=' . rawurlencode($this->get_api_key()),
      'headers' => array(),
    );
  }
}
