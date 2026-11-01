<?php

/**
 * Shared helpers for the Local AI image, audio and video providers.
 *
 * @package KritiAI
 */

namespace KritiAI\Providers;

/**
 * Class Local_AI
 */
class Local_AI
{

  /**
   * Configured server URL without a trailing /v1, since the OpenAI
   * providers append their own /v1/... paths.
   *
   * @param array $config Provider config.
   * @return string
   */
  public static function base_url($config)
  {
    $url = isset($config['base_url']) ? untrailingslashit((string) $config['base_url']) : '';

    return (string) preg_replace('#/v1$#i', '', $url);
  }

  /**
   * The connection test only needs the server URL.
   *
   * @param array $config Provider config.
   * @return \WP_Error|null
   */
  public static function check_url($config)
  {
    if ('' === self::base_url($config)) {
      return new \WP_Error('kriti_ai_missing_url', __('Enter your Local AI API URL first.', 'kriti-ai'));
    }

    return null;
  }

  /**
   * Local servers have no default endpoint or model, so both must be set.
   *
   * @param array  $config Provider config.
   * @param string $model Model for this request.
   * @return \WP_Error|null
   */
  public static function check_config($config, $model)
  {
    if ('' === self::base_url($config)) {
      return new \WP_Error('kriti_ai_missing_url', __('Enter your Local AI API URL in the Integrations Hub first.', 'kriti-ai'));
    }

    if ('' === (string) $model) {
      return new \WP_Error('kriti_ai_missing_model', __('Enter the model name served by your Local AI server in the Integrations Hub.', 'kriti-ai'));
    }

    return null;
  }
}
