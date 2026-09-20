<?php

/**
 * DeepSeek text provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers\Text;

use KritiAI\Core\Provider;
use KritiAI\Core\Text_Provider;

/**
 * Class DeepSeek_Text
 */
class DeepSeek_Text extends Provider implements Text_Provider
{

	/**
	 * Generate text via DeepSeek chat API.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $params Parameters.
	 * @return array|\WP_Error
	 */
	public function generate_text($prompt, $params)
	{
		$settings = $this->settings();

		$body = array(
			'model'       => $this->get_model($params),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => (string) $prompt,
				),
			),
			'temperature' => isset($params['temperature']) ? (float) $params['temperature'] : (float) $settings['temperature'],
			'max_tokens'  => isset($params['max_tokens']) ? (int) $params['max_tokens'] : (int) $settings['max_tokens'],
		);

		$url     = $this->get_base_url('https://api.deepseek.com') . '/v1/chat/completions';
		$headers = array(
			'Authorization' => 'Bearer ' . $this->get_api_key(),
		);

		$response = $this->http_post_json($url, $body, $headers, (int) $settings['text_timeout']);

		if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
			return new \WP_Error('kriti_ai_provider_error', $this->extract_error($response, __('DeepSeek request failed.', 'kriti-ai')));
		}

		$data  = json_decode(wp_remote_retrieve_body($response), true);
		$text  = isset($data['choices'][0]['message']['content']) ? (string) $data['choices'][0]['message']['content'] : '';
		$usage = isset($data['usage']) ? $data['usage'] : array();

		if ('' === $text) {
			return new \WP_Error('kriti_ai_empty_response', __('DeepSeek returned an empty response.', 'kriti-ai'));
		}

		return array(
			'content'    => trim($text),
			'tokens_in'  => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0,
			'tokens_out' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
			'model'      => $this->get_model($params),
		);
	}
}
