<?php

/**
 * Settings view: defaults for every AI Studio generation parameter.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}

$kriti_ai_settings = Settings::get();
$kriti_ai_choices  = Settings::choices();
$kriti_ai_input    = kriti_ai_settings_input_class();

/**
 * CSS classes shared by every settings input (matches the Integrations Hub).
 *
 * @return string
 */
function kriti_ai_settings_input_class()
{
  return 'regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors';
}

/**
 * Render a labelled number field bound to a setting.
 *
 * @param string $key Setting key.
 * @param string $label Field label.
 * @param array  $attrs min / max / step attributes.
 * @param string $help Optional help text.
 */
function kriti_ai_settings_number($key, $label, $attrs, $help = '')
{
  $kriti_ai_settings = Settings::get();
  $kriti_ai_input    = kriti_ai_settings_input_class();
  ?>
  <div class="kriti-ai-field">
    <label for="kriti-ai-setting-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
    <input type="number" id="kriti-ai-setting-<?php echo esc_attr($key); ?>" name="kriti_ai_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($kriti_ai_settings[$key]); ?>" <?php foreach ($attrs as $attr => $value) : ?><?php echo esc_attr($attr); ?>="<?php echo esc_attr($value); ?>" <?php endforeach; ?>class="<?php echo esc_attr($kriti_ai_input); ?>" />
    <?php if ($help) : ?>
      <p class="description"><?php echo esc_html($help); ?></p>
    <?php endif; ?>
  </div>
  <?php
}

/**
 * Render a labelled select bound to a setting with values from Settings::choices().
 *
 * @param string $key Setting key.
 * @param string $label Field label.
 */
function kriti_ai_settings_select($key, $label)
{
  $kriti_ai_settings = Settings::get();
  $kriti_ai_choices  = Settings::choices();
  $kriti_ai_input    = kriti_ai_settings_input_class();
  ?>
  <div class="kriti-ai-field">
    <label for="kriti-ai-setting-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
    <select id="kriti-ai-setting-<?php echo esc_attr($key); ?>" name="kriti_ai_settings[<?php echo esc_attr($key); ?>]" class="<?php echo esc_attr($kriti_ai_input); ?>">
      <?php foreach ($kriti_ai_choices[$key] as $kriti_ai_value => $kriti_ai_option) : ?>
        <option value="<?php echo esc_attr($kriti_ai_value); ?>" <?php selected($kriti_ai_value, $kriti_ai_settings[$key]); ?>><?php echo esc_html($kriti_ai_option); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php
}

