<?php

/**
 * Option helpers and sanitization for plugin settings.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Settings.
 */
class Settings
{

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action(
      'admin_post_kriti_ai_save_settings',
      array(__CLASS__, 'handle_save')
    );
  }

  /**
   * Get the global plugin settings.
   *
   * @return array
   */
  public static function get()
  {
    return wp_parse_args(
      get_option('kriti_ai_settings', array()),
      array(
        'default_text_provider'  => 'mock',
        'default_image_provider' => 'mock',
        'default_audio_provider' => 'mock',
        'default_video_provider' => 'mock',
        'temperature'            => 0.7,
        'max_tokens'             => 1024,
        'top_p'                  => 1.0,
        'text_timeout'            => 90,
        'media_timeout'           => 180,
        'video_poll_interval'    => 15,
        'delete_uninstall'       => 0,
      )
    );
  }

  /**
   * Get all provider configurations.
   *
   * @return array
   */
  public static function providers()
  {
    $providers = get_option('kriti_ai_providers', array());

    return is_array($providers) ? $providers : array();
  }

  /**
   * Get a single provider configuration.
   *
   * @param string $slug Provider slug.
   * @return array
   */
  public static function provider($slug)
  {
    $providers = self::providers();
    $slug      = sanitize_key($slug);

    $config = isset($providers[$slug]) && is_array($providers[$slug])
      ? $providers[$slug]
      : array();

    if (isset($config['model'])) {
      $config['model'] = self::normalizeModel(
        $slug,
        $config['model']
      );
    }

    return $config;
  }

  /**
   * Persist sanitized settings from the settings form.
   *
   * @return void
   */
  public static function handle_save()
  {
    if (! current_user_can('manage_options')) {
      wp_die(
        esc_html__(
          'You do not have permission to change these settings.',
          'kriti-ai'
        )
      );
    }

    check_admin_referer('kriti_ai_save_settings');

    /*
		 * Settings.
		 *
		 * Validate that the submitted value is an array before
		 * reading individual fields from it.
		 */
    $settings = array();

    if (
      isset($_POST['kriti_ai_settings']) &&
      is_array($_POST['kriti_ai_settings'])
    ) {
      $raw_settings = wp_unslash($_POST['kriti_ai_settings']);

      $settings = is_array($raw_settings)
        ? $raw_settings
        : array();
    }

    $clean = array(
      'default_text_provider'  => isset($settings['default_text_provider'])
        ? sanitize_key($settings['default_text_provider'])
        : '',

      'default_image_provider' => isset($settings['default_image_provider'])
        ? sanitize_key($settings['default_image_provider'])
        : '',

      'default_audio_provider' => isset($settings['default_audio_provider'])
        ? sanitize_key($settings['default_audio_provider'])
        : '',

      'default_video_provider' => isset($settings['default_video_provider'])
        ? sanitize_key($settings['default_video_provider'])
        : '',

      'temperature' => isset($settings['temperature'])
        ? min(
          2.0,
          max(
            0.0,
            (float) $settings['temperature']
          )
        )
        : 0.7,

      'max_tokens' => isset($settings['max_tokens'])
        ? max(
          1,
          (int) $settings['max_tokens']
        )
        : 1024,

      'top_p' => isset($settings['top_p'])
        ? min(
          1.0,
          max(
            0.0,
            (float) $settings['top_p']
          )
        )
        : 1.0,

      'text_timeout' => isset($settings['text_timeout'])
        ? max(
          10,
          (int) $settings['text_timeout']
        )
        : 90,

      'media_timeout' => isset($settings['media_timeout'])
        ? max(
          10,
          (int) $settings['media_timeout']
        )
        : 180,

      'video_poll_interval' => isset($settings['video_poll_interval'])
        ? max(
          10,
          (int) $settings['video_poll_interval']
        )
        : 15,

      'delete_uninstall' => ! empty($settings['delete_uninstall'])
        ? 1
        : 0,
    );

    update_option('kriti_ai_settings', $clean);

    /*
		 * Provider configurations.
		 */
    $providers = array();

    if (
      isset($_POST['kriti_ai_providers']) &&
      is_array($_POST['kriti_ai_providers'])
    ) {
      $raw_providers = wp_unslash($_POST['kriti_ai_providers']);

      $providers = is_array($raw_providers)
        ? $raw_providers
        : array();
    }

    $existing = self::providers();

    $provider_slugs = array_unique(
      array_merge(
        array_keys($existing),
        array_keys($providers)
      )
    );

    foreach ($provider_slugs as $slug) {
      $slug = sanitize_key($slug);

      if (empty($slug)) {
        continue;
      }

      $config = isset($existing[$slug]) &&
        is_array($existing[$slug])
        ? $existing[$slug]
        : array();

      $submitted = isset($providers[$slug]) &&
        is_array($providers[$slug])
        ? $providers[$slug]
        : array();

      $model_choices = Provider_Manager::model_choices($slug);

      $default_model = isset($model_choices[0])
        ? $model_choices[0]
        : '';

      /*
			 * Model.
			 */
      $model = isset($submitted['model'])
        ? sanitize_text_field($submitted['model'])
        : (
          isset($config['model'])
          ? sanitize_text_field($config['model'])
          : $default_model
        );

      /*
			 * Base URL.
			 */
      $base_url_raw = isset($submitted['base_url'])
        ? esc_url_raw($submitted['base_url'])
        : (
          isset($config['base_url'])
          ? esc_url_raw($config['base_url'])
          : ''
        );

      $base_url_clean = preg_replace(
        '#/v1beta(/models)?/?$#i',
        '',
        rtrim((string) $base_url_raw, '/')
      );

      /*
			 * API key.
			 *
			 * Keep the existing API key when the field is not
			 * submitted or is intentionally left blank.
			 */
      $api_key = isset($config['api_key'])
        ? (string) $config['api_key']
        : '';

      if (
        isset($submitted['api_key']) &&
        is_string($submitted['api_key']) &&
        '' !== trim($submitted['api_key'])
      ) {
        $api_key = sanitize_text_field($submitted['api_key']);
      }

      $existing[$slug] = array(
        'enabled'  => ! empty($submitted['enabled']),
        'api_key'  => $api_key,
        'model'    => self::normalizeModel($slug, $model),
        'base_url' => $base_url_clean,
      );
    }

    /**
     * Filter provider configuration before it is saved.
     *
     * @param array $existing Sanitized provider configs.
     */
    $existing = apply_filters(
      'kriti_ai_providers_saved',
      $existing
    );

    update_option('kriti_ai_providers', $existing);

    wp_safe_redirect(
      add_query_arg(
        array(
          'page'    => 'kriti-ai-providers',
          'updated' => '1',
        ),
        admin_url('admin.php')
      )
    );

    exit;
  }

  /**
   * Normalize provider model names that have changed upstream.
   *
   * @param string $slug  Provider slug.
   * @param string $model Model name.
   * @return string
   */
  private static function normalizeModel($slug, $model)
  {
    $slug  = sanitize_key($slug);
    $model = preg_replace(
      '/^models\//',
      '',
      trim((string) $model)
    );

    $legacy_models = array(
      'gemini' => array(
        'gemini-3.7-flash'      => 'gemini-3.7-flash',
        'gemini-3,6-flash'      => 'gemini-3.6-flash',
        'gemini-3.1-pro'        => 'gemini-3.1-pro',
        'gemini-3.5-flash-lite' => 'gemini-3.5-flash-lite',
      ),
    );

    return isset($legacy_models[$slug][$model])
      ? $legacy_models[$slug][$model]
      : $model;
  }
}
