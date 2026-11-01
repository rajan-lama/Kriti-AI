<?php

/**
 * Enqueues admin assets.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Assets
 */
class Assets
{



  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
  }

  /**
   * Enqueue styles and scripts on Kriti AI screens only.
   *
   * @param string $hook Current admin page hook.
   * @return void
   */
  public static function enqueue($hook)
  {
    $screens = array(
      'toplevel_page_kriti-ai',
      'kriti-ai_page_kriti-ai-generate',
      'kriti-ai_page_kriti-ai-queue',
      'kriti-ai_page_kriti-ai-providers',
      'kriti-ai_page_kriti-ai-media',
      'kriti-ai_page_kriti-ai-settings',
    );

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $isKritiAIMediaList = $screen && 'edit-kriti_ai_media' === $screen->id;

    if (! in_array($hook, $screens, true) && ! $isKritiAIMediaList) {
      return;
    }

    $kriti_ai_query_args = array(
      'family' => 'Material+Symbols+Outlined:100,200,300,400,500,600,700,0,1|Inter:400,500,600,700|JetBrains+Mono:400',
    );

    wp_enqueue_style('kriti-ai-google-fonts', add_query_arg($kriti_ai_query_args, '//fonts.googleapis.com/css'), array(), KRITI_AI_VERSION);

    wp_enqueue_style('kriti-ai-admin', KRITI_AI_URL . 'admin/css/admin.css', array(), self::asset_version('admin/css/admin.css'));

    wp_enqueue_style('kriti-ai-tailwind', KRITI_AI_URL . 'admin/css/tailwind.css', array(), self::asset_version('admin/css/tailwind.css'));

    wp_enqueue_script(
      'kriti-ai-admin',
      KRITI_AI_URL . 'admin/js/admin.js',
      array('wp-i18n'),
      self::asset_version('admin/js/admin.js'),
      true
    );

    wp_set_script_translations('kriti-ai-admin', 'kriti-ai', KRITI_AI_DIR . 'languages');

    wp_localize_script(
      'kriti-ai-admin',
      'KritiAI',
      array(
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('kriti_ai_ajax'),
        'defaults' => array(
          'temperature' => Settings::get()['temperature'],
          'max_tokens'  => Settings::get()['max_tokens'],
          'top_p'       => Settings::get()['top_p'],
          'top_k'       => Settings::get()['top_k'],
        ),
        'models'   => self::model_choices(),
      )
    );
  }

  /**
   * Asset version that changes whenever the file does, so browsers never
   * keep a stale copy after an update.
   *
   * @param string $relative_path Path relative to the plugin directory.
   * @return string
   */
  private static function asset_version($relative_path)
  {
    $mtime = @filemtime(KRITI_AI_DIR . $relative_path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Missing file falls back to the plugin version.

    return $mtime ? KRITI_AI_VERSION . '.' . $mtime : KRITI_AI_VERSION;
  }

  /**
   * Suggested models for every registered provider, keyed by slug.
   *
   * @return array
   */
  private static function model_choices()
  {
    $models = array();

    foreach (array('text', 'image', 'audio', 'video') as $capability) {
      foreach (Provider_Manager::slugs_for($capability) as $slug) {
        $models[$slug] = Provider_Manager::model_choices($slug);
      }
    }

    return $models;
  }
}
