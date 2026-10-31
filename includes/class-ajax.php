<?php

/**
 * Admin-AJAX endpoints powering the vanilla JS admin UI.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Ajax
 */
class Ajax
{

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action('wp_ajax_kriti_ai_generate', array(__CLASS__, 'generate'));
    add_action('wp_ajax_kriti_ai_job_status', array(__CLASS__, 'job_status'));
    add_action('wp_ajax_kriti_ai_jobs_recent', array(__CLASS__, 'jobs_recent'));
    add_action('wp_ajax_kriti_ai_publish_item', array(__CLASS__, 'publish_item'));
    add_action('wp_ajax_kriti_ai_cancel_job', array(__CLASS__, 'cancel_job'));
    add_action('wp_ajax_kriti_ai_dashboard', array(__CLASS__, 'dashboard'));
    add_action('wp_ajax_kriti_ai_load_prompt', array(__CLASS__, 'load_prompt'));
    add_action('wp_ajax_kriti_ai_test_provider', array(__CLASS__, 'test_provider'));
  }

  /**
   * Shared permission + nonce gate.
   *
   * @return void
   */
  private static function guard()
  {
    if (! current_user_can('manage_options')) {
      wp_send_json_error(array('message' => __('Permission denied.', 'kriti-ai')), 403);
    }

    check_ajax_referer('kriti_ai_ajax', 'nonce');
  }

  /**
   * GET parameter helper.
   *
   * @param string $key Key.
   * @param mixed  $fallback Default value.
   * @return mixed
   */
  private static function get_param($key, $fallback = '')
  {
    if (!isset($_REQUEST[$key]) && check_ajax_referer('kriti_ai_ajax', 'nonce')) {
      return $fallback;
    }

    $value = sanitize_text_field(wp_unslash($_REQUEST[$key]));

    if (is_array($value) && check_ajax_referer('kriti_ai_ajax', 'nonce')) {
      return $fallback;
    }

    return sanitize_text_field($value);
  }

  /**
   * Queue a generation job.
   *
   * @return void
   */
  public static function generate()
  {
    self::guard();

    $type = sanitize_key(self::get_param('type', 'content'));

    if (! in_array($type, array('content', 'image', 'audio', 'video'), true)) {
      wp_send_json_error(array('message' => __('Unknown generation type.', 'kriti-ai')));
    }

    $provider = sanitize_key(self::get_param('provider', ''));

    if ('content' === $type) {
      $job = self::queue_content($provider);
    } elseif ('image' === $type) {
      $job = self::queue_image($provider);
    } elseif ('audio' === $type) {
      $job = self::queue_audio($provider);
    } else {
      $job = self::queue_video($provider);
    }

    if (is_wp_error($job)) {
      wp_send_json_error(array('message' => $job->get_error_message()));
    }

    wp_send_json_success(
      array(
        'job_id' => (int) $job,
        'type'   => $type,
      )
    );
  }

  /**
   * Queue an image job.
   *
   * @param string $provider Provider slug.
   * @return int|WP_Error
   */
  private static function queue_image($provider)
  {
    $settings = Settings::get();
    $capability = 'image';
    $provider   = self::resolve_provider($provider, $capability);

    if (is_wp_error($provider)) {
      return $provider;
    }

    $prompt = (string) self::get_param('prompt', '');

    if ('' === trim($prompt)) {
      return new \WP_Error('kriti_ai_missing_prompt', __('Please describe the image you want to generate.', 'kriti-ai'));
    }

    $params = array(
      'prompt'       => Generator::replace_variables($prompt),
      'title'        => sanitize_text_field(self::get_param('title', __('AI Image', 'kriti-ai'))),
      'provider'     => $provider,
      'model'        => sanitize_text_field(self::get_param('model', '')),
      'resolution'   => sanitize_text_field(self::get_param('resolution', $settings['image_resolution'])),
      'aspect_ratio' => sanitize_text_field(self::get_param('aspect_ratio', $settings['image_aspect_ratio'])),
      'style'        => sanitize_text_field(self::get_param('style', $settings['image_style'])),
      'quality'      => sanitize_text_field(self::get_param('quality', $settings['image_quality'])),
    );

    return Queue::add('image', $provider, $params['model'], $params);
  }

  /**
   * Queue a text/content job.
   *
   * @param string $provider Provider slug.
   * @return int|WP_Error
   */
  private static function queue_content($provider)
  {
    $capability = 'text';
    $provider   = self::resolve_provider($provider, $capability);

    if (is_wp_error($provider)) {
      return $provider;
    }

    $user_prompt = (string) self::get_param('prompt', '');

    if ('' === trim($user_prompt)) {
      return new \WP_Error('kriti_ai_missing_prompt', __('Please enter a prompt or instructions.', 'kriti-ai'));
    }

    $settings = Settings::get();
    $mode = 'post';

    $params = array(
      'title'       => sanitize_text_field(self::get_param('title', '')),
      'prompt'      => Generator::build_post_prompt(
        $user_prompt,
        array(
          'system_instructions' => sanitize_textarea_field(self::get_param('system_instructions', '')),
        )
      ),
      'mode'        => $mode,
      'type'        => $mode,
      'provider'    => $provider,
      'model'       => sanitize_text_field(self::get_param('model', '')),
      'temperature' => (float) self::get_param('temperature', $settings['temperature']),
      'max_tokens'  => max(64, (int) self::get_param('max_tokens', $settings['max_tokens'])),
      'top_p'       => (float) self::get_param('top_p', $settings['top_p']),
      'top_k'       => max(0, (int) self::get_param('top_k', $settings['top_k'])),
    );

    return Queue::add('text', $provider, $params['model'], $params);
  }

  /**
   * Queue an audio job.
   *
   * @param string $provider Provider slug.
   * @return int|WP_Error
   */
  private static function queue_audio($provider)
  {
    $settings = Settings::get();
    $capability = 'audio';
    $provider   = self::resolve_provider($provider, $capability);

    if (is_wp_error($provider)) {
      return $provider;
    }

    $text = (string) self::get_param('text', '');

    if ('' === trim($text)) {
      return new \WP_Error('kriti_ai_missing_text', __('Please enter the script to convert to speech.', 'kriti-ai'));
    }

    $params = array(
      'text'             => $text,
      'title'            => sanitize_text_field(self::get_param('title', __('AI Audio', 'kriti-ai'))),
      'provider'         => $provider,
      'model'            => sanitize_text_field(self::get_param('model', '')),
      'voice'            => sanitize_text_field(self::get_param('voice', $settings['audio_voice'])),
      'speed'            => (float) self::get_param('speed', $settings['audio_speed']),
      'format'           => sanitize_key(self::get_param('format', 'mp3')),
      'stability'        => (float) self::get_param('stability', $settings['audio_stability']),
      'similarity_boost' => (float) self::get_param('similarity_boost', $settings['audio_similarity_boost']),
    );

    return Queue::add('audio', $provider, $params['model'], $params);
  }

  /**
   * Queue a video job.
   *
   * @param string $provider Provider slug.
   * @return int|WP_Error
   */
  private static function queue_video($provider)
  {
    $settings = Settings::get();
    $capability = 'video';
    $provider   = self::resolve_provider($provider, $capability);

    if (is_wp_error($provider)) {
      return $provider;
    }

    $prompt = (string) self::get_param('prompt', '');

    if ('' === trim($prompt)) {
      return new \WP_Error('kriti_ai_missing_prompt', __('Please describe the video you want to generate.', 'kriti-ai'));
    }

    $params = array(
      'prompt'       => $prompt,
      'title'        => sanitize_text_field(self::get_param('title', __('AI Video', 'kriti-ai'))),
      'provider'     => $provider,
      'model'        => sanitize_text_field(self::get_param('model', '')),
      'duration'     => (int) self::get_param('duration', $settings['video_duration']),
      'resolution'   => sanitize_text_field(self::get_param('resolution', $settings['video_resolution'])),
      'aspect_ratio' => sanitize_text_field(self::get_param('aspect_ratio', $settings['video_aspect_ratio'])),
      'quality'      => sanitize_text_field(self::get_param('quality', $settings['video_quality'])),
    );

    return Queue::add('video', $provider, $params['model'], $params);
  }

  /**
   * Resolve the provider slug for a capability.
   *
   * @param string $provider Requested provider.
   * @param string $capability Capability.
   * @return string|WP_Error
   */
  private static function resolve_provider($provider, $capability)
  {
    $enabled = Provider_Manager::enabled_slugs($capability);

    if ('' === $provider) {
      $provider = Provider_Manager::default_provider($capability);
    }

    if (! in_array($provider, $enabled, true)) {
      $provider_instance = Provider_Manager::get($provider);

      if ($provider_instance && ! $provider_instance->is_enabled()) {
        return new \WP_Error(
          'kriti_ai_provider_disabled',
          sprintf(
            /* translators: %s: provider slug */
            __('Provider "%s" is not enabled. Enable it in Settings first.', 'kriti-ai'),
            $provider
          )
        );
      }

      return new \WP_Error(
        'kriti_ai_no_provider',
        __('No enabled provider is available for this generation type.', 'kriti-ai')
      );
    }

    return $provider;
  }

  /**
   * Return the status of a job.
   *
   * @return void
   */
  public static function job_status()
  {
    self::guard();

    $id  = (int) self::get_param('job_id', 0);
    $job = Queue::get($id);

    if (! $job) {
      wp_send_json_error(array('message' => __('Job not found.', 'kriti-ai')));
    }

    wp_send_json_success(self::job_payload($job));
  }

  /**
   * Return recent jobs for the queue page.
   *
   * @return void
   */
  public static function jobs_recent()
  {
    self::guard();

    global $wpdb;

    $limit = min(50, max(5, (int) self::get_param('limit', 20)));

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue::table() is plugin-controlled table name.
    $rows = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue::table() is plugin-controlled table name.
      $wpdb->prepare('SELECT * FROM ' . Queue::table() . ' ORDER BY id DESC LIMIT %d', $limit)
    );

    wp_send_json_success(array_map(array(__CLASS__, 'job_payload'), $rows));
  }

  /**
   * Format a job row for JSON output.
   *
   * @param object $job Job row.
   * @return array
   */
  private static function job_payload($job)
  {
    $result = json_decode((string) $job->result, true);
    $result = is_array($result) ? $result : array();
    $params = json_decode((string) $job->params, true);
    $params = is_array($params) ? $params : array();

    return array(
      'id'         => (int) $job->id,
      'job_type'   => $job->job_type,
      'provider'   => $job->provider,
      'model'      => $job->model,
      'title'      => isset($params['title']) ? sanitize_text_field((string) $params['title']) : '',
      'status'     => $job->status,
      'progress'   => (int) $job->progress,
      'error'      => $job->error,
      'attempts'   => (int) $job->attempts,
      'created_at' => $job->created_at,
      'result'     => $result,
    );
  }

  /**
   * Publish or unpublish a generated item.
   *
   * @return void
   */
  public static function publish_item()
  {
    self::guard();

    $item_id = (int) self::get_param('item_id', 0);
    $action  = sanitize_key(self::get_param('action_name', 'publish'));

    if (! $item_id) {
      wp_send_json_error(array('message' => __('Missing item id.', 'kriti-ai')));
      return;
    }

    if ('publish' === $action) {
      $result = Media::publish($item_id);
    } elseif ('unpublish' === $action) {
      $result = Media::unpublish($item_id);
    } elseif ('delete' === $action) {
      $result = Media::delete($item_id);
    } else {
      wp_send_json_error(array('message' => __('Unknown action.', 'kriti-ai')));
      return;
    }

    if (is_wp_error($result)) {
      wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(
      array(
        'item_id' => $item_id,
        'state'   => Media::state($item_id),
      )
    );
  }

  /**
   * Cancel a queued job.
   *
   * @return void
   */
  public static function cancel_job()
  {
    self::guard();

    $id = (int) self::get_param('job_id', 0);

    if (! Queue::cancel($id)) {
      wp_send_json_error(array('message' => __('Job could not be cancelled.', 'kriti-ai')));
    }

    wp_send_json_success(array('job_id' => $id));
  }

  /**
   * Dashboard metrics payload.
   *
   * @return void
   */
  public static function dashboard()
  {
    self::guard();

    $days = min(90, max(7, (int) self::get_param('days', 30)));

    wp_send_json_success(
      array(
        'summary'          => Metrics::summary($days),
        'generated_totals' => Metrics::generated_totals(),
        'connected_models' => self::dashboard_connected_models(),
        'by_provider'      => Metrics::by_provider($days),
        'daily'            => Metrics::daily(min(30, $days)),
        'draft_published'  => Metrics::draft_vs_published(),
      )
    );
  }

  /**
   * Connected provider/model status for the dashboard.
   *
   * @return array
   */
  private static function dashboard_connected_models()
  {
    $capabilities = array('text', 'image', 'audio', 'video');
    $seen         = array();

    foreach ($capabilities as $capability) {
      foreach (Provider_Manager::slugs_for($capability) as $slug) {
        if (isset($seen[$slug])) {
          $seen[$slug]['capabilities'][] = $capability;
          continue;
        }

        $provider = Provider_Manager::get($slug);
        $config   = Settings::provider($slug);
        $enabled  = $provider ? $provider->is_enabled() : ! empty($config['enabled']);
        $ready    = $provider ? Provider_Manager::is_ready($provider) : false;

        if ($ready) {
          $status = 'ready';
        } elseif ($enabled) {
          $status = 'needs_key';
        } else {
          $status = 'disabled';
        }

        $model_choices = Provider_Manager::model_choices($slug);
        $model         = Provider_Manager::default_model($slug, $config);

        $seen[$slug] = array(
          'slug'         => $slug,
          'label'        => Provider_Manager::label($slug),
          'model'        => $model,
          'status'       => $status,
          'enabled'      => (bool) $enabled,
          'capabilities' => array($capability),
        );
      }
    }

    $models = array_values($seen);

    usort(
      $models,
      function ($left, $right) {
        $weights = array(
          'ready'     => 0,
          'needs_key' => 1,
          'disabled'  => 2,
        );

        $left_weight  = isset($weights[$left['status']]) ? $weights[$left['status']] : 3;
        $right_weight = isset($weights[$right['status']]) ? $weights[$right['status']] : 3;

        if ($left_weight === $right_weight) {
          return strcasecmp($left['label'], $right['label']);
        }

        return $left_weight - $right_weight;
      }
    );

    return $models;
  }

  /**
   * Load a saved prompt for the generate form.
   *
   * @return void
   */
  public static function load_prompt()
  {
    self::guard();

    $id   = (int) self::get_param('prompt_id', 0);
    $post = get_post($id);

    if (! $post || Post_Types::PROMPT !== $post->post_type) {
      wp_send_json_error(array('message' => __('Prompt not found.', 'kriti-ai')));
    }

    wp_send_json_success(
      array(
        'id'          => (int) $post->ID,
        'title'       => get_the_title($post),
        'content'     => $post->post_content,
        'provider'    => get_post_meta($post->ID, 'kriti_ai_provider', true),
        'model'       => get_post_meta($post->ID, 'kriti_ai_model', true),
        'temperature' => get_post_meta($post->ID, 'kriti_ai_temperature', true),
        'max_tokens'  => get_post_meta($post->ID, 'kriti_ai_max_tokens', true),
        'top_p'       => get_post_meta($post->ID, 'kriti_ai_top_p', true),
        'item_type'   => get_post_meta($post->ID, 'kriti_ai_item_type', true),
      )
    );
  }

  /**
   * Test a provider's connection with the key / URL currently in the form,
   * falling back to the saved values for fields that were not sent.
   *
   * @return void
   */
  public static function test_provider()
  {
    self::guard();

    $slug  = sanitize_key(self::get_param('slug', ''));
    $known = array();

    foreach (array('text', 'image', 'audio', 'video') as $capability) {
      $known = array_merge($known, Provider_Manager::slugs_for($capability));
    }

    if (! in_array($slug, $known, true)) {
      wp_send_json_error(array('message' => __('Unknown provider.', 'kriti-ai')));
    }

    $config = Settings::provider($slug);

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce checked in guard().
    if (isset($_REQUEST['api_key'])) {
      $config['api_key'] = trim((string) self::get_param('api_key', ''));
    }

    if (isset($_REQUEST['base_url'])) {
      $config['base_url'] = preg_replace('#/v1beta(/models)?/?$#i', '', rtrim(esc_url_raw(self::get_param('base_url', '')), '/'));
    }
    // phpcs:enable

    $provider = Provider_Manager::create($slug, $config);
    $result   = $provider ? $provider->test_connection() : new \WP_Error('kriti_ai_no_provider', __('Unknown provider.', 'kriti-ai'));

    if (is_wp_error($result)) {
      wp_send_json_error(array('message' => $result->get_error_message()));
    }

    wp_send_json_success(array('message' => __('Connection successful.', 'kriti-ai')));
  }
}
