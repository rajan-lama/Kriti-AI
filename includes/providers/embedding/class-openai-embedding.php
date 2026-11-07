<?php

/**
 * OpenAI embedding provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Embedding;

use KritiAI\Core\Provider;
use KritiAI\Core\Embedding_Provider;

/**
 * Class OpenAI_Embedding
 */
class OpenAI_Embedding extends Provider implements Embedding_Provider
{

  /**
   * Embed texts via the embeddings endpoint.
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

    $model = $this->get_model($params);
    $body  = array(
      'model' => $model,
      'input' => $texts,
    );

    if (! empty($params['dimensions'])) {
      $body['dimensions'] = (int) $params['dimensions'];
    }

    $response = $this->http_post_json(
      $this->get_base_url('https://api.openai.com') . '/v1/embeddings',
      $body,
      array('Authorization' => 'Bearer ' . $this->get_api_key()),
      (int) $this->settings()['text_timeout']
    );

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
      return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('OpenAI embedding request failed.', 'kriti-ai')));
    }

    $data  = json_decode(wp_remote_retrieve_body($response), true);
    $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : array();

    // The API returns an index with each vector; do not rely on the order.
    usort(
      $items,
      static function ($a, $b) {
        return (isset($a['index']) ? (int) $a['index'] : 0) <=> (isset($b['index']) ? (int) $b['index'] : 0);
      }
    );

    $vectors = array();

    foreach ($items as $item) {
      if (isset($item['embedding']) && is_array($item['embedding'])) {
        $vectors[] = array_map('floatval', $item['embedding']);
      }
    }

    if (count($vectors) !== count($texts)) {
      return new \WP_Error('kriti_ai_empty_response', __('OpenAI returned a different number of embeddings than texts.', 'kriti-ai'));
    }

    return array(
      'vectors'    => $vectors,
      'dimensions' => count($vectors[0]),
      'tokens_in'  => isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : 0,
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
    return array(
      'url'     => $this->get_base_url('https://api.openai.com') . '/v1/models/' . rawurlencode($this->get_model(array())),
      'headers' => array('Authorization' => 'Bearer ' . $this->get_api_key()),
    );
  }
}
