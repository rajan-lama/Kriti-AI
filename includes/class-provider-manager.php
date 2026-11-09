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
    'text'  => array('openai', 'anthropic', 'gemini', 'deepseek', 'groq', 'openrouter', 'ollama', 'mock'),
    'embedding' => array('openai_embedding', 'gemini_embedding', 'ollama_embedding', 'mock'),
    'image' => array('openai_image', 'gemini_image', 'stability', 'replicate', 'local_ai_image', 'mock'),
    'audio' => array('openai_audio', 'elevenlabs', 'gemini_audio', 'local_ai_audio', 'mock'),
    'video' => array('openai_video', 'gemini_video', 'local_ai_video', 'mock'),
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
   * Build an uncached provider instance from an explicit config, e.g. to
   * test credentials that have not been saved yet.
   *
   * @param string $slug Provider slug.
   * @param array  $config Provider config.
   * @return Provider|null
   */
  public static function create($slug, $config)
  {
    return self::instantiate($slug, $config);
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
      'anthropic'    => 'KritiAI\\Providers\\Text\\Anthropic_Text',
      'gemini'       => 'KritiAI\\Providers\\Text\\Gemini_Text',
      'deepseek'     => 'KritiAI\\Providers\\Text\\DeepSeek_Text',
      'groq'         => 'KritiAI\\Providers\\Text\\Groq_Text',
      'openrouter'   => 'KritiAI\\Providers\\Text\\OpenRouter_Text',
      'ollama'       => 'KritiAI\\Providers\\Text\\Ollama_Text',
      'stability'    => 'KritiAI\\Providers\\Image\\Stability_Image',
      'replicate'    => 'KritiAI\\Providers\\Image\\Replicate_Image',
      'openai_image' => 'KritiAI\\Providers\\Image\\OpenAI_Image',
      'gemini_image' => 'KritiAI\\Providers\\Image\\Gemini_Image',
      'openai_audio' => 'KritiAI\\Providers\\Audio\\OpenAI_Audio',
      'elevenlabs'   => 'KritiAI\\Providers\\Audio\\ElevenLabs_Audio',
      'gemini_audio' => 'KritiAI\\Providers\\Audio\\Gemini_Audio',
      'openai_embedding' => 'KritiAI\\Providers\\Embedding\\OpenAI_Embedding',
      'gemini_embedding' => 'KritiAI\\Providers\\Embedding\\Gemini_Embedding',
      'ollama_embedding' => 'KritiAI\\Providers\\Embedding\\Ollama_Embedding',
      'openai_video' => 'KritiAI\\Providers\\Video\\OpenAI_Video',
      'gemini_video' => 'KritiAI\\Providers\\Video\\Gemini_Video',
      'local_ai_image' => 'KritiAI\\Providers\\Image\\Local_AI_Image',
      'local_ai_audio' => 'KritiAI\\Providers\\Audio\\Local_AI_Audio',
      'local_ai_video' => 'KritiAI\\Providers\\Video\\Local_AI_Video',
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
   * Embedding model of an embedding provider: the model saved on its Integrations Hub card, or the first
   * suggested model when none is saved.
   *
   * @param string $slug Provider slug.
   * @return string
   */
  public static function default_embedding_model($slug)
  {
    $provider = self::get($slug);
    $model    = self::default_model($slug, $provider ? $provider->get_config() : array());

    // The mock provider serves every capability under one slug, so its embeddings get a name of their own.
    if ('mock' === $slug) {
      $model = 'mock-embedding';
    }

    /**
     * Filter the embedding model of a provider.
     *
     * @param string $model Model name.
     * @param string $slug Provider slug.
     */
    return (string) apply_filters('kriti_ai_embedding_model', $model, $slug);
  }

  /**
   * Embed texts with a provider that supports it.
   *
   * @param string[] $texts Texts, in order.
   * @param array    $params Optional: model, dimensions.
   * @param string   $slug Provider slug; empty for the default one.
   * @return array|\WP_Error The provider result plus a `provider` key.
   */
  public static function embed($texts, $params = array(), $slug = '')
  {
    $enabled = self::enabled_slugs('embedding');
    $slug    = in_array($slug, $enabled, true) ? $slug : self::default_provider('embedding');

    if ('' === $slug) {
      return new \WP_Error('kriti_ai_no_embedding_provider', __('No embedding provider is set up. Add an OpenAI or Gemini key, or an Ollama server, in the Integrations Hub.', 'kriti-ai'));
    }

    $provider = self::get($slug);

    if (! $provider instanceof \KritiAI\Core\Embedding_Provider) {
      return new \WP_Error('kriti_ai_no_embedding_provider', __('This provider cannot create embeddings.', 'kriti-ai'));
    }

    $result = $provider->generate_embeddings($texts, $params);

    if (is_wp_error($result)) {
      return $result;
    }

    $result['provider'] = $slug;

    return $result;
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

    // The mock provider is a fallback for trying the plugin without a key.
    if (count($enabled) > 1) {
      $enabled = array_values(array_diff($enabled, array('mock')));
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
    // Local AI servers take an optional key.
    $no_key = array('mock', 'ollama', 'ollama_embedding', 'local_ai_image', 'local_ai_audio', 'local_ai_video');
    return ! in_array($slug, $no_key, true);
  }

  /**
   * Whether the provider's default model is set on the Integrations Hub.
   * Self-hosted servers need this, since their models are whatever the user
   * installed. Embedding providers have it too: there is no per-request model
   * picker for embeddings, so the card is where the model is chosen. Other
   * providers default to their first suggested model.
   *
   * @param string $slug Provider slug.
   * @return bool
   */
  public static function has_model_setting($slug)
  {
    return 'ollama' === $slug || self::is_local_ai($slug) || self::is_embedding($slug);
  }

  /**
   * Whether a provider slug is one of the embedding providers.
   *
   * @param string $slug Provider slug.
   * @return bool
   */
  public static function is_embedding($slug)
  {
    return '_embedding' === substr((string) $slug, -10);
  }

  /**
   * Model a provider uses when a generation does not choose one.
   *
   * @param string $slug Provider slug.
   * @param array  $config Provider config.
   * @return string
   */
  public static function default_model($slug, $config)
  {
    if (self::has_model_setting($slug)) {
      $model = isset($config['model']) ? trim((string) $config['model']) : '';

      // A self-hosted server's models are whatever it has installed, so nothing is guessed for it;
      // an embedding provider falls back to its first suggested model.
      if ('' === $model && self::is_embedding($slug)) {
        $choices = self::model_choices($slug);
        $model   = isset($choices[0]) ? (string) $choices[0] : '';
      }

      return $model;
    }

    $choices = self::model_choices($slug);

    return isset($choices[0]) ? (string) $choices[0] : '';
  }

  /**
   * Whether a provider is a self-hosted Local AI server.
   *
   * @param string $slug Provider slug.
   * @return bool
   */
  public static function is_local_ai($slug)
  {
    return 0 === strpos($slug, 'local_ai_');
  }

  /**
   * Human-readable provider name.
   *
   * @param string $slug Provider slug.
   * @return string
   */
  public static function label($slug)
  {
    $labels = array(
      'openai_embedding' => __('OpenAI (Embeddings)', 'kriti-ai'),
      'gemini_embedding' => __('Gemini (Embeddings)', 'kriti-ai'),
      'ollama_embedding' => __('Ollama (Embeddings)', 'kriti-ai'),
      'local_ai_image' => __('Local AI (Image)', 'kriti-ai'),
      'local_ai_audio' => __('Local AI (Audio)', 'kriti-ai'),
      'local_ai_video' => __('Local AI (Video)', 'kriti-ai'),
    );

    return isset($labels[$slug]) ? $labels[$slug] : ucwords(str_replace('_', ' ', $slug));
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
      'openai_embedding' => array('text-embedding-3-small', 'text-embedding-3-large', 'text-embedding-ada-002'),
      'gemini_embedding' => array('gemini-embedding-001'),
      'ollama_embedding' => array('nomic-embed-text', 'mxbai-embed-large', 'bge-m3', 'all-minilm'),
      'openai_image' => array('dall-e-3', 'dall-e-2'),
      'gemini_image' => array('gemini-3.1-flash-image', 'gemini-2.5-flash-image', 'gemini-3-pro-image', 'gemini-3.1-flash-lite-image'),
      'openai_audio' => array('gpt-4o-mini-tts', 'tts-1', 'tts-1-hd'),
      'gemini_audio' => array('gemini-2.5-flash-preview-tts', 'gemini-3.1-flash-tts-preview', 'gemini-2.5-pro-preview-tts'),
      'openai_video' => array('sora-2', 'sora-2-pro'),
      'gemini_video' => array('veo-3.1-lite-generate-preview', 'veo-3.1-generate-preview', 'veo-3.1-fast-generate-preview'),
      'elevenlabs'   => array('eleven_multilingual_v2', 'eleven_flash_v2_5', 'eleven_turbo_v2_5'),
      'anthropic'    => array('claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'),
      'groq'         => array('llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b'),
      'openrouter'   => array('openrouter/auto', 'anthropic/claude-sonnet-5-5', 'openai/gpt-4o-mini', 'deepseek/deepseek-r1'),
      'stability'    => array('sd3.5-large', 'sd3.5-large-turbo', 'sd3.5-medium', 'core', 'ultra'),
      'replicate'    => array('black-forest-labs/flux-schnell', 'black-forest-labs/flux-dev', 'black-forest-labs/flux-1.1-pro'),
      // Local AI model names depend on what the server has installed.
      'local_ai_image' => array(),
      'local_ai_audio' => array(),
      'local_ai_video' => array(),
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
