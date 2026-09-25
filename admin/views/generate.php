<?php

/**
 * Generate view.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}

$kriti_ai_settings = Settings::get();
$kriti_ai_top_p    = isset($kriti_ai_settings['top_p']) ? (float) $kriti_ai_settings['top_p'] : 1.0;

$kriti_ai_text_enabled  = Provider_Manager::enabled_slugs('text');
$kriti_ai_image_enabled = Provider_Manager::enabled_slugs('image');
$kriti_ai_audio_enabled = Provider_Manager::enabled_slugs('audio');
$kriti_ai_video_enabled = Provider_Manager::enabled_slugs('video');

$kriti_ai_prompts = get_posts(
  array(
    'post_type'      => Post_Types::PROMPT,
    'post_status'    => 'any',
    'posts_per_page' => 100,
    'orderby'        => 'title',
    'order'          => 'ASC',
  )
);

/**
 * Render a provider select.
 *
 * @param string[] $kriti_ai_slugs Enabled provider slugs.
 * @param string   $kriti_ai_defaultSlug Default slug.
 * @param string   $kriti_ai_name Select name.
 */


function kriti_ai_render_provider_select($kriti_ai_slugs, $kriti_ai_defaultSlug, $kriti_ai_name)
{
  echo '<select name="' . esc_attr($kriti_ai_name) . '" class="kriti-ai-provider-select w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 bg-slate-50" data-select="' . esc_attr($kriti_ai_name) . '">';
  foreach ($kriti_ai_slugs as $kriti_ai_slug) {
    printf(
      '<option value="%1$s"%3$s>%2$s</option>',
      esc_attr($kriti_ai_slug),
      esc_html(ucwords(str_replace('_', ' ', $kriti_ai_slug))),
      selected($kriti_ai_slug, $kriti_ai_defaultSlug, false)
    );
  }
  echo '</select>';
}

