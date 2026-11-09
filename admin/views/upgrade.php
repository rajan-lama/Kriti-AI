<?php

/**
 * Upgrade and support view.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}

$kriti_ai_products = array(
  array(
    'icon'     => 'edit_note',
    'title'    => __('Kriti AI Pro', 'kriti-ai'),
    'text'     => __('An AI-native editorial workspace: rewrite, expand and optimise content right inside the editor.', 'kriti-ai'),
    'features' => array(
      __('AI editing inside the block editor', 'kriti-ai'),
      __('SEO, tone and readability tools', 'kriti-ai'),
      __('Priority updates', 'kriti-ai'),
    ),
    'url'      => 'https://kritiai.net/products/kriti-ai-pro',
  ),
  array(
    'icon'     => 'smart_toy',
    'title'    => __('Kriti AI Copilot', 'kriti-ai'),
    'text'     => __('A supervised copilot that creates pages, edits content and builds whole websites, with plan approval, audit and rollback.', 'kriti-ai'),
    'features' => array(
      __('Chat to create and edit pages', 'kriti-ai'),
      __('Plan approval and full audit log', 'kriti-ai'),
      __('One-click rollback', 'kriti-ai'),
    ),
    'url'      => 'https://kritiai.net/products/kriti-ai-copilot',
  ),
  array(
    'icon'     => 'design_services',
    'title'    => __('Kriti AI Designer', 'kriti-ai'),
    'text'     => __('Build Gutenberg pages and sections from a prompt using a library of professional patterns.', 'kriti-ai'),
    'features' => array(
      __('Pattern Library first', 'kriti-ai'),
      __('Content bound to your brand voice', 'kriti-ai'),
      __('Generates AI images for sections', 'kriti-ai'),
    ),
    'url'      => 'https://kritiai.net/products/kriti-ai-designer',
  ),
);

$kriti_ai_support = array(
  array(
    'icon'  => 'menu_book',
    'title' => __('Documentation', 'kriti-ai'),
    'text'  => __('Guides for providers, prompts, the queue and settings.', 'kriti-ai'),
    'label' => __('Read the docs', 'kriti-ai'),
    'url'   => 'https://kritiai.net/documentation',
  ),
  array(
    'icon'  => 'support_agent',
    'title' => __('Contact support', 'kriti-ai'),
    'text'  => __('Stuck or found a bug? Send us a message and include the details below.', 'kriti-ai'),
    'label' => __('Contact us', 'kriti-ai'),
    'url'   => 'https://kritiai.net/contact',
  ),
  array(
    'icon'  => 'forum',
    'title' => __('Community forum', 'kriti-ai'),
    'text'  => __('Ask questions and request features on the WordPress.org support forum.', 'kriti-ai'),
    'label' => __('Open the forum', 'kriti-ai'),
    'url'   => 'https://wordpress.org/support/plugin/kriti-ai/',
  ),
);

global $wp_version;
$kriti_ai_system_info = sprintf(
  "Kriti AI: %s\nWordPress: %s\nPHP: %s\nSite: %s",
  KRITI_AI_VERSION,
  $wp_version,
  PHP_VERSION,
  home_url()
);
?>
<div class="wrap">
  <?php include 'header.php'; ?>

  <main class="pt-4 w-full">
    <div class="mb-8">
      <h1 class="font-kriti-ai-display-lg text-kriti-ai-display-lg text-kriti-ai-on-background mb-4"><?php esc_html_e('Upgrade Kriti AI', 'kriti-ai'); ?></h1>
      <p class="font-kriti-ai-body-lg text-kriti-ai-body-lg text-kriti-ai-slate-gray max-w-2xl"><?php esc_html_e('Kriti AI is the foundation. Add a companion plugin to build pages, edit content and design whole sites with AI.', 'kriti-ai'); ?></p>
    </div>

    <section class="kriti-ai-upsell-grid">
      <?php foreach ($kriti_ai_products as $kriti_ai_product) : ?>
        <div class="kriti-ai-panel kriti-ai-upsell-card">
          <span class="material-symbols-outlined text-kriti-ai-primary" aria-hidden="true"><?php echo esc_html($kriti_ai_product['icon']); ?></span>
          <h2><?php echo esc_html($kriti_ai_product['title']); ?></h2>
          <p><?php echo esc_html($kriti_ai_product['text']); ?></p>
          <ul>
            <?php foreach ($kriti_ai_product['features'] as $kriti_ai_feature) : ?>
              <li><?php echo esc_html($kriti_ai_feature); ?></li>
            <?php endforeach; ?>
          </ul>
          <a class="button button-primary" href="<?php echo esc_url($kriti_ai_product['url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Learn more', 'kriti-ai'); ?></a>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="kriti-ai-panel">
      <h2><?php esc_html_e('Need several plugins?', 'kriti-ai'); ?></h2>
      <p><?php esc_html_e('The Kriti AI Bundle gives you one licence for every Kriti AI plugin.', 'kriti-ai'); ?></p>
      <a class="button" href="https://kritiai.net/products" target="_blank" rel="noopener noreferrer"><?php esc_html_e('See plans and pricing', 'kriti-ai'); ?></a>
    </div>

    <div class="mt-8 mb-4">
      <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface"><?php esc_html_e('Support', 'kriti-ai'); ?></h2>
    </div>

    <section class="kriti-ai-upsell-grid">
      <?php foreach ($kriti_ai_support as $kriti_ai_item) : ?>
        <div class="kriti-ai-panel kriti-ai-upsell-card">
          <span class="material-symbols-outlined text-kriti-ai-primary" aria-hidden="true"><?php echo esc_html($kriti_ai_item['icon']); ?></span>
          <h2><?php echo esc_html($kriti_ai_item['title']); ?></h2>
          <p><?php echo esc_html($kriti_ai_item['text']); ?></p>
          <a class="button" href="<?php echo esc_url($kriti_ai_item['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($kriti_ai_item['label']); ?></a>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="kriti-ai-panel">
      <h2><?php esc_html_e('System information', 'kriti-ai'); ?></h2>
      <p><?php esc_html_e('Copy this into your support request. It contains no API keys.', 'kriti-ai'); ?></p>
      <textarea class="large-text code" rows="5" readonly onfocus="this.select();"><?php echo esc_textarea($kriti_ai_system_info); ?></textarea>
    </div>
  </main>

  <?php include 'footer.php'; ?>
</div>
