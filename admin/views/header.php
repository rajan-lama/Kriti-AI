<!-- TopNavBar -->
<header id="kriti-ai-dashboard-header">
  <div class="flex justify-between items-center max-w-full px-5 h-20 bg-white border rounded-lg">
    <!-- Brand -->
    <a class="font-title-md text-title-md font-bold text-primary dark:text-primary-fixed flex items-center gap-2" href="#">
      <img src="<?php echo KRITI_AI_URL . 'images/kriti-ai-logo.png'; ?>" alt="Kriti AI Logo" class="h-8 w-8" />
      <?php esc_html_e('Kriti AI', 'kriti-ai'); ?>
    </a>
    <!-- Links (Desktop) -->
    <div class="hidden md:flex gap-6 items-center">
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="https://kritiai.net/documentation"><?php esc_html_e('Documentation', 'kriti-ai'); ?></a>
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="<?php echo admin_url('admin.php?page=kriti-ai'); ?>"><?php esc_html_e('Dashboard', 'kriti-ai'); ?></a>
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="<?php echo admin_url('admin.php?page=kriti-ai-generate'); ?>"><?php esc_html_e('AI Studio', 'kriti-ai'); ?></a>
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="<?php echo admin_url('edit.php?post_type=kriti_ai_prompt'); ?>"><?php esc_html_e('Prompt Library', 'kriti-ai'); ?></a>
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="<?php echo admin_url('admin.php?page=kriti-ai-queue'); ?>"><?php esc_html_e('Queue', 'kriti-ai'); ?></a>
      <a class="font-body-lg text-body-lg text-slate-gray dark:text-on-surface-variant hover:text-primary dark:hover:text-primary-fixed transition-colors" href="<?php echo admin_url('admin.php?page=kriti-ai-providers'); ?>"><?php esc_html_e('Settings', 'kriti-ai'); ?></a>
    </div>
    <!-- Trailing Action -->
    <a href="https://kritiai.net/products" class="bg-kriti-ai-primary text-white font-label-md px-6 py-2 rounded-1 hover:opacity-90 transition-opacity hover:text-white">
      <?php esc_html_e('Get Pro', 'kriti-ai'); ?>
    </a>
  </div>
</header>

<h1></h1>