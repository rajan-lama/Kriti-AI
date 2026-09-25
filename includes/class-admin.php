<?php

/**
 * Admin menu and page rendering.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Admin
 */
class Admin
{



  /**
   * The prompt library post type.
   *
   * @var string
   */
  const PROMPT = 'kriti_ai_prompt';

  /**
   * The article post type.
   *
   * @var string
   */
  const ARTICLE = 'kriti_ai_article';

  /**
   * The media post type.
   *
   * @var string
   */
  const MEDIA = 'kriti_ai_media';


  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action('admin_menu', array(__CLASS__, 'menus'));
    add_action('admin_notices', array(__CLASS__, 'notices'));
    add_action('admin_menu', array(__CLASS__, 'reorderKritiAIMenu'), 999);
  }

  public static function reorderKritiAIMenu()
  {
    global $submenu;

    if (!isset($submenu['kriti-ai'])) {
      return;
    }

    $menu = $submenu['kriti-ai'];

    $dashboard = array();
    $postType = array();
    $others = array();

    foreach ($menu as $item) {
      // Dashboard
      if ('kriti-ai' === $item[2]) {
        $dashboard[] = $item;
        continue;
      }

      if (0 === strpos($item[2], 'edit.php?post_type=')) {
        // Custom post type submenus.
        $postType[] = $item;
        continue;
      }

      // Everything else.
      $others[] = $item;
    }

    // Dashboard first, plugin pages in the middle,
    // custom post type submenus at the bottom.
    $submenu['kriti-ai'] = array_merge( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering existing admin submenu entries.
      $dashboard,
      $others,
      $postType
    );
  }

  /**
   * Register the top level menu and submenus.
   *
   * @return void
   */
  public static function menus()
  {
    add_menu_page(
      __('Kriti AI', 'kriti-ai'),
      __('Kriti AI', 'kriti-ai'),
      'manage_options',
      'kriti-ai',
      array(__CLASS__, 'pageDashboard'),
      'dashicons-superhero',
    );

    add_submenu_page(
      'kriti-ai',
      __('Dashboard', 'kriti-ai'),
      __('Dashboard', 'kriti-ai'),
      'manage_options',
      'kriti-ai',
      array(__CLASS__, 'pageDashboard'),
      1
    );

    add_submenu_page(
      'kriti-ai',
      __('AI Studio', 'kriti-ai'),
      __('AI Studio', 'kriti-ai'),
      'manage_options',
      'kriti-ai-generate',
      array(__CLASS__, 'pageGenerate')
    );

    add_submenu_page(
      'kriti-ai',
      __('Queue', 'kriti-ai'),
      __('Queue', 'kriti-ai'),
      'manage_options',
      'kriti-ai-queue',
      array(__CLASS__, 'pageQueue')
    );

    add_submenu_page(
      'kriti-ai',
      __('MCP Server', 'kriti-ai'),
      __('MCP Server', 'kriti-ai'),
      'manage_options',
      'kriti-ai-providers',
      array(__CLASS__, 'pageProviders')
    );
  }

  /**
   * Render the dashboard page.
   *
   * @return void
   */
  public static function pageDashboard()
  {
    self::renderView('dashboard');
  }

  /**
   * Render the generate page.
   *
   * @return void
   */
  public static function pageGenerate()
  {
    self::renderView('generate');
  }

  /**
   * Render the queue page.
   *
   * @return void
   */
  public static function pageQueue()
  {
    self::renderView('queue');
  }

  /**
   * Render the settings page.
   *
   * @return void
   */
  public static function pageSettings()
  {
    self::renderView('settings');
  }

  /**
   * Render the providers page.
   *
   * @return void
   */
  public static function pageProviders()
  {
    self::renderView('providers');
  }

  /**
   * Render an admin view.
   *
   * @param string $view View slug.
   * @return void
   */
  private static function renderView($view)
  {
    $file = KRITI_AI_DIR . 'admin/views/' . $view . '.php';

    if (!file_exists($file)) {
      echo '<div class="notice notice-error"><p>' . esc_html__('Missing admin view.', 'kriti-ai') . '</p></div>';
      return;
    }

    include $file;
  }

  /**
   * Print admin notices.
   *
   * @return void
   */
  public static function notices()
  {
    $screen = get_current_screen();

    if (!$screen || false === strpos((string) $screen->id, 'kriti-ai')) {
      return;
    }

    $updated = filter_input(INPUT_GET, 'updated', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ('1' === $updated) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Kriti AI settings saved.', 'kriti-ai') . '</p></div>';
    }

    $textEnabled = Provider_Manager::enabled_slugs('text');
    if (empty($textEnabled)) {
      echo '<div class="notice notice-warning"><p>' . esc_html__('Kriti AI: no text providers are enabled. Open Settings to enable at least one provider.', 'kriti-ai') . '</p></div>';
    }
  }
}
