<?php

/**
 * WP-Cron worker that executes queued generation jobs.
 *
 * @package KritiAI
 */

namespace KritiAI;

use KritiAI\Core\Provider;

/**
 * Class Worker
 */
class Worker
{

  /**
   * Maximum number of attempts per job.
   *
   * @var int
   */
  const MAX_ATTEMPTS = 3;

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    // The event itself is registered by Queue::register().
  }

  /**
   * Process due jobs.
   *
   * @return void
   */
  public static function process()
  {
    if (! Queue::acquire_lock(self::queue_lock_ttl())) {
      return;
    }

    $jobs = Queue::next_jobs(5);

    foreach ($jobs as $job) {
      self::process_job($job);
    }

    Queue::release_lock();
  }

  /**
   * Get a queue lock TTL long enough for configured provider requests.
   *
   * @return int
   */
  private static function queue_lock_ttl()
  {
    $settings      = get_option('kriti_ai_settings', array());
    $text_timeout  = isset($settings['text_timeout']) ? (int) $settings['text_timeout'] : 90;
    $media_timeout = isset($settings['media_timeout']) ? (int) $settings['media_timeout'] : 180;

    return max(120, $text_timeout, $media_timeout) + 60;
  }

  /**
   * Process a single job.
   *
   * @param object $job Job row.
   * @return void
   */
  private static function process_job($job)
  {
    $provider = Provider_Manager::get($job->provider);

    if (! $provider || ! $provider->is_enabled()) {
      Queue::update(
        $job->id,
        array(
          'status' => 'failed',
          'error'  => __('Provider is not enabled.', 'kriti-ai'),
        )
      );
      return;
    }

    $params = json_decode((string) $job->params, true);
    if (! is_array($params)) {
      $params = array();
    }

    switch ($job->job_type) {
      case 'text':
        self::run_text($job, $provider, $params);
        break;
      case 'image':
        self::run_image($job, $provider, $params);
        break;
      case 'audio':
        self::run_audio($job, $provider, $params);
        break;
      case 'video':
        self::run_video($job, $provider, $params);
        break;
    }
  }

  /**
   * Run a synchronous image generation job.
   *
   * @param object   $job Job row.
   * @param Provider $provider Provider instance.
   * @param array    $params Job parameters.
   * @return void
   */
  private static function run_image($job, $provider, $params)
  {
    Queue::update(
      $job->id,
      array(
        'status'   => 'processing',
        'progress' => 10,
        'attempts' => (int) $job->attempts + 1,
      )
    );

    $prompt = isset($params['prompt']) ? (string) $params['prompt'] : '';
    $result = $provider->generate_image($prompt, $params);

    if (is_wp_error($result)) {
      self::handle_failure($job, $result->get_error_message());
      return;
    }

    Generator::finalize_media($job, $result, 'image', $params);
  }

  /**
   * Run a synchronous text generation job.
   *
   * @param object   $job Job row.
   * @param Provider $provider Provider instance.
   * @param array    $params Job parameters.
   * @return void
   */
  private static function run_text($job, $provider, $params)
  {
    Queue::update(
      $job->id,
      array(
        'status'   => 'processing',
        'progress' => 10,
        'attempts' => (int) $job->attempts + 1,
      )
    );

    $prompt = isset($params['prompt']) ? (string) $params['prompt'] : '';
    $result = $provider->generate_text($prompt, $params);

    if (is_wp_error($result)) {
      self::handle_failure($job, $result->get_error_message());
      return;
    }

    Generator::finalize_text($job, $result, $params);
  }

  /**
   * Run a synchronous audio generation job.
   *
   * @param object   $job Job row.
   * @param Provider $provider Provider instance.
   * @param array    $params Job parameters.
   * @return void
   */
  private static function run_audio($job, $provider, $params)
  {
    Queue::update(
      $job->id,
      array(
        'status'   => 'processing',
        'progress' => 10,
        'attempts' => (int) $job->attempts + 1,
      )
    );

    $text   = isset($params['text']) ? (string) $params['text'] : '';
    $result = $provider->synthesize_audio($text, $params);

    if (is_wp_error($result)) {
      self::handle_failure($job, $result->get_error_message());
      return;
    }

    Generator::finalize_media($job, $result, 'audio', $params);
  }

  /**
   * Run an (optionally async) video generation job.
   *
   * @param object   $job Job row.
   * @param Provider $provider Provider instance.
   * @param array    $params Job parameters.
   * @return void
   */
  private static function run_video($job, $provider, $params)
  {
    $prompt = isset($params['prompt']) ? (string) $params['prompt'] : '';
    $result = json_decode((string) $job->result, true);
    $result = is_array($result) ? $result : array();

    $is_pending_run = ('pending' === $job->status);
    $attempts       = (int) $job->attempts + ($is_pending_run ? 1 : 0);

    Queue::update(
      $job->id,
      array(
        'status'   => 'processing',
        'attempts' => $attempts,
      )
    );

    if ($is_pending_run && empty($result['external_id'])) {
      $outcome = $provider->start_video($prompt, $params);
    } elseif (! empty($result['external_id'])) {
      $outcome = $provider->poll_video((string) $result['external_id'], $params);
    } else {
      self::handle_failure($job, __('The video job has no provider reference to poll.', 'kriti-ai'));
      return;
    }

    if (is_wp_error($outcome)) {
      self::handle_failure($job, $outcome->get_error_message());
      return;
    }

    $status = isset($outcome['status']) ? $outcome['status'] : 'pending';

    if ('completed' === $status) {
      Generator::finalize_media($job, $outcome, 'video', $params);
      return;
    }

    // Still in flight: schedule the next poll.
    $settings = get_option('kriti_ai_settings', array());
    $interval = isset($settings['video_poll_interval']) ? (int) $settings['video_poll_interval'] : 15;
    $interval = max(10, $interval);

    Queue::update(
      $job->id,
      array(
        'status'     => 'processing',
        'result'     => wp_json_encode($outcome),
        'progress'   => min(90, max(15, (int) $job->progress)),
        'poll_after' => gmdate('Y-m-d H:i:s', time() + $interval),
      )
    );
  }

  /**
   * Handle a failed attempt: retry or mark as failed.
   *
   * @param object $job Job row.
   * @param string $error Error message.
   * @return void
   */
  private static function handle_failure($job, $error)
  {
    $attempts = (int) $job->attempts + 1;

    if ($attempts >= self::MAX_ATTEMPTS) {
      Queue::update(
        $job->id,
        array(
          'status' => 'failed',
          'error'  => $error,
        )
      );
      Metrics::record_failure($job, $error);
      return;
    }

    Queue::update(
      $job->id,
      array(
        'status'       => 'pending',
        'error'        => $error,
        'attempts'     => $attempts,
        'poll_after'   => null,
        'scheduled_at' => gmdate('Y-m-d H:i:s', time() + 60),
      )
    );
  }
}