?>
<div class="wrap">
  <?php include 'header.php'; ?>
  <!-- Page Header -->
  <div class="mb-6 flex justify-between items-end">
    <div>
      <h1 class="text-2xl font-semibold text-slate-800 mb-1"><?php esc_html_e('Create Content', 'kriti-ai'); ?></h1>
      <p class="text-slate-500 text-sm"><?php esc_html_e('Configure your parameters and prompt the AI to generate high-fidelity content.', 'kriti-ai'); ?></p>
    </div>
  </div>

  <!-- Main Layout Grid -->
  <main>
    <form id="kriti-ai-generate-form" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left Column: Primary Inputs -->
      <div class="lg:col-span-8 space-y-6">
        <!-- Content Type Selection (Redesigned as Segmented Cards) -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-4" data-purpose="content-type-selector">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
            <?php esc_html_e('Content Type', 'kriti-ai'); ?>
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

            <!-- Content Type Options -->

            <!-- Article -->
            <label class="relative cursor-pointer group">
              <input checked class="peer sr-only" name="content_type" type="radio" value="article" />
              <div class="flex flex-col items-center gap-2 p-3 rounded-lg border border-kriti-ai-outline-variant bg-kriti-ai-surface hover:bg-kriti-ai-surface-container-low transition-colors peer-checked:border-kriti-ai-primary peer-checked:bg-kriti-ai-indigo-wash peer-checked:shadow-[0_0_0_1px_#6366F1_inset]">
                <span class="material-symbols-outlined text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors" style="font-variation-settings: 'FILL' 0;">
                  <?php esc_html_e('article', 'kriti-ai'); ?>
                </span>

                <span class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors">
                  <?php esc_html_e('Article', 'kriti-ai'); ?>
                </span>

              </div>
            </label>


            <!-- Image -->
            <label class="relative cursor-pointer group">
              <input class="peer sr-only" name="content_type" type="radio" value="image" />

              <div class="flex flex-col items-center gap-2 p-3 rounded-lg border border-kriti-ai-outline-variant bg-kriti-ai-surface hover:bg-kriti-ai-surface-container-low transition-colors peer-checked:border-kriti-ai-primary peer-checked:bg-kriti-ai-indigo-wash peer-checked:shadow-[0_0_0_1px_#6366F1_inset]">
                <span class="material-symbols-outlined text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors" style="font-variation-settings: 'FILL' 0;">
                  <?php esc_html_e('image', 'kriti-ai'); ?>
                </span>

                <span class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors">
                  <?php esc_html_e('Image', 'kriti-ai'); ?>
                </span>

              </div>
            </label>


            <!-- Audio -->
            <label class="relative cursor-pointer group">
              <input class="peer sr-only" name="content_type" type="radio" value="audio" />

              <div class="flex flex-col items-center gap-2 p-3 rounded-lg border border-kriti-ai-outline-variant bg-kriti-ai-surface hover:bg-kriti-ai-surface-container-low transition-colors peer-checked:border-kriti-ai-primary peer-checked:bg-kriti-ai-indigo-wash peer-checked:shadow-[0_0_0_1px_#6366F1_inset]">
                <span class="material-symbols-outlined text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors" style="font-variation-settings: 'FILL' 0;">
                  <?php esc_html_e('audio_file', 'kriti-ai'); ?>
                </span>

                <span class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors">
                  <?php esc_html_e('Audio', 'kriti-ai'); ?>
                </span>

              </div>
            </label>


            <!-- Video -->
            <label class="relative cursor-pointer group">
              <input class="peer sr-only" name="content_type" type="radio" value="video" />

              <div class="flex flex-col items-center gap-2 p-3 rounded-lg border border-kriti-ai-outline-variant bg-kriti-ai-surface hover:bg-kriti-ai-surface-container-low transition-colors peer-checked:border-kriti-ai-primary peer-checked:bg-kriti-ai-indigo-wash peer-checked:shadow-[0_0_0_1px_#6366F1_inset]">
                <span class="material-symbols-outlined text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors" style="font-variation-settings: 'FILL' 0;">
                  <?php esc_html_e('movie', 'kriti-ai'); ?>
                </span>

                <span class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-on-surface-variant peer-checked:text-kriti-ai-primary transition-colors">
                  <?php esc_html_e('Video', 'kriti-ai'); ?>
                </span>

              </div>
            </label>

          </div>
        </section>

        <!-- Main Prompt Area -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden" data-purpose="prompt-editor">
          <!-- Header & Meta Inputs -->
          <div class="border-b border-slate-100 p-5 bg-slate-50/50">
            <div class="grid grid-cols-1 sm:grid-cols-1 gap-4 mb-4">
              <!-- Title Input -->
              <!-- <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5" for="kriti-ai-title"><?php esc_html_e('Prompt Title', 'kriti-ai'); ?></label>
                <input class="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-800 placeholder-slate-400" id="kriti-ai-title" placeholder="<?php echo esc_attr__('e.g. SEO Blog Post about AI', 'kriti-ai'); ?>" type="text" name="title">
              </div> -->
              <!-- Library Select -->
              <!-- <div> -->
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5" for="kriti-ai-prompt-select"><?php esc_html_e('Load Template', 'kriti-ai'); ?></label>
              <select id="kriti-ai-prompt-select" class="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 bg-white">
                <option value=""><?php esc_html_e('— Select a prompt —', 'kriti-ai'); ?></option>
                <?php foreach ($kriti_ai_prompts as $kriti_ai_prompt) : ?>
                  <option value="<?php echo esc_attr($kriti_ai_prompt->ID); ?>">
                    <?php echo esc_html(get_the_title($kriti_ai_prompt)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <!-- </div> -->
            </div>
          </div>

          <!-- Main Article Prompt -->
          <div class="p-5 bg-indigo-50/30">
            <div class="flex justify-between items-center mb-4">
              <div>
                <h3 id="kriti-ai-prompt-canvas-title" class="text-lg font-semibold text-slate-800"><?php esc_html_e('Article Prompt', 'kriti-ai'); ?></h3>
                <p id="kriti-ai-prompt-canvas-help" class="text-sm text-slate-500"><?php esc_html_e('Describe the article topic, target reader, structure, and tone.', 'kriti-ai'); ?></p>
              </div>
            </div>

            <!-- Prompt Input Areas -->
            <div data-gen-for="content" style="margin-bottom:0;">
              <label for="kriti-ai-prompt" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?php esc_html_e('Article instructions', 'kriti-ai'); ?></label>
              <textarea name="prompt" id="kriti-ai-prompt" rows="8" class="w-full rounded-md border-slate-300 shadow-inner focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 placeholder-slate-400 resize-y mb-2" placeholder="<?php echo esc_attr__('Write a long-form article about [topic]. Include an SEO title, intro, 4-6 sections with headings, key takeaways, and a concise conclusion.', 'kriti-ai'); ?>"></textarea>
              <p class="description text-xs text-slate-500 mb-6"><?php esc_html_e('Tip: include audience, length, tone, and required keywords for better outputs.', 'kriti-ai'); ?></p>
            </div>

            <!-- Image Prompt -->
            <div data-gen-for="image" hidden style="margin-bottom:0;">
              <label for="kriti-ai-image-prompt" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?php esc_html_e('Image prompt', 'kriti-ai'); ?></label>
              <textarea name="image_prompt" id="kriti-ai-image-prompt" rows="8" class="w-full rounded-md border-slate-300 shadow-inner focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 placeholder-slate-400 resize-y mb-2" placeholder="<?php echo esc_attr__('A stunning studio photograph of [subject], vibrant colors, ultra-detailed 8k resolution, cinematic lighting.', 'kriti-ai'); ?>"></textarea>
              <p class="description mt-2"><?php esc_html_e('Tip: specify subject, lighting, style, colors, and camera perspective.', 'kriti-ai'); ?></p>
            </div>

            <!-- Video Prompt -->
            <div data-gen-for="video" hidden style="margin-bottom:0;">
              <label for="kriti-ai-video-prompt" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?php esc_html_e('Video brief', 'kriti-ai'); ?></label>
              <textarea name="video_prompt" id="kriti-ai-video-prompt" rows="8" class="w-full rounded-md border-slate-300 shadow-inner focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 placeholder-slate-400 resize-y mb-2" placeholder="<?php echo esc_attr__('Create a cinematic video about [topic]. Define scene sequence, camera style, pacing, lighting mood, and end frame message.', 'kriti-ai'); ?>"></textarea>
              <p class="description mt-2"><?php esc_html_e('Tip: specify visual style, camera motion, and setting for more accurate shots.', 'kriti-ai'); ?></p>
            </div>

            <!-- Audio Prompt -->
            <div data-gen-for="audio" hidden style="margin-bottom:0;">
              <label for="kriti-ai-text" class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2"><?php esc_html_e('Audio script', 'kriti-ai'); ?></label>
              <div class="flex items-center gap-2 rounded-lg bg-slate-100 border border-slate-300 px-3 py-2 text-slate-700 mb-2">
                <span class="material-symbols-outlined text-indigo-600 text-base" data-icon="graphic_eq"><?php esc_html_e('graphic_eq', 'kriti-ai'); ?></span>
                <span class="text-sm"><?php esc_html_e('Write as spoken language with pauses and emphasis cues.', 'kriti-ai'); ?></span>
              </div>
              <textarea name="text" id="kriti-ai-text" rows="8" class="w-full rounded-md border-slate-300 shadow-inner focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 placeholder-slate-400 resize-y mb-2" placeholder="<?php echo esc_attr__('Write a natural voice-over script for [topic] with clear pauses, emphasis notes, and a strong closing call to action.', 'kriti-ai'); ?>"></textarea>
              <p class="text-xs text-slate-500 mb-6"><?php esc_html_e('Tip: keep sentences short and spoken-language friendly for cleaner narration.', 'kriti-ai'); ?></p>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-end pt-4 border-t border-slate-200">
              <button class="inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm" type="submit" id="kriti-ai-generate-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                </svg>
                <span id="kriti-ai-generate-btn-label"><?php esc_html_e('Generate Content', 'kriti-ai'); ?></span>
              </button>
            </div>
          </div>
        </section>

        <div class="mb-6 bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden" id="kriti-ai-result-panel" hidden>
          <div class="p-4 block sm:items-center justify-between gap-4" id="kriti-ai-result"></div>
        </div>
      </div>
      <!-- End Left Column -->
      <!-- Right Column: Settings & History -->
      <div class="lg:col-span-4 space-y-6">
        <!-- Engine Settings -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-4" data-purpose="ai-engine-settings">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2"><?php esc_html_e('AI Engine', 'kriti-ai'); ?></h2>

          <!-- AI content provider -->
          <div class="space-y-4">
            <div class="kriti-ai-field">
              <label class="block text-sm font-medium text-slate-700 mb-1"><?php esc_html_e('Provider', 'kriti-ai'); ?></label>
              <div data-gen-for="content"><?php kriti_ai_render_provider_select($kriti_ai_text_enabled, $kriti_ai_settings['default_text_provider'], 'provider'); ?></div>
              <div data-gen-for="image" hidden><?php kriti_ai_render_provider_select($kriti_ai_image_enabled, $kriti_ai_settings['default_image_provider'], 'provider'); ?></div>
              <div data-gen-for="audio" hidden><?php kriti_ai_render_provider_select($kriti_ai_audio_enabled, $kriti_ai_settings['default_audio_provider'], 'provider'); ?></div>
              <div data-gen-for="video" hidden><?php kriti_ai_render_provider_select($kriti_ai_video_enabled, $kriti_ai_settings['default_video_provider'], 'provider'); ?></div>
            </div>

            <div class="kriti-ai-field">
              <label class="block text-sm font-medium text-slate-700 mb-1" for="kriti-ai-model"><?php esc_html_e('Model', 'kriti-ai'); ?></label>
              <select name="model" id="kriti-ai-model" class="w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-slate-700 bg-slate-50 mb-2">
                <option value=""><?php esc_html_e('Provider default', 'kriti-ai'); ?></option>
              </select>
              <div class="mt-2 flex items-center justify-between gap-2">
                <button type="button" id="kriti-ai-use-provider-default" class="w-full py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded hover:bg-slate-50 transition-colors"><?php esc_html_e('Use provider default', 'kriti-ai'); ?></button>
              </div>
              <p class="text-xs text-slate-500 mt-2"><span id="kriti-ai-model-source"><?php esc_html_e('Provider default', 'kriti-ai'); ?></span> · <span id="kriti-ai-model-help"><?php esc_html_e('Leave empty to use the provider default.', 'kriti-ai'); ?></span></p>
            </div>
          </div>

        </section>
        <!-- Generation Parameters -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-4" data-purpose="generation-parameters">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2 flex justify-between items-center">
            <?php esc_html_e('Parameters', 'kriti-ai'); ?>
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
          </h2>
          <div class="space-y-6" data-gen-for="content">
            <!-- Temperature -->
            <div>
              <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-slate-700" for="kriti-ai-temperature"><?php esc_html_e('Temperature', 'kriti-ai'); ?></label>
              </div>
              <input class="w-full rounded-md border-slate-200 py-1 px-2 text-right text-xs text-indigo-600 bg-indigo-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mt-2" name="temperature" id="kriti-ai-temperature" max="2" min="0" step="0.1" type="number" value="<?php echo esc_attr($kriti_ai_settings['temperature']); ?>">
              <p class=" text-xs text-slate-500 mt-2 leading-tight"><?php esc_html_e('Controls randomness. Higher values produce more creative responses.', 'kriti-ai'); ?></p>
            </div>
            <!-- Max Tokens -->
            <div>
              <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-slate-700" for="kriti-ai-max-tokens"><?php esc_html_e('Max Tokens', 'kriti-ai'); ?></label>
              </div>
              <input class="w-full rounded-md border-slate-200 py-1 px-2 text-right text-xs text-indigo-600 bg-indigo-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mt-2" type="number" name="max_tokens" id="kriti-ai-max-tokens" min="64" step="1" value="<?php echo esc_attr(max(64, (int) $kriti_ai_settings['max_tokens'])); ?>">
              <p class="text-xs text-slate-500 mt-2 leading-tight"><?php esc_html_e('Maximum response length for text generation.', 'kriti-ai'); ?></p>

            </div>
            <!-- Top P -->
            <div>
              <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-slate-700" for="kriti-ai-top-p"><?php esc_html_e('Top P', 'kriti-ai'); ?></label>
              </div>
              <input class="w-full rounded-md border-slate-200 py-1 px-2 text-right text-xs text-indigo-600 bg-indigo-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mt-2" type="number" name="top_p" id="kriti-ai-top-p" min="0" max="1" step="0.01" value="<?php echo esc_attr($kriti_ai_top_p); ?>">
              <p class="text-xs text-slate-500 mt-2 leading-tight"><?php esc_html_e('Nucleus sampling threshold. Lower values make output more focused.', 'kriti-ai'); ?></p>
            </div>
          </div>

          <!-- Parameters for image generation -->
          <div data-gen-for="image" hidden class="flex flex-col gap-3">
            <div class="grid grid-cols-1 gap-3">
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-img-resolution"><?php esc_html_e('Resolution', 'kriti-ai'); ?></label>
                <select name="resolution" id="kriti-ai-img-resolution" class="kriti-ai-param-input">
                  <option value="1024x1024" selected><?php esc_html_e('1024x1024', 'kriti-ai'); ?></option>
                  <option value="1024x1792"><?php esc_html_e('1024x1792', 'kriti-ai'); ?></option>
                  <option value="1792x1024"><?php esc_html_e('1792x1024', 'kriti-ai'); ?></option>
                  <option value="512x512"><?php esc_html_e('512x512', 'kriti-ai'); ?></option>
                </select>
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-img-aspect"><?php esc_html_e('Aspect Ratio', 'kriti-ai'); ?></label>
                <select name="aspect_ratio" id="kriti-ai-img-aspect" class="kriti-ai-param-input">
                  <option value="1:1" selected><?php esc_html_e('1:1 (Square)', 'kriti-ai'); ?></option>
                  <option value="16:9"><?php esc_html_e('16:9 (Widescreen)', 'kriti-ai'); ?></option>
                  <option value="9:16"><?php esc_html_e('9:16 (Portrait)', 'kriti-ai'); ?></option>
                </select>
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-img-style"><?php esc_html_e('Style', 'kriti-ai'); ?></label>
                <select name="style" id="kriti-ai-img-style" class="kriti-ai-param-input">
                  <option value="vivid" selected><?php esc_html_e('Vivid', 'kriti-ai'); ?></option>
                  <option value="natural"><?php esc_html_e('Natural', 'kriti-ai'); ?></option>
                </select>
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-img-quality"><?php esc_html_e('Quality', 'kriti-ai'); ?></label>
                <select name="quality" id="kriti-ai-img-quality" class="kriti-ai-param-input">
                  <option value="standard" selected><?php esc_html_e('Standard', 'kriti-ai'); ?></option>
                  <option value="hd"><?php esc_html_e('HD', 'kriti-ai'); ?></option>
                </select>
              </div>
            </div>
          </div>

          <!-- Parameters for audio generation -->
          <div data-gen-for="audio" hidden class="flex flex-col gap-3">
            <div class="grid grid-cols-1 gap-3">
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-voice"><?php esc_html_e('Voice', 'kriti-ai'); ?></label>
                <input type="text" name="voice" id="kriti-ai-voice" class="kriti-ai-param-input" value="" placeholder="<?php echo esc_attr__('alloy', 'kriti-ai'); ?>" />
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-speed"><?php esc_html_e('Speed', 'kriti-ai'); ?></label>
                <input type="number" name="speed" id="kriti-ai-speed" min="0.25" max="4" step="0.25" value="1.0" class="kriti-ai-param-input" />
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-stability"><?php esc_html_e('Stability', 'kriti-ai'); ?></label>
                <input type="number" name="stability" id="kriti-ai-stability" min="0" max="1" step="0.1" value="0.5" class="kriti-ai-param-input" />
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-similarity"><?php esc_html_e('Similarity boost', 'kriti-ai'); ?></label>
                <input type="number" name="similarity_boost" id="kriti-ai-similarity" min="0" max="1" step="0.1" value="0.75" class="kriti-ai-param-input" />
              </div>
            </div>
          </div>

          <!-- Parameters for video generation -->
          <div data-gen-for="video" hidden class="flex flex-col gap-3">
            <div class="grid grid-cols-1 gap-3">
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-duration"><?php esc_html_e('Duration (seconds)', 'kriti-ai'); ?></label>
                <input type="number" name="duration" id="kriti-ai-duration" min="5" max="60" step="1" value="8" class="kriti-ai-param-input" />
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-resolution"><?php esc_html_e('Resolution', 'kriti-ai'); ?></label>
                <select name="resolution" id="kriti-ai-resolution" class="kriti-ai-param-input">
                  <option value="720p"><?php esc_html_e('720p', 'kriti-ai'); ?></option>
                  <option value="1080p"><?php esc_html_e('1080p', 'kriti-ai'); ?></option>
                  <option value="1280x720" selected><?php esc_html_e('1280x720', 'kriti-ai'); ?></option>
                  <option value="1920x1080"><?php esc_html_e('1920x1080', 'kriti-ai'); ?></option>
                </select>
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-aspect"><?php esc_html_e('Aspect ratio', 'kriti-ai'); ?></label>
                <select name="aspect_ratio" id="kriti-ai-aspect" class="kriti-ai-param-input">
                  <option value="16:9"><?php esc_html_e('16:9', 'kriti-ai'); ?></option>
                  <option value="9:16"><?php esc_html_e('9:16', 'kriti-ai'); ?></option>
                  <option value="1:1"><?php esc_html_e('1:1', 'kriti-ai'); ?></option>
                </select>
              </div>
              <div class="kriti-ai-field kriti-ai-param-field kriti-ai-param-card">
                <label for="kriti-ai-quality"><?php esc_html_e('Quality', 'kriti-ai'); ?></label>
                <select name="quality" id="kriti-ai-quality" class="kriti-ai-param-input">
                  <option value="low"><?php esc_html_e('Low', 'kriti-ai'); ?></option>
                  <option value="medium" selected><?php esc_html_e('Medium', 'kriti-ai'); ?></option>
                  <option value="high"><?php esc_html_e('High', 'kriti-ai'); ?></option>
                </select>
              </div>
            </div>
          </div>
        </section>
      </div>
      <!-- End Right Column -->
    </form>
    <input type="hidden" name="type" value="content" id="kriti-ai-gen-type" form="kriti-ai-generate-form" />
  </main>

  <?php if (empty($kriti_ai_text_enabled) && empty($kriti_ai_image_enabled) && empty($kriti_ai_audio_enabled) && empty($kriti_ai_video_enabled)) : ?>
    <div class="notice notice-error mt-4">
      <p>
        <?php esc_html_e('No AI providers are enabled. Enable at least one provider in Settings to start generating.', 'kriti-ai'); ?>
      </p>
    </div>
  <?php endif; ?>
  <!-- End Main Layout Grid -->
  <?php include 'footer.php'; ?>
</div>