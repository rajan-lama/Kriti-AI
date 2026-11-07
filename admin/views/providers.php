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

$kriti_ai_providers = Settings::providers();

$kriti_ai_rendered_slugs   = array();

$kriti_ai_capabilities = array(
  'text'  => __('Text / Content providers', 'kriti-ai'),
  'image' => __('Image providers', 'kriti-ai'),
  'audio' => __('Audio providers', 'kriti-ai'),
  'video' => __('Video providers', 'kriti-ai'),
  'embedding' => __('Embedding providers', 'kriti-ai'),
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
        <p class="description"><?php esc_html_e('A provider is enabled automatically once its API key is saved (or its server URL, for Ollama and Local AI). Clear the field and save to disable it.', 'kriti-ai'); ?></p>
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
                  // A provider serving several capabilities (e.g. mock) shares one config, so render it once.
                  if (isset($kriti_ai_rendered_slugs[$kriti_ai_slug])) {
                    continue;
                  }
                  $kriti_ai_rendered_slugs[$kriti_ai_slug] = true;

                  $kriti_ai_config       = isset($kriti_ai_providers[$kriti_ai_slug]) ? $kriti_ai_providers[$kriti_ai_slug] : array();
                  $kriti_ai_is_local     = Provider_Manager::is_local_ai($kriti_ai_slug);
                  $kriti_ai_requires_key = Provider_Manager::requires_key($kriti_ai_slug);
                  $kriti_ai_slug_label   = Provider_Manager::label($kriti_ai_slug);
                  $kriti_ai_models       = Provider_Manager::model_choices($kriti_ai_slug);
                  $kriti_ai_model_value  = isset($kriti_ai_config['model']) && '' !== $kriti_ai_config['model'] ? $kriti_ai_config['model'] : (isset($kriti_ai_models[0]) ? $kriti_ai_models[0] : '');
                  ?>
                  <div>

                    <!-- OpenAI Card -->
                    <div data-kriti-ai-provider="<?php echo esc_attr($kriti_ai_slug); ?>" class="bg-kriti-ai-surface rounded-xl border border-kriti-ai-outline-variant/50 p-6 flex flex-col h-full shadow-[0_4px_12px_rgba(0,0,0,0.02)] hover:shadow-[0_8px_24px_rgba(99,102,241,0.08)] transition-shadow duration-300 relative overflow-hidden group">
                      <div class="absolute top-0 left-0 w-full h-[2px] bg-kriti-ai-primary scale-x-100 origin-left transition-transform duration-300"></div>
                      <div class="flex justify-between items-start">
                        <div class="rounded-lg flex flex-column items-start justify-center">
                          <!-- <span class="material-symbols-outlined text-kriti-ai-on-surface-variant" data-icon="psychology">psychology</span> -->
                          <h3 class="font-kriti-ai-title-md text-kriti-ai-title-md text-kriti-ai-on-surface mb-1"><?php echo esc_html($kriti_ai_slug_label); ?></h3>
                        </div>
                        <span class="kriti-ai-connection-status" data-kriti-ai-status hidden>
                          <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                          <?php esc_html_e('Connected', 'kriti-ai'); ?>
                        </span>
                      </div>
                      <div class="mt-auto kriti-ai-provider-fields flex flex-wrap items-start gap-4">
                        <?php if ($kriti_ai_is_local) : ?>
                          <div class="kriti-ai-field">
                            <label><?php esc_html_e('API URL', 'kriti-ai'); ?></label>
                            <input type="url" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][base_url]" data-kriti-ai-url value="<?php echo esc_attr(isset($kriti_ai_config['base_url']) ? $kriti_ai_config['base_url'] : ''); ?>" placeholder="http://localhost:8080" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
                            <p class="description">
                              <?php esc_html_e('Base URL of your local OpenAI-compatible server (e.g. LocalAI). Requests go to the same /v1 endpoints as OpenAI.', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php endif; ?>

                        <?php if ($kriti_ai_requires_key || $kriti_ai_is_local) : ?>
                          <div class="kriti-ai-field">
                            <label><?php $kriti_ai_is_local ? esc_html_e('API key (optional)', 'kriti-ai') : esc_html_e('API key', 'kriti-ai'); ?></label>
                            <input type="password" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][api_key]" data-kriti-ai-key value="<?php echo esc_attr(isset($kriti_ai_config['api_key']) ? $kriti_ai_config['api_key'] : ''); ?>" class="regular-text  w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" autocomplete="off" />
                            <p class="description">
                              <?php esc_html_e('You can also define the constant ', 'kriti-ai'); ?>
                              <code><?php echo esc_html('KRITI_AI_KEY_' . strtoupper(str_replace('-', '_', $kriti_ai_slug))); ?></code>
                              <?php esc_html_e(' in wp-config.php; it overrides this field.', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php endif; ?>

                        <?php if ('ollama' === $kriti_ai_slug || 'ollama_embedding' === $kriti_ai_slug) : ?>
                          <div class="kriti-ai-field">
                            <label><?php esc_html_e('Server URL', 'kriti-ai'); ?></label>
                            <input type="url" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][base_url]" data-kriti-ai-url value="<?php echo esc_attr(isset($kriti_ai_config['base_url']) ? $kriti_ai_config['base_url'] : ''); ?>" placeholder="http://localhost:11434" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" />
                            <p class="description">
                              <?php echo 'ollama_embedding' === $kriti_ai_slug
                              ? esc_html__('Enter your Ollama server URL (e.g. http://localhost:11434) to enable it. The embedding model must be installed on the server: ollama pull nomic-embed-text', 'kriti-ai')
                              : esc_html__('Enter your Ollama server URL (e.g. http://localhost:11434) to enable it. Please use full model name like qwen3.5:latest', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php elseif ('openrouter' === $kriti_ai_slug) : ?>
                          <div class="kriti-ai-field">
                            <p class="description">
                              <?php esc_html_e('Uses openrouter/auto by default. Pick any OpenRouter model id per generation in AI Studio.', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php elseif ('mock' === $kriti_ai_slug) : ?>
                          <div class="kriti-ai-field">
                            <p class="description">
                              <?php esc_html_e('The mock provider returns placeholder text, image, audio and video so you can test the workflow without an API key. It is offered automatically only when no other provider is set up for that type.', 'kriti-ai'); ?>
                            </p>
                          </div>
                        <?php endif; ?>

                        <?php if (Provider_Manager::has_model_setting($kriti_ai_slug)) : ?>
                          <div class="kriti-ai-field">
                            <label><?php esc_html_e('Model', 'kriti-ai'); ?></label>
                            <input type="text" name="kriti_ai_providers[<?php echo esc_attr($kriti_ai_slug); ?>][model]" list="kriti-ai-models-<?php echo esc_attr($kriti_ai_slug); ?>" placeholder="<?php echo $kriti_ai_is_local ? esc_attr__('Model name on your server', 'kriti-ai') : ''; ?>" class="regular-text w-full py-2 border border-kriti-ai-outline-variant rounded-lg font-kriti-ai-title-md text-kriti-ai-body-md text-kriti-ai-on-surface hover:bg-kriti-ai-surface-container-low transition-colors" value="<?php echo esc_attr($kriti_ai_model_value); ?>" autocomplete="off" />
                            <?php if (Provider_Manager::is_embedding($kriti_ai_slug)) : ?>
                              <p class="description"><?php esc_html_e('Turns text into numbers for meaning search, for example in Kriti AI Chatbot. If you change the model, content that was embedded with the old one is embedded again.', 'kriti-ai'); ?></p>
                            <?php endif; ?>
                            <datalist id="kriti-ai-models-<?php echo esc_attr($kriti_ai_slug); ?>">
                              <?php foreach ($kriti_ai_models as $kriti_ai_model) : ?>
                                <option value="<?php echo esc_attr($kriti_ai_model); ?>"></option>
                              <?php endforeach; ?>
                            </datalist>
                          </div>
                        <?php endif; ?>

                        <div class="kriti-ai-connection-actions">
                          <button type="button" class="button" data-kriti-ai-test="<?php echo esc_attr($kriti_ai_slug); ?>"><?php esc_html_e('Test connection', 'kriti-ai'); ?></button>
                          <span class="kriti-ai-connection-message" data-kriti-ai-message aria-live="polite"></span>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>

              </div>
            </section>
          </div>
        <?php endforeach; ?>

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