$kriti_ai_capabilities = array(
  'text'  => __('Content', 'kriti-ai'),
  'image' => __('Image', 'kriti-ai'),
  'audio' => __('Audio', 'kriti-ai'),
  'video' => __('Video', 'kriti-ai'),
);
?>
<div class="wrap">
  <?php include 'header.php'; ?>
  <div class="text-kriti-ai-on-background font-kriti-ai-body-md flex flex-col">

    <main class="flex-grow pt-4 w-full">
      <div class="mb-12">
        <h1 class="font-kriti-ai-display-lg text-kriti-ai-display-lg text-kriti-ai-on-background mb-4"><?php esc_html_e('Settings', 'kriti-ai'); ?></h1>
        <p class="font-kriti-ai-body-lg text-kriti-ai-body-lg text-kriti-ai-slate-gray max-w-2xl">
          <?php esc_html_e('Default values for every parameter in AI Studio. AI Studio starts from these values, and you can still change them for a single generation.', 'kriti-ai'); ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=kriti-ai-providers')); ?>"><?php esc_html_e('Manage provider API keys in the Integrations Hub.', 'kriti-ai'); ?></a>
        </p>
      </div>

      <?php if (isset($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirect. ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'kriti-ai'); ?></p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="kriti_ai_save_settings" />
        <input type="hidden" name="kriti_ai_return_page" value="kriti-ai-settings" />
        <?php wp_nonce_field('kriti_ai_save_settings'); ?>

        <!-- Default providers -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="hub"><?php esc_html_e('hub', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Default providers', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <?php foreach ($kriti_ai_capabilities as $kriti_ai_capability => $kriti_ai_label) : ?>
              <?php $kriti_ai_key = 'default_' . $kriti_ai_capability . '_provider'; ?>
              <div class="kriti-ai-field">
                <label for="kriti-ai-setting-<?php echo esc_attr($kriti_ai_key); ?>"><?php echo esc_html($kriti_ai_label); ?></label>
                <select id="kriti-ai-setting-<?php echo esc_attr($kriti_ai_key); ?>" name="kriti_ai_settings[<?php echo esc_attr($kriti_ai_key); ?>]" class="<?php echo esc_attr($kriti_ai_input); ?>">
                  <option value=""><?php esc_html_e('First available', 'kriti-ai'); ?></option>
                  <?php foreach (Provider_Manager::slugs_for($kriti_ai_capability) as $kriti_ai_slug) : ?>
                    <?php $kriti_ai_provider = Provider_Manager::get($kriti_ai_slug); ?>
                    <option value="<?php echo esc_attr($kriti_ai_slug); ?>" <?php selected($kriti_ai_slug, $kriti_ai_settings[$kriti_ai_key]); ?>>
                      <?php
                      echo esc_html(Provider_Manager::label($kriti_ai_slug));
                      if ('mock' !== $kriti_ai_slug && ! ($kriti_ai_provider && $kriti_ai_provider->is_enabled())) {
                        echo ' ' . esc_html__('(not configured)', 'kriti-ai');
                      }
                      ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="description"><?php esc_html_e('If the chosen provider is not configured, AI Studio uses the first available one.', 'kriti-ai'); ?></p>
        </div>

        <!-- Content -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="article"><?php esc_html_e('article', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Content generation', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <?php
            kriti_ai_settings_number('temperature', __('Temperature', 'kriti-ai'), array('min' => 0, 'max' => 2, 'step' => 0.1), __('Higher values produce more creative responses.', 'kriti-ai'));
            kriti_ai_settings_number('max_tokens', __('Max tokens', 'kriti-ai'), array('min' => 64, 'step' => 1), __('Maximum response length.', 'kriti-ai'));
            kriti_ai_settings_number('top_p', __('Top P', 'kriti-ai'), array('min' => 0, 'max' => 1, 'step' => 0.01), __('Nucleus sampling threshold.', 'kriti-ai'));
            kriti_ai_settings_number('top_k', __('Top K', 'kriti-ai'), array('min' => 0, 'max' => 500, 'step' => 1), __('0 disables it. Used by Gemini, Ollama and OpenRouter.', 'kriti-ai'));
            ?>
          </div>
        </div>

        <!-- Image -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="image"><?php esc_html_e('image', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Image generation', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <?php
            kriti_ai_settings_select('image_resolution', __('Resolution', 'kriti-ai'));
            kriti_ai_settings_select('image_aspect_ratio', __('Aspect ratio', 'kriti-ai'));
            kriti_ai_settings_select('image_style', __('Style', 'kriti-ai'));
            kriti_ai_settings_select('image_quality', __('Quality', 'kriti-ai'));
            ?>
          </div>
        </div>

        <!-- Audio -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="graphic_eq"><?php esc_html_e('graphic_eq', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Audio generation', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <div class="kriti-ai-field">
              <label for="kriti-ai-setting-audio_voice"><?php esc_html_e('Voice', 'kriti-ai'); ?></label>
              <input type="text" id="kriti-ai-setting-audio_voice" name="kriti_ai_settings[audio_voice]" value="<?php echo esc_attr($kriti_ai_settings['audio_voice']); ?>" placeholder="alloy" class="<?php echo esc_attr($kriti_ai_input); ?>" />
              <p class="description"><?php esc_html_e('Leave empty for each provider\'s default voice.', 'kriti-ai'); ?></p>
            </div>
            <?php
            kriti_ai_settings_number('audio_speed', __('Speed', 'kriti-ai'), array('min' => 0.25, 'max' => 4, 'step' => 0.25));
            kriti_ai_settings_number('audio_stability', __('Stability', 'kriti-ai'), array('min' => 0, 'max' => 1, 'step' => 0.1), __('ElevenLabs only.', 'kriti-ai'));
            kriti_ai_settings_number('audio_similarity_boost', __('Similarity boost', 'kriti-ai'), array('min' => 0, 'max' => 1, 'step' => 0.05), __('ElevenLabs only.', 'kriti-ai'));
            ?>
          </div>
        </div>

        <!-- Video -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="movie"><?php esc_html_e('movie', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Video generation', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <?php
            kriti_ai_settings_number('video_duration', __('Duration (seconds)', 'kriti-ai'), array('min' => 1, 'max' => 60, 'step' => 1));
            kriti_ai_settings_select('video_resolution', __('Resolution', 'kriti-ai'));
            kriti_ai_settings_select('video_aspect_ratio', __('Aspect ratio', 'kriti-ai'));
            kriti_ai_settings_select('video_quality', __('Quality', 'kriti-ai'));
            ?>
          </div>
        </div>

        <!-- Advanced -->
        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="tune"><?php esc_html_e('tune', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Advanced', 'kriti-ai'); ?></h2>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <?php
            kriti_ai_settings_number('text_timeout', __('Text timeout (seconds)', 'kriti-ai'), array('min' => 10, 'step' => 1));
            kriti_ai_settings_number('media_timeout', __('Media timeout (seconds)', 'kriti-ai'), array('min' => 10, 'step' => 1));
            kriti_ai_settings_number('video_poll_interval', __('Video poll interval (seconds)', 'kriti-ai'), array('min' => 10, 'step' => 1), __('How often to check on a running video job.', 'kriti-ai'));
            ?>
            <div class="kriti-ai-field">
              <label for="kriti-ai-setting-delete_uninstall"><?php esc_html_e('Uninstall', 'kriti-ai'); ?></label>
              <input type="hidden" name="kriti_ai_settings[delete_uninstall]" value="0" />
              <label>
                <input type="checkbox" id="kriti-ai-setting-delete_uninstall" name="kriti_ai_settings[delete_uninstall]" value="1" <?php checked(! empty($kriti_ai_settings['delete_uninstall'])); ?> />
                <?php esc_html_e('Delete all plugin data when the plugin is uninstalled', 'kriti-ai'); ?>
              </label>
            </div>
          </div>
        </div>

        <p class="submit">
          <button type="submit" class="button button-kriti-ai-primary button-hero">
            <?php esc_html_e('Save Settings', 'kriti-ai'); ?>
          </button>
        </p>
      </form>
    </main>

  </div>

  <?php include 'footer.php'; ?>
</div>
