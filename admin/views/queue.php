<?php

/**
 * Queue view.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}
?>
<div class="wrap">
  <?php include 'header.php'; ?>
  <h1><?php esc_html_e('Generation Queue', 'kriti-ai'); ?></h1>

  <div class="kriti-ai-dashboard-toolbar">
    <button type="button" class="button" id="kriti-ai-refresh-queue">
      <?php esc_html_e('Refresh', 'kriti-ai'); ?>
    </button>
    <span class="kriti-ai-live-status"></span>
  </div>

  <div class="kriti-ai-panel">
    <table class="widefat striped" id="kriti-ai-queue-table">
      <thead>
        <tr>
          <th><?php esc_html_e('ID', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Type', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Provider', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Model', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Status', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Progress', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Created', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Result / Error', 'kriti-ai'); ?></th>
          <th><?php esc_html_e('Actions', 'kriti-ai'); ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="9"><?php esc_html_e('Loading…', 'kriti-ai'); ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="kriti-ai-panel">
    <h2><?php esc_html_e('About the queue', 'kriti-ai'); ?></h2>
    <p>
      <?php esc_html_e('Generation runs in the kriti-ai-background through WP-Cron. Video jobs are polled automatically until the provider finishes them. You can cancel a pending job; running jobs cannot be cancelled.', 'kriti-ai'); ?>
    </p>
  </div>

  <?php include 'footer.php'; ?>
</div>
