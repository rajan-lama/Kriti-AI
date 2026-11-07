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
        // Default provider per type; '' means the first available one.
        'default_text_provider'  => '',
        'default_image_provider' => '',
        'default_audio_provider' => '',
        'default_video_provider' => '',
        'default_embedding_provider' => '',
        // Content generation.
        'temperature'            => 0.7,
        'max_tokens'             => 1024,
        'top_p'                  => 1.0,
        'top_k'                  => 0,
        // Image generation.
        'image_resolution'       => '1024x1024',
        'image_aspect_ratio'     => '1:1',
        'image_style'            => 'vivid',
        'image_quality'          => 'standard',
        // Audio generation.
        'audio_voice'            => '',
        'audio_speed'            => 1.0,
        'audio_stability'        => 0.5,
        'audio_similarity_boost' => 0.75,
        // Video generation.
        'video_duration'         => 8,
        'video_resolution'       => '1280x720',
        'video_aspect_ratio'     => '16:9',
        'video_quality'          => 'medium',
        // Advanced.
        'text_timeout'           => 90,
        'media_timeout'          => 180,
        'video_poll_interval'    => 15,
        'delete_uninstall'       => 0,
      )
    );
  }

  /**
   * Allowed values for the select-type generation settings, shared by the
   * Settings page and AI Studio.
   *
   * @return array<string, array<string, string>> Setting key => value => label.
   */
  public static function choices()
  {
    return array(
      'image_resolution'   => array(
        '1024x1024' => '1024x1024',
        '1024x1792' => '1024x1792',
        '1792x1024' => '1792x1024',
        '512x512'   => '512x512',
      ),
      'image_aspect_ratio' => array(
        '1:1'  => __('1:1 (Square)', 'kriti-ai'),
        '16:9' => __('16:9 (Widescreen)', 'kriti-ai'),
        '9:16' => __('9:16 (Portrait)', 'kriti-ai'),
      ),
      'image_style'        => array(
        'vivid'   => __('Vivid', 'kriti-ai'),
        'natural' => __('Natural', 'kriti-ai'),
      ),
      'image_quality'      => array(
        'standard' => __('Standard', 'kriti-ai'),
        'hd'       => __('HD', 'kriti-ai'),
      ),
      'video_resolution'   => array(
        '720p'      => '720p',
        '1080p'     => '1080p',
        '1280x720'  => '1280x720',
        '1920x1080' => '1920x1080',
      ),
      'video_aspect_ratio' => array(
        '16:9' => '16:9',
        '9:16' => '9:16',
        '1:1'  => '1:1',
      ),
      'video_quality'      => array(
        'low'    => __('Low', 'kriti-ai'),
        'medium' => __('Medium', 'kriti-ai'),
        'high'   => __('High', 'kriti-ai'),
      ),
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

    /*
		 * Only fields present in the request are updated, so a form that
		 * submits a subset of settings (e.g. the Integrations Hub) keeps the
		 * rest. Generation options are set on the AI Studio page.
		 */
    $clean = self::get();

    foreach (array('text', 'image', 'audio', 'video', 'embedding') as $capability) {
      $key = 'default_' . $capability . '_provider';
      if (isset($settings[$key])) {
        $slug        = sanitize_key($settings[$key]);
        $clean[$key] = in_array($slug, Provider_Manager::slugs_for($capability), true) ? $slug : '';
      }
    }

    foreach (self::choices() as $key => $options) {
      if (isset($settings[$key]) && isset($options[(string) $settings[$key]])) {
        $clean[$key] = (string) $settings[$key];
      }
    }

    if (isset($settings['audio_voice'])) {
      $clean['audio_voice'] = sanitize_text_field($settings['audio_voice']);
    }

    $numbers = array(
      // key => array(min, max, cast).
      'temperature'         => array(0.0, 2.0, 'float'),
      'max_tokens'          => array(1, PHP_INT_MAX, 'int'),
      'top_p'               => array(0.0, 1.0, 'float'),
      'top_k'               => array(0, 500, 'int'),
      'text_timeout'        => array(10, PHP_INT_MAX, 'int'),
      'media_timeout'       => array(10, PHP_INT_MAX, 'int'),
      'video_poll_interval' => array(10, PHP_INT_MAX, 'int'),
      'audio_speed'            => array(0.25, 4.0, 'float'),
      'audio_stability'        => array(0.0, 1.0, 'float'),
      'audio_similarity_boost' => array(0.0, 1.0, 'float'),
      'video_duration'         => array(1, 60, 'int'),
    );

    foreach ($numbers as $key => $rule) {
      if (isset($settings[$key]) && '' !== $settings[$key]) {
        $value       = 'int' === $rule[2] ? (int) $settings[$key] : (float) $settings[$key];
        $clean[$key] = min($rule[1], max($rule[0], $value));
      }
    }

    if (isset($settings['delete_uninstall'])) {
      $clean['delete_uninstall'] = ! empty($settings['delete_uninstall']) ? 1 : 0;
    }

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

    // The Settings page has no provider fields; leave provider configs alone.
    $provider_slugs = isset($_POST['kriti_ai_providers'])
      ? array_unique(
        array_merge(
          array_keys($existing),
          array_keys($providers)
        )
      )
      : array();

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

      // Only self-hosted providers keep a saved model; the rest use their default.
      if (! Provider_Manager::has_model_setting($slug)) {
        $model = '';
      }

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
			 * The field shows the saved key, so a submitted blank value means
			 * the key was removed (which disables the provider). Keep the
			 * existing key when the field is not submitted at all.
			 */
      $api_key = isset($config['api_key'])
        ? (string) $config['api_key']
        : '';

      if (
        isset($submitted['api_key']) &&
        is_string($submitted['api_key'])
      ) {
        $api_key = sanitize_text_field(trim($submitted['api_key']));
      }

      // Mirrors Provider::is_enabled(): enabled by a key, or a server URL for self-hosted providers.
      if ('mock' === $slug) {
        $enabled = true;
      } elseif (Provider_Manager::requires_key($slug)) {
        $enabled = '' !== $api_key;
      } else {
        $enabled = '' !== $base_url_clean;
      }

      $existing[$slug] = array(
        'enabled'  => $enabled,
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

    $return_page = isset($_POST['kriti_ai_return_page']) && 'kriti-ai-settings' === $_POST['kriti_ai_return_page']
      ? 'kriti-ai-settings'
      : 'kriti-ai-providers';

    wp_safe_redirect(
      add_query_arg(
        array(
          'page'    => $return_page,
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
