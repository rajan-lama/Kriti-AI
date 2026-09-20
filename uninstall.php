<?php

/**
 * Uninstall routine for Kriti AI.
 *
 * Removes plugin data only when the user opted in via Settings
 * ("Delete all plugin data on uninstall").
 *
 * @package KritiAI
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

$kriti_ai_settings = get_option('kriti_ai_settings', array());

if (empty($kriti_ai_settings['delete_uninstall'])) {
  return;
}

global $wpdb;

/*
 * Remove custom plugin tables.
 *
 * These table names are generated exclusively from the WordPress
 * database prefix and do not contain user input.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name is generated from the WordPress database prefix.
$wpdb->query(
  "DROP TABLE IF EXISTS {$wpdb->prefix}kriti_ai_jobs"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name is generated from the WordPress database prefix.
$wpdb->query(
  "DROP TABLE IF EXISTS {$wpdb->prefix}kriti_ai_metrics"
);

/*
 * Remove plugin options.
 */
delete_option('kriti_ai_settings');
delete_option('kriti_ai_providers');
delete_option('KRITI_AI_DB_version');
delete_option('kriti_ai_queue_lock');

/*
 * Delete prompt library posts.
 *
 * Using get_posts() here avoids direct database access and lets
 * WordPress handle the post query.
 */
$kriti_ai_prompts = get_posts(
  array(
    'post_type'      => 'kriti_ai_prompt',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
  )
);

foreach ($kriti_ai_prompts as $kriti_ai_prompt_id) {
  wp_delete_post(absint($kriti_ai_prompt_id), true);
}

/*
 * Delete generated media attachments and content posts.
 *
 * Query post IDs directly from postmeta. This is an uninstall-only
 * operation and the meta key is a fixed plugin-owned value.
 */
$kriti_ai_generated_ids = $wpdb->get_col(
  $wpdb->prepare(
    "SELECT DISTINCT pm.post_id
		FROM {$wpdb->postmeta} AS pm
		INNER JOIN {$wpdb->posts} AS p
			ON p.ID = pm.post_id
		WHERE pm.meta_key = %s
		AND pm.meta_value = %s
		AND p.post_type IN ('post', 'attachment')",
    '_kriti_ai_generated',
    '1'
  )
);

foreach ($kriti_ai_generated_ids as $kriti_ai_generated_id) {
  wp_delete_post(absint($kriti_ai_generated_id), true);
}

/*
 * Clear scheduled queue processing.
 */
wp_clear_scheduled_hook('kriti_ai_process_queue');
