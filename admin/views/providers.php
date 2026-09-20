<?php

/**
 * Settings view.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}

$kriti_ai_settings  = Settings::get();
$kriti_ai_providers = Settings::providers();

$kriti_ai_capabilities = array(
  'text'  => __('Text / Content providers', 'kriti-ai'),
  'image' => __('Image providers', 'kriti-ai'),
  'audio' => __('Audio providers', 'kriti-ai'),
  'video' => __('Video providers', 'kriti-ai'),
);
?>
<div class="wrap">
  <?php include 'header.php'; ?>
  <div class="text-kriti-ai-on-background font-kriti-ai-body-md flex flex-col">

    <!-- Main Content -->
    <main class="flex-grow pt-4 w-full">
      <!-- Header Section -->
      <div class="mb-12">
        <h1 class="font-kriti-ai-display-lg text-kriti-ai-display-lg text-kriti-ai-on-background mb-4"><?php esc_html_e('Integrations Hub', 'kriti-ai'); ?></h1>
        <p class="font-kriti-ai-body-lg text-kriti-ai-body-lg text-kriti-ai-slate-gray max-w-2xl"><?php esc_html_e('Connect Kriti AI to industry-leading LLM providers and seamlessly integrate with your existing WordPress ecosystem tools.', 'kriti-ai'); ?></p>
      </div>
      <!-- LLM Providers Section -->
      <section class="mb-6">
        <div class="flex items-center gap-2 mb-6">
          <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="memory"><?php esc_html_e('memory', 'kriti-ai'); ?></span>
          <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('LLM Providers', 'kriti-ai'); ?></h2>
        </div>
      </section>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="kriti_ai_save_settings" />
        <?php wp_nonce_field('kriti_ai_save_settings'); ?>

        <?php foreach ($kriti_ai_capabilities as $kriti_ai_capability => $kriti_ai_label) : ?>
          <div class="kriti-ai-panel">
            <div class="flex items-center gap-2 mb-6">
              <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="memory"><?php esc_html_e('memory', 'kriti-ai'); ?></span>
              <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface">
                <?php
                printf(
                  /* translators: %s: provider capability label. */
                  esc_html__('LLM for %s', 'kriti-ai'),
                  esc_html($kriti_ai_label)
                );
                ?>
              </h2>
            </div>
            <section class="mb-16">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-kriti-ai-gutter">

                <?php foreach (Provider_Manager::slugs_for($kriti_ai_capability) as $kriti_ai_slug) : ?>
                  <?php
                  $kriti_ai_config       = isset($kriti_ai_providers[$kriti_ai_slug]) ? $kriti_ai_providers[$kriti_ai_slug] : array();
                  $kriti_ai_requires_key = Provider_Manager::requires_key($kriti_ai_slug);
                  $kriti_ai_slug_label   = ucwords(str_replace('_', ' ', $kriti_ai_slug));
                  $kriti_ai_models       = Provider_Manager::model_choices($kriti_ai_slug);
                  $kriti_ai_model_value  = isset($kriti_ai_config['model']) && '' !== $kriti_ai_config['model'] ? $kriti_ai_config['model'] : (isset($kriti_ai_models[0]) ? $kriti_ai_models[0] : '');
                  ?>
                  <div>

                    <!-- OpenAI Card -->
                    <div class="bg-kriti-ai-surface rounded-xl border border-kriti-ai-outline-variant/50 p-6 flex flex-col h-full shadow-[0_4px_12px_rgba(0,0,0,0.02)] hover:shadow-[0_8px_24px_rgba(99,102,241,0.08)] transition-shadow duration-300 relative overflow-hidden group">
                      <div class="absolute top-0 left-0 w-full h-[2px] bg-kriti-ai-primary scale-x-100 origin-left transition-transform duration-300"></div>
                      <div class="flex justify-between items-start">
                        <div class="rounded-lg flex flex-column items-start justify-center">
                          <!-- <span class="material-symbols-outlined text-kriti-ai-on-surface-variant" data-icon="psychology">psychology</span> -->
                          <h3 class="font-kriti-ai-title-md text-kriti-ai-title-md text-kriti-ai-on-surface mb-1"><?php echo esc_html($kriti_ai_slug_label); ?></h3>
                        </div>
                        <label class="switch">
                          <input type="checkbox" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][enabled]" value="1" <?php checked(! empty($kriti_ai_config['enabled'])); ?> />
                          <span class="slider"></span>
                        </label>
                      </div>
                      <div class="mt-auto">
                        <?php if ($kriti_ai_requires_key) : ?>
                          <div class="kriti-ai-field">
                            <label><?php esc_html_e('API key', 'kriti-ai'); ?></label>
                            <input type="password" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][api_key]" value="<?php echo esc_attr(isset($kriti_ai_config['api_key']) ? $kriti_ai_config['api_key'] : ''); ?>" class="regular-text  w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" autocomplete="off" />
                            <p class="description">
                              <?php esc_html_e('You can also define the constant ', 'kriti-ai'); ?>
                              <code><?php echo esc_html('KRITI_AI_KEY_' . strtoupper(str_replace('-', '_', $kriti_ai_slug))); ?></code>
                              <?php esc_html_e(' in wp-config.php; it overrides this field.', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php endif; ?>

                        <?php if ($kriti_ai_slug === "ollama") : ?>
                          <div class="kriti-ai-field">
                            <p class="description">
                              <?php esc_html_e('Please use full model name like qwen3.5:latest', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php endif; ?>

                        <div class="kriti-ai-field">
                          <label><?php esc_html_e('Model', 'kriti-ai'); ?></label>

                          <?php if ($kriti_ai_slug === "ollama") : ?>
                            <input type="text" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][model]" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" value=<?php echo esc_html($kriti_ai_model_value); ?>>
                          <?php else : ?>
                            <select name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][model]" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors">
                              <?php foreach ($kriti_ai_models as $kriti_ai_model) : ?>
                                <option value="<?php echo esc_attr($kriti_ai_model); ?>" <?php selected($kriti_ai_model, $kriti_ai_model_value); ?>><?php echo esc_html($kriti_ai_model); ?></option>
                              <?php endforeach; ?>
                              <?php if ('' !== $kriti_ai_model_value && ! in_array($kriti_ai_model_value, $kriti_ai_models, true)) : ?>
                                <option value="<?php echo esc_attr($kriti_ai_model_value); ?>" selected><?php echo esc_html($kriti_ai_model_value); ?></option>
                              <?php endif; ?>
                            </select>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>

              </div>
            </section>
          </div>
        <?php endforeach; ?>

        <div class="kriti-ai-panel">
          <div class="flex items-center gap-2 mb-6">
            <span class="material-symbols-outlined text-kriti-ai-primary text-xl" data-icon="tune"><?php esc_html_e('tune', 'kriti-ai'); ?></span>
            <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Generation defaults', 'kriti-ai'); ?></h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
            <div class="kriti-ai-field">
              <label for="kriti-ai-provider-temperature"><?php esc_html_e('Temperature', 'kriti-ai'); ?></label>
              <input type="number" id="kriti-ai-provider-temperature" name="kriti_ai_settings[temperature]" min="0" max="2" step="0.1" placeholder="0.7" value="<?php echo esc_attr($kriti_ai_settings['temperature']); ?>" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
            </div>

            <div class="kriti-ai-field">
              <label for="kriti-ai-provider-max-tokens"><?php esc_html_e('Max tokens', 'kriti-ai'); ?></label>
              <input type="number" id="kriti-ai-provider-max-tokens" name="kriti_ai_settings[max_tokens]" min="1" step="1" placeholder="1000" value="<?php echo esc_attr($kriti_ai_settings['max_tokens']); ?>" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
            </div>

            <div class="kriti-ai-field">
              <label for="kriti-ai-provider-text-timeout"><?php esc_html_e('Text timeout (seconds)', 'kriti-ai'); ?></label>
              <input type="number" id="kriti-ai-provider-text-timeout" name="kriti_ai_settings[text_timeout]" min="10" step="1" placeholder="30" value="<?php echo esc_attr($kriti_ai_settings['text_timeout']); ?>" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
            </div>

            <div class="kriti-ai-field">
              <label for="kriti-ai-provider-media-timeout"><?php esc_html_e('Media timeout (seconds)', 'kriti-ai'); ?></label>
              <input type="number" id="kriti-ai-provider-media-timeout" name="kriti_ai_settings[media_timeout]" min="10" step="1" placeholder="30" value="<?php echo esc_attr($kriti_ai_settings['media_timeout']); ?>" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
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
