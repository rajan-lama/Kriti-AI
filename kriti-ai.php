<?php

/**
 * Plugin Name:       Kriti AI
 * Plugin URI:        https://wordpress.org/plugins/kriti-ai/
 * Description:       Connect multiple AI providers (OpenAI, Google Gemini, Ollama) to generate content, audio and video inside WordPress. Generated items stay as drafts until published.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Kriti AI
 * Author URI:        https://kritiai.net
 * Contributors:      lamarajan
 * Tags:              ai, content generation, text generation, audio generation, video generation
 * Network:           true
 * GitHub Plugin URI: https://github.com/rajan-lama/kriti-ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kriti-ai
 * Domain Path:       /languages
 *
 * @package KritiAI
 */

if (! defined('ABSPATH')) {
  exit;
}

define('KRITI_AI_VERSION', '1.0.2');
define('KRITI_AI_FILE', __FILE__);
define('KRITI_AI_DIR', plugin_dir_path(__FILE__));
define('KRITI_AI_URL', plugin_dir_url(__FILE__));
define('KRITI_AI_BASENAME', plugin_basename(__FILE__));
define('KRITI_AI_DB_VERSION', '1.0.2');

require_once KRITI_AI_DIR . 'includes/autoload.php';

register_activation_hook(__FILE__, array('KritiAI\\Install', 'activate'));
register_deactivation_hook(__FILE__, array('KritiAI\\Install', 'deactivate'));

/**
 * Boot the plugin after all plugins are loaded.
 *
 * @return void
 */
function kriti_ai_boot()
{
  $plugin = KritiAI\Plugin::instance();
  $plugin->register();
}

add_action('plugins_loaded', 'kriti_ai_boot');

/**
 * Provide Kriti AI Writer's platform contract, bridging it to the local
 * providers configured here (kriti_ai_providers option).
 *
 * @param mixed $platform Existing filtered value.
 * @return mixed
 */
function kriti_ai_writer_platform_bridge($platform)
{
  if ($platform || ! interface_exists('KritiAI\\Writer\\Platform\\PlatformContract')) {
    return $platform;
  }

  require_once KRITI_AI_DIR . 'includes/class-writer-bridge.php';

  return new KritiAI\Writer_Bridge();
}

add_filter('kriti_ai_writer_platform', 'kriti_ai_writer_platform_bridge');
