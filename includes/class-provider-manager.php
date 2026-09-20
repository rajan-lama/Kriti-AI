<?php

/**
 * Provider registry.
 *
 * Resolves configured AI providers by capability (text, audio, video).
 *
 * @package KritiAI
 */

namespace KritiAI;

use KritiAI\Core\Provider;

/**
 * Class Provider_Manager
 */
class Provider_Manager
{


  /**
   * Capability => slugs for built-in providers.
   *
   * @var array
   */
  private static $capabilities = array(
    'text'  => array('openai', 'gemini', 'deepseek', 'ollama', 'mock'),
    'image' => array('openai_image', 'gemini_image', 'mock'),
    'audio' => array('openai_audio', 'gemini_audio', 'mock'),
    'video' => array('openai_video', 'gemini_video', 'mock'),
  );

  /**
   * Instantiated provider instances keyed by slug.
   *
   * @var Provider[]
   */
  private static $instances = array();

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_filter('cron_schedules', array('KritiAI\\Install', 'cronSchedules')); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Interval is defined in Install::cronSchedules().
  }

  /**
   * Get a single provider instance by slug.
   *
   * @param string $slug Provider slug.
   * @return Provider|null
   */
  public static function get($slug)
  {
    if (! isset(self::$instances[$slug])) {
      $configs = get_option('kriti_ai_providers', array());
      $config  = isset($configs[$slug]) ? $configs[$slug] : array();

      $instance = self::instantiate($slug, $config);

      if ($instance) {
        self::$instances[$slug] = $instance;
      }
    }

    return isset(self::$instances[$slug]) ? self::$instances[$slug] : null;
  }

  /**
   * Instantiate a provider class for a slug.
   *
   * @param string $slug Provider slug.
   * @param array  $config Provider config.
   * @return Provider|null
   */
  private static function instantiate($slug, $config)
  {
    $map = array(
      'openai'       => 'KritiAI\\Providers\\Text\\OpenAI_Text',
      'gemini'       => 'KritiAI\\Providers\\Text\\Gemini_Text',
      'deepseek'     => 'KritiAI\\Providers\\Text\\DeepSeek_Text',
      'ollama'       => 'KritiAI\\Providers\\Text\\Ollama_Text',
      'openai_image' => 'KritiAI\\Providers\\Image\\OpenAI_Image',
      'gemini_image' => 'KritiAI\\Providers\\Image\\Gemini_Image',
      'openai_audio' => 'KritiAI\\Providers\\Audio\\OpenAI_Audio',
      'gemini_audio' => 'KritiAI\\Providers\\Audio\\Gemini_Audio',
      'openai_video' => 'KritiAI\\Providers\\Video\\OpenAI_Video',
      'gemini_video' => 'KritiAI\\Providers\\Video\\Gemini_Video',
      'mock'         => 'KritiAI\\Providers\\Mock_Provider',
    );

    $class = isset($map[$slug]) ? $map[$slug] : '';

    /**
     * Allow third parties to map a custom slug to a provider class.
     *
     * @param string $class Provider class name.
     * @param string $slug Provider slug.
     * @param array  $config Provider config.
     */
    $class = apply_filters('kriti_ai_provider_class', $class, $slug, $config);

    if (! $class || ! class_exists($class)) {
      return null;
    }

    return new $class($slug, $config);
  }

  /**
   * Get all provider slugs for a capability.
   *
   * @param string $capability text|image|audio|video.
   * @return string[]
   */
  public static function slugs_for($capability)
  {
    $available = isset(self::$capabilities[$capability]) ? self::$capabilities[$capability] : array();

    /**
     * Filter provider slugs available for a capability.
     *
     * @param string[] $slugs Provider slugs.
     * @param string   $capability Capability name.
     */
    return apply_filters('kriti_ai_providers_for_capability', $available, $capability);
  }

  /**
   * Get enabled provider slugs for a capability.
   *
   * @param string $capability text|image|audio|video.
   * @return string[]
   */
  public static function enabled_slugs($capability)
  {
    $enabled = array();

    foreach (self::slugs_for($capability) as $slug) {
      $provider = self::get($slug);
      if ($provider && $provider->is_enabled()) {
        $enabled[] = $slug;
      }
    }

    return $enabled;
  }

  /**
   * Get the default (first enabled) provider slug for a capability.
   *
   * @param string $capability text|image|audio|video.
   * @return string
   */
  public static function default_provider($capability)
  {
    $settings = get_option('kriti_ai_settings', array());
    $default  = isset($settings['default_' . $capability . '_provider']) ? $settings['default_' . $capability . '_provider'] : '';

    $enabled = self::enabled_slugs($capability);

    if (in_array($default, $enabled, true)) {
      return $default;
    }

    if (! empty($enabled)) {
      return $enabled[0];
    }

    return '';
  }

  /**
   * Whether a provider requires an API key.
   *
   * @param string $slug Provider slug.
   * @return bool
   */
  public static function requires_key($slug)
  {
    $no_key = array('mock', 'ollama');
    return ! in_array($slug, $no_key, true);
  }

  /**
   * A provider is ready when it exists, is enabled, and (for cloud providers)
   * has an API key configured.
   *
   * @param Provider $provider Provider instance.
   * @return bool
   */
  public static function is_ready($provider)
  {
    if (! $provider || ! $provider->is_enabled()) {
      return false;
    }

    if (self::requires_key($provider->get_slug())) {
      $constant = 'KRITI_AI_KEY_' . strtoupper(str_replace('-', '_', $provider->get_slug()));
      $config   = $provider->get_config();
      $has_key  = defined($constant) ? ! empty(constant($constant)) : ! empty($config['api_key']);
      if (! $has_key) {
        return false;
      }
    }

    return true;
  }

  /**
   * Get suggested models for a provider slug.
   *
   * @param string $slug Provider slug.
   * @return string[]
   */
  public static function model_choices($slug)
  {
    $choices = array(
      'openai'       => array('gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano'),
      'gemini'       => array('gemini-3.6-flash', 'gemini-3.7-flash', 'gemini-3.1-pro', 'gemini-3.5-flash-lite'),
      'deepseek'     => array('deepseek-chat', 'deepseek-reasoner'),
      'ollama'       => array('qwen3.5:latest', 'llama3.1', 'llama3.2', 'mistral', 'gemma2', 'llama2', 'llama2-7b', 'llama2-13b', 'llama2-70b'),
      'openai_image' => array('dall-e-3', 'dall-e-2'),
      'gemini_image' => array('gemini-3.1-flash-image', 'gemini-2.5-flash-image', 'gemini-3-pro-image', 'gemini-3.1-flash-lite-image'),
      'openai_audio' => array('gpt-4o-mini-tts', 'tts-1', 'tts-1-hd'),
      'gemini_audio' => array('gemini-2.5-flash-preview-tts', 'gemini-3.1-flash-tts-preview', 'gemini-2.5-pro-preview-tts'),
      'openai_video' => array('sora-2'),
      'gemini_video' => array('veo-3.1-lite-generate-preview', 'veo-3.1-generate-preview', 'veo-3.1-fast-generate-preview'),
      'mock'         => array('mock-model'),
    );

    /**
     * Filter suggested models for a provider.
     *
     * @param string[] $models Suggested models.
     * @param string   $slug Provider slug.
     */
    return apply_filters('kriti_ai_provider_models', isset($choices[$slug]) ? $choices[$slug] : array(), $slug);
  }
}
