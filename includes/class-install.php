<?php

/**
 * Activation, deactivation and upgrade routines.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Install
 */
class Install
{

  /**
   * Run on plugin activation.
   *
   * @return void
   */
  public static function activate()
  {
    self::maybeUpgrade();
    self::scheduleCron();
  }

  /**
   * Run on plugin deactivation.
   *
   * @return void
   */
  public static function deactivate()
  {
    wp_clear_scheduled_hook('kriti_ai_process_queue');
  }

  /**
   * Create/upgrade database tables and seed default options.
   *
   * @return void
   */
  public static function maybeUpgrade()
  {
    $installed = get_option('KRITI_AI_DB_version', '0');

    if (version_compare($installed, KRITI_AI_DB_VERSION, '>=')) {
      return;
    }

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charsetCollate = $wpdb->get_charset_collate();

    $jobsTable = $wpdb->prefix . 'kriti_ai_jobs';
    $sql       = "CREATE TABLE {$jobsTable} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_type VARCHAR(20) NOT NULL DEFAULT 'text',
			provider VARCHAR(40) NOT NULL DEFAULT '',
			model VARCHAR(120) NOT NULL DEFAULT '',
			params LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			progress TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			result LONGTEXT NULL,
			error TEXT NULL,
			attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			poll_after DATETIME NULL,
			scheduled_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charsetCollate};";

    dbDelta($sql);

    $metricsTable = $wpdb->prefix . 'kriti_ai_metrics';
    $sql          = "CREATE TABLE {$metricsTable} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			item_type VARCHAR(20) NOT NULL DEFAULT 'text',
			provider VARCHAR(40) NOT NULL DEFAULT '',
			model VARCHAR(120) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			tokens_in BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			tokens_out BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			cost DECIMAL(12,6) NOT NULL DEFAULT 0,
			duration_ms INT(11) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id),
			KEY provider (provider),
			KEY created_at (created_at)
		) {$charsetCollate};";

    dbDelta($sql);

    self::seedOptions();

    update_option('KRITI_AI_DB_version', KRITI_AI_DB_VERSION);
  }

  /**
   * Seed default options on first activation.
   *
   * @return void
   */
  private static function seedOptions()
  {
    if (false === get_option('kriti_ai_providers', false)) {
      add_option(
        'kriti_ai_providers',
        array(
          'openai'       => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'gpt-4o-mini',
            'base_url' => '',
          ),
          'gemini'       => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'gemini-3.6-flash',
            'base_url' => '',
          ),
          'deepseek'     => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'deepseek-chat',
            'base_url' => '',
          ),
          'ollama'       => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'llama3.1',
            'base_url' => 'http://localhost:11434',
          ),
          'openai_image' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'dall-e-3',
            'base_url' => '',
          ),
          'gemini_image' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'gemini-3.1-flash-image',
            'base_url' => '',
          ),
          'openai_audio' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'gpt-4o-mini-tts',
            'base_url' => '',
          ),
          'gemini_audio' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'gemini-2.5-flash-preview-tts',
            'base_url' => '',
          ),
          'openai_video' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'sora-2',
            'base_url' => '',
          ),
          'gemini_video' => array(
            'enabled'  => false,
            'api_key'  => '',
            'model'    => 'veo-3.1-lite-generate-preview',
            'base_url' => '',
          ),
          'mock'         => array(
            'enabled'  => true,
            'api_key'  => '',
            'model'    => 'mock-model',
            'base_url' => '',
          ),
        )
      );
    }

    if (false === get_option('kriti_ai_settings', false)) {
      add_option(
        'kriti_ai_settings',
        array(
          'default_text_provider'  => 'mock',
          'default_image_provider' => 'mock',
          'default_audio_provider' => 'mock',
          'default_video_provider' => 'mock',
          'temperature'            => 0.7,
          'max_tokens'             => 1024,
          'text_timeout'           => 90,
          'media_timeout'          => 180,
          'video_poll_interval'    => 15,
          'delete_uninstall'       => 0,
        )
      );
    }
  }

  /**
   * Register the recurring queue cron schedule.
   *
   * @return void
   */
  public static function scheduleCron()
  {
    if (! wp_next_scheduled('kriti_ai_process_queue')) {
      wp_schedule_event(time(), 'kriti_ai_every_minute', 'kriti_ai_process_queue');
    }
  }

  /**
   * Add a one minute interval to WP-Cron.
   *
   * @param array $schedules Cron schedules.
   * @return array
   */
  public static function cronSchedules($schedules)
  {
    $schedules['kriti_ai_every_minute'] = array(
      'interval' => MINUTE_IN_SECONDS,
      'display'  => __('Every minute (Kriti AI)', 'kriti-ai'),
    );
    return $schedules;
  }
}
