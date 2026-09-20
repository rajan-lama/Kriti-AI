<?php

/**
 * Dashboard view.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

if (! current_user_can('manage_options')) {
  return;
}

$kriti_ai_default_days = 30;
$kriti_ai_current_user = wp_get_current_user();
$kriti_ai_dashboard_name = $kriti_ai_current_user && $kriti_ai_current_user->ID ? $kriti_ai_current_user->display_name : __('there', 'kriti-ai');
?>
<div class="wrap">
  <?php include 'header.php'; ?>
  <div>
    <h1 class="font-kriti-ai-headline-lg text-kriti-ai-headline-lg text-kriti-ai-on-surface mb-1">
      <?php
      printf(
        /* translators: %s: current user's display name. */
        esc_html__('Welcome back, %s', 'kriti-ai'),
        esc_html($kriti_ai_dashboard_name)
      );
      ?>
    </h1>
  </div>
  <div class="text-kriti-ai-on-surface font-kriti-ai-body-md min-h-screen flex flex-col antialiased">
    <div class="flex flex-1 pt-5 w-full mx-auto">

      <!-- Main Content Area -->
      <main class="flex-1 flex flex-col gap-kriti-ai-stack-lg overflow-y-auto">
        <!-- Welcome Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-kriti-ai-stack-md">
          <div>
            <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray"><?php esc_html_e('Here is what is happening with your AI generation tasks today.', 'kriti-ai'); ?></p>
          </div>
          <div class="flex gap-3">
            <a class="flex items-center gap-2 px-4 py-2 bg-gradient-to-b from-[#6366F1] to-[#4F46E5] text-white rounded-lg font-kriti-ai-label-md text-kriti-ai-label-md hover:opacity-90 transition-opacity shadow-sm" href="<?php echo esc_url(admin_url('admin.php?page=kriti-ai-generate')); ?>">
              <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;"><?php esc_html_e('add', 'kriti-ai'); ?></span>
              <?php esc_html_e('New Generation', 'kriti-ai'); ?>
            </a>
          </div>
        </div>
        <!-- Bento Grid High-Level Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-kriti-ai-gutter">
          <!-- Metric 1 -->
          <div class="bg-kriti-ai-surface p-6 rounded-xl border border-kriti-ai-outline-variant/40 shadow-[0_4px_12px_rgba(0,0,0,0.02)] flex flex-col gap-4 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-kriti-ai-indigo-wash rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-500"></div>
            <div class="flex justify-between items-start">
              <div class="h-10 w-10 rounded-lg bg-kriti-ai-indigo-wash text-kriti-ai-primary flex items-center justify-center">
                <span class="material-symbols-outlined"><?php esc_html_e('article', 'kriti-ai'); ?></span>
              </div>
              <span class="flex items-center gap-1 text-emerald-600 font-kriti-ai-label-md text-kriti-ai-label-md bg-emerald-50 px-2 py-1 rounded-md">
                <span class="material-symbols-outlined text-[14px]"><?php esc_html_e('database', 'kriti-ai'); ?></span>
                <span data-kriti-ai-kpi-trend="articles">—</span>
              </span>
            </div>
            <div>
              <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray mb-1"><?php esc_html_e('Total Articles', 'kriti-ai'); ?></p>
              <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface" data-kriti-ai-kpi="articles">—</h2>
            </div>
          </div>
          <!-- Metric 2 -->
          <div class="bg-kriti-ai-surface p-6 rounded-xl border border-kriti-ai-outline-variant/40 shadow-[0_4px_12px_rgba(0,0,0,0.02)] flex flex-col gap-4 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-kriti-ai-surface-container-high rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-500"></div>
            <div class="flex justify-between items-start">
              <div class="h-10 w-10 rounded-lg bg-kriti-ai-surface-container-high text-kriti-ai-secondary flex items-center justify-center">
                <span class="material-symbols-outlined"><?php esc_html_e('image', 'kriti-ai'); ?></span>
              </div>
              <span class="flex items-center gap-1 text-emerald-600 font-kriti-ai-label-md text-kriti-ai-label-md bg-emerald-50 px-2 py-1 rounded-md">
                <span class="material-symbols-outlined text-[14px]"><?php esc_html_e('database', 'kriti-ai'); ?></span>
                <span data-kriti-ai-kpi-trend="images">—</span>
              </span>
            </div>
            <div>
              <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray mb-1"><?php esc_html_e('Images', 'kriti-ai'); ?></p>
              <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface" data-kriti-ai-kpi="images">—</h2>
            </div>
          </div>
          <!-- Metric 3 -->
          <div class="bg-kriti-ai-surface p-6 rounded-xl border border-kriti-ai-outline-variant/40 shadow-[0_4px_12px_rgba(0,0,0,0.02)] flex flex-col gap-4 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-kriti-ai-surface-container rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-500"></div>
            <div class="flex justify-between items-start">
              <div class="h-10 w-10 rounded-lg bg-kriti-ai-surface-container text-kriti-ai-tertiary flex items-center justify-center">
                <span class="material-symbols-outlined"><?php esc_html_e('audio_file', 'kriti-ai'); ?></span>
              </div>
              <span class="flex items-center gap-1 text-emerald-600 font-kriti-ai-label-md text-kriti-ai-label-md bg-emerald-50 px-2 py-1 rounded-md">
                <span class="material-symbols-outlined text-[14px]"><?php esc_html_e('database', 'kriti-ai'); ?></span>
                <span data-kriti-ai-kpi-trend="audio">—</span>
              </span>
            </div>
            <div>
              <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray mb-1"><?php esc_html_e('Audio Files', 'kriti-ai'); ?></p>
              <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface" data-kriti-ai-kpi="audio">—</h2>
            </div>
          </div>
          <!-- Metric 4 -->
          <div class="bg-kriti-ai-surface p-6 rounded-xl border-t-2 border-t-kriti-ai-primary border-x border-x-kriti-ai-outline-variant/40 border-b border-b-kriti-ai-outline-variant/40 shadow-[0_12px_24px_rgba(99,102,241,0.05)] flex flex-col gap-4 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-kriti-ai-indigo-wash/50 to-transparent -z-10"></div>
            <div class="flex justify-between items-start">
              <div class="h-10 w-10 rounded-lg bg-kriti-ai-primary text-white flex items-center justify-center shadow-md">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;"><?php esc_html_e('movie', 'kriti-ai'); ?></span>
              </div>
              <span class="flex items-center gap-1 text-emerald-600 font-kriti-ai-label-md text-kriti-ai-label-md bg-emerald-50 px-2 py-1 rounded-md">
                <span class="material-symbols-outlined text-[14px]"><?php esc_html_e('database', 'kriti-ai'); ?></span>
                <span data-kriti-ai-kpi-trend="videos">—</span>
              </span>
            </div>
            <div>
              <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray mb-1 flex items-center gap-1"><?php esc_html_e('Videos', 'kriti-ai'); ?></p>
              <h2 class="font-kriti-ai-headline-md text-kriti-ai-headline-md text-kriti-ai-on-surface ai-text-gradient" data-kriti-ai-kpi="videos">—</h2>
            </div>
          </div>
        </div>
        <!-- Main Split Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-kriti-ai-gutter">
          <!-- Left Column (Chart & Table) -->
          <div class="lg:col-span-2 flex flex-col gap-kriti-ai-gutter">
            <!-- Usage Chart Area (Stylized) -->
            <div class="bg-kriti-ai-surface rounded-xl border border-kriti-ai-outline-variant/40 shadow-sm p-6">
              <div class="flex justify-between items-center mb-6">
                <h3 class="font-kriti-ai-title-md text-kriti-ai-title-md text-kriti-ai-on-surface font-semibold">
                  <?php esc_html_e('Generation Volume', 'kriti-ai'); ?>
                  (<span id="kriti-ai-volume-days"><?php echo esc_html($kriti_ai_default_days); ?></span> <?php esc_html_e('Days', 'kriti-ai'); ?>)
                </h3>
                <div>
                  <select id="kriti-ai-days" data-default="<?php echo esc_attr($kriti_ai_default_days); ?>" class="bg-kriti-ai-surface-container-low border-none rounded-md text-kriti-ai-label-md font-kriti-ai-label-md text-kriti-ai-on-surface focus:ring-1 focus:ring-kriti-ai-primary py-1 pl-3 pr-8 cursor-pointer">
                    <option value="7"><?php esc_html_e('Last 7 Days', 'kriti-ai'); ?></option>
                    <option value="30" selected><?php esc_html_e('Last 30 Days', 'kriti-ai'); ?></option>
                    <option value="90"><?php esc_html_e('Last 90 Days', 'kriti-ai'); ?></option>
                  </select>
                  <button type="button" class="button" id="kriti-ai-refresh-dashboard">
                    <?php esc_html_e('Refresh', 'kriti-ai'); ?>
                  </button>
                </div>
              </div>
              <div class="kriti-ai-cards" id="kriti-ai-metric-cards">
                <div class="kriti-ai-card kriti-ai-card-placeholder"><?php esc_html_e('Loading metrics…', 'kriti-ai'); ?></div>
              </div>
              <div class="kriti-ai-panel h-64 w-full rounded-lg border border-kriti-ai-outline-variant/20 relative overflow-hidden flex items-end px-4 gap-2 pb-4 pt-10">
                <div id="kriti-ai-chart" width="100%" height="100%"></div>
              </div>
            </div>
            <!-- Recent Activity Table -->
            <div class="bg-kriti-ai-surface rounded-xl border border-kriti-ai-outline-variant/40 shadow-sm overflow-hidden">
              <div class="p-6 border-b border-kriti-ai-outline-variant/30 flex justify-between items-center">
                <h3 class="font-kriti-ai-title-md text-kriti-ai-title-md text-kriti-ai-on-surface font-semibold"><?php esc_html_e('Recent Activity', 'kriti-ai'); ?></h3>
                <a class="text-kriti-ai-primary font-kriti-ai-label-md text-kriti-ai-label-md hover:underline" href="<?php echo esc_url(admin_url('admin.php?page=kriti-ai-queue')); ?>"><?php esc_html_e('View All', 'kriti-ai'); ?></a>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="kriti-ai-dashboard-recent-table">
                  <thead>
                    <tr class="bg-kriti-ai-surface-container-lowest border-b border-kriti-ai-outline-variant/30 text-kriti-ai-slate-gray font-kriti-ai-label-md text-kriti-ai-label-md uppercase tracking-wider">
                      <th class="p-4 font-medium"><?php esc_html_e('Content Name', 'kriti-ai'); ?></th>
                      <th class="p-4 font-medium"><?php esc_html_e('Type', 'kriti-ai'); ?></th>
                      <th class="p-4 font-medium"><?php esc_html_e('Status', 'kriti-ai'); ?></th>
                      <th class="p-4 font-medium"><?php esc_html_e('Date', 'kriti-ai'); ?></th>
                    </tr>
                  </thead>
                  <tbody id="kriti-ai-dashboard-recent-body" class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-on-surface divide-y divide-kriti-ai-outline-variant/20">
                    <tr>
                      <td class="p-4 text-kriti-ai-slate-gray" colspan="4"><?php esc_html_e('Loading recent activity…', 'kriti-ai'); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="overflow-hidden">

              <div class="kriti-ai-panel">
                <h2><?php esc_html_e('Usage by provider', 'kriti-ai'); ?></h2>
                <table class="widefat striped" id="kriti-ai-provider-table">
                  <thead>
                    <tr>
                      <th><?php esc_html_e('Provider', 'kriti-ai'); ?></th>
                      <th><?php esc_html_e('Requests', 'kriti-ai'); ?></th>
                      <th><?php esc_html_e('Success', 'kriti-ai'); ?></th>
                      <th><?php esc_html_e('Tokens in', 'kriti-ai'); ?></th>
                      <th><?php esc_html_e('Tokens out', 'kriti-ai'); ?></th>
                      <th><?php esc_html_e('Est. cost', 'kriti-ai'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="6"><?php esc_html_e('Loading…', 'kriti-ai'); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Right Sidebar (Quick Actions & Models) -->
          <div class="flex flex-col gap-kriti-ai-gutter">
            <!-- Quick Actions Bento Box -->
            <div class="kriti-ai-panel">
              <h2><?php esc_html_e('Draft vs Published', 'kriti-ai'); ?></h2>
              <div id="kriti-ai-draft-published" class="kriti-ai-draft-published">
                <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray"><?php esc_html_e('Loading publication states…', 'kriti-ai'); ?></p>
              </div>
            </div>

            <!-- API Usage Widget -->
            <div class="bg-kriti-ai-surface-container-low p-4 rounded-xl border border-kriti-ai-outline-variant/50">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-kriti-ai-primary text-sm"><?php esc_html_e('bolt', 'kriti-ai'); ?></span>
                <h4 class="font-kriti-ai-label-md text-kriti-ai-label-md text-kriti-ai-on-surface"><?php esc_html_e('API Usage', 'kriti-ai'); ?></h4>
              </div>
              <div class="w-full bg-kriti-ai-outline-variant/30 rounded-full h-1.5 mb-2 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="kriti-ai-api-usage-bar">
                <div class="bg-kriti-ai-primary h-1.5 rounded-full relative overflow-hidden" id="kriti-ai-api-usage-fill" style="width:0%;">
                  <div class="absolute inset-0 bg-white/20 animate-pulse" id="kriti-ai-api-usage-pulse"></div>
                </div>
              </div>
              <p class="font-kriti-ai-label-md text-[10px] text-kriti-ai-slate-gray" id="kriti-ai-api-usage-text"><?php esc_html_e('Loading API usage…', 'kriti-ai'); ?></p>
            </div>
            <!-- Connected Models Widget -->
            <div class="bg-kriti-ai-surface rounded-xl border border-kriti-ai-outline-variant/40 shadow-sm p-6">
              <div class="flex justify-between items-center mb-4">
                <h3 class="font-kriti-ai-title-md text-kriti-ai-title-md text-kriti-ai-on-surface font-semibold flex items-center gap-2">
                  <span class="material-symbols-outlined text-kriti-ai-primary"><?php esc_html_e('hub', 'kriti-ai'); ?></span>
                  <?php esc_html_e('Connected Models', 'kriti-ai'); ?>
                </h3>
                <a class="text-kriti-ai-slate-gray hover:text-kriti-ai-primary transition-colors" href="<?php echo esc_url(admin_url('admin.php?page=kriti-ai-providers')); ?>" aria-label="<?php esc_attr_e('Open provider settings', 'kriti-ai'); ?>">
                  <span class="material-symbols-outlined text-[20px]"><?php esc_html_e('settings', 'kriti-ai'); ?></span>
                </a>
              </div>
              <div class="flex flex-col gap-3" id="kriti-ai-connected-models">
                <p class="font-kriti-ai-body-md text-kriti-ai-body-md text-kriti-ai-slate-gray"><?php esc_html_e('Loading connected models…', 'kriti-ai'); ?></p>
              </div>
              <a class="w-full mt-4 py-2 border border-dashed border-kriti-ai-outline-variant rounded-lg text-kriti-ai-slate-gray font-kriti-ai-label-md text-kriti-ai-label-md hover:border-kriti-ai-primary hover:text-kriti-ai-primary transition-colors flex items-center justify-center gap-2" href="<?php echo esc_url(admin_url('admin.php?page=kriti-ai-providers')); ?>">
                <span class="material-symbols-outlined text-[16px]"><?php esc_html_e('add', 'kriti-ai'); ?></span> <?php esc_html_e('Add Provider', 'kriti-ai'); ?>
              </a>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>
