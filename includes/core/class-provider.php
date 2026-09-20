<?php

/**
 * Base class shared by every provider.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Class Provider
 */
abstract class Provider
{


	/**
	 * Provider slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Stored configuration array.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Constructor.
	 *
	 * @param string $slug Provider slug.
	 * @param array  $config Provider configuration.
	 */
	public function __construct($slug, $config)
	{
		$this->slug   = $slug;
		$this->config = is_array($config) ? $config : array();
	}

	/**
	 * Get the provider slug.
	 *
	 * @return string
	 */
	public function get_slug()
	{
		return $this->slug;
	}

	/**
	 * Get the provider configuration.
	 *
	 * @return array
	 */
	public function get_config()
	{
		return $this->config;
	}

	/**
	 * Whether the provider is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		return ! empty($this->config['enabled']);
	}

	/**
	 * Get an api key from config, or a constant override.
	 *
	 * Constants follow the pattern KRITI_AI_KEY_{SLUG} e.g. KRITI_AI_KEY_OPENAI.
	 *
	 * @return string
	 */
	protected function get_api_key()
	{
		$constant = 'KRITI_AI_KEY_' . strtoupper(str_replace('-', '_', $this->slug));
		if (defined($constant)) {
			return (string) constant($constant);
		}
		return isset($this->config['api_key']) ? (string) $this->config['api_key'] : '';
	}

	/**
	 * Get a configured model, allowing per-request overrides.
	 *
	 * @param array $params Request parameters.
	 * @return string
	 */
	protected function get_model($params)
	{
		if (! empty($params['model'])) {
			return (string) $params['model'];
		}
		return isset($this->config['model']) ? (string) $this->config['model'] : '';
	}

	/**
	 * Resolve a base URL with a sensible default.
	 *
	 * @param string $fallback_url Default endpoint.
	 * @return string
	 */
	protected function get_base_url($fallback_url)
	{
		if (! empty($this->config['base_url'])) {
			return untrailingslashit($this->config['base_url']);
		}
		return $fallback_url;
	}

	/**
	 * Get the global plugin settings.
	 *
	 * @return array
	 */
	protected function settings()
	{
		return wp_parse_args(
			get_option('kriti_ai_settings', array()),
			array(
				'temperature'   => 0.7,
				'max_tokens'    => 1024,
				'text_timeout'  => 90,
				'media_timeout' => 180,
			)
		);
	}

	/**
	 * Perform a POST JSON request.
	 *
	 * @param string $url Request URL.
	 * @param array  $body JSON-encodable body.
	 * @param array  $headers Extra headers.
	 * @param int    $timeout Timeout in seconds.
	 * @return array|WP_Error
	 */
	protected function http_post_json($url, $body, $headers = array(), $timeout = 90)
	{
		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'headers'     => array_merge(
				array(
					'Content-Type' => 'application/json',
				),
				$headers
			),
			'body'        => wp_json_encode($body),
		);

		return wp_remote_post($url, $args);
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $url Request URL.
	 * @param array  $headers Extra headers.
	 * @param int    $timeout Timeout in seconds.
	 * @return array|WP_Error
	 */
	protected function http_get($url, $headers = array(), $timeout = 90)
	{
		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'headers'     => $headers,
		);

		return wp_remote_get($url, $args);
	}

	/**
	 * Write binary content to a unique temporary file.
	 *
	 * @param string $contents Raw file contents.
	 * @param string $extension File extension without dot.
	 * @return string|WP_Error Absolute path to the temp file.
	 */
	protected function write_tmp_file($contents, $extension)
	{
		$tmp = wp_tempnam('kriti-ai-', sys_get_temp_dir());

		if (! $tmp) {
			return new \WP_Error('kriti_ai_tmp_error', __('Could not create a temporary file.', 'kriti-ai'));
		}

		$target = preg_replace('/\.tmp$/', '.' . $extension, (string) $tmp);

		if (false === file_put_contents($target, $contents)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing provider temp files to local temp directory.
			return new \WP_Error('kriti_ai_tmp_error', __('Could not write temporary file contents.', 'kriti-ai'));
		}

		if ($target !== $tmp && file_exists($tmp)) {
			wp_delete_file($tmp);
		}

		return $target;
	}

	/**
	 * Extract a readable error message from a WP_Error or API response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param string         $fallback Fallback message.
	 * @return string
	 */
	protected function extract_error($response, $fallback = '')
	{
		if (is_wp_error($response)) {
			return $response->get_error_message();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode((string) $body, true);

		if (is_array($data)) {
			if (! empty($data['error']['message'])) {
				return $data['error']['message'];
			}
			if (! empty($data['error']) && is_string($data['error'])) {
				return $data['error'];
			}
			if (! empty($data['message'])) {
				return $data['message'];
			}
		}

		if (! empty($body)) {
			return sprintf(
				/* translators: 1: http code, 2: response body */
				__('HTTP %1$s: %2$s', 'kriti-ai'),
				(int) $code,
				esc_html(substr($body, 0, 300))
			);
		}

		return $fallback ? $fallback : sprintf(
			/* translators: %d: http code */
			__('Request failed with HTTP code %d', 'kriti-ai'),
			(int) $code
		);
	}
}
