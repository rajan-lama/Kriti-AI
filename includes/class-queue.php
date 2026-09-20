<?php

/**
 * Background job queue backed by a custom table.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Queue.
 */
class Queue
{

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action(
      'kriti_ai_process_queue',
      array('KritiAI\Worker', 'process')
    );
  }

  /**
   * The queue table name.
   *
   * @return string
   */
  public static function table()
  {
    global $wpdb;

    return $wpdb->prefix . 'kriti_ai_jobs';
  }

  /**
   * Add a job to the queue.
   *
   * @param string $job_type Job type: text|audio|video.
   * @param string $provider Provider slug.
   * @param string $model    Model slug.
   * @param array  $params   Job payload.
   * @param int    $delay    Delay in seconds before the job becomes available.
   * @return int|\WP_Error Job ID or error.
   */
  public static function add(
    $job_type,
    $provider,
    $model,
    $params,
    $delay = 0
  ) {
    global $wpdb;

    $now = gmdate('Y-m-d H:i:s');

    $delay = max(0, (int) $delay);

    $scheduled_at = $now;

    if ($delay > 0) {
      $scheduled_at = gmdate(
        'Y-m-d H:i:s',
        time() + $delay
      );
    }

    $inserted = $wpdb->insert(
      self::table(),
      array(
        'job_type'     => sanitize_key($job_type),
        'provider'     => sanitize_key($provider),
        'model'        => sanitize_text_field($model),
        'params'       => wp_json_encode($params),
        'status'       => 'pending',
        'progress'     => 0,
        'attempts'     => 0,
        'created_at'   => $now,
        'updated_at'   => $now,
        'scheduled_at' => $scheduled_at,
      ),
      array(
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%s',
        '%s',
        '%s',
      )
    );

    if (false === $inserted) {
      return new \WP_Error(
        'kriti_ai_queue_error',
        __(
          'Could not create the background job.',
          'kriti-ai'
        )
      );
    }

    $id = (int) $wpdb->insert_id;

    self::ensure_cron();

    return $id;
  }

  /**
   * Get a single job.
   *
   * @param int $id Job ID.
   * @return object|null
   */
  public static function get($id)
  {
    global $wpdb;

    $id = absint($id);

    if (! $id) {
      return null;
    }

    $table = self::table();

    /*
		 * The table name is generated exclusively by self::table()
		 * using the WordPress database prefix. It is never derived
		 * from user input. Values are still passed through prepare().
		 *
		 * WordPress versions before 6.2 do not support the %i
		 * identifier placeholder.
		 */
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated exclusively by self::table() and contains no user input.
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $id
      )
    );

    return $row ? $row : null;
  }

  /**
   * Update job fields.
   *
   * @param int   $id     Job ID.
   * @param array $fields Fields to update.
   * @return bool
   */
  public static function update($id, $fields)
  {
    global $wpdb;

    $id = absint($id);

    if (! $id || empty($fields) || ! is_array($fields)) {
      return false;
    }

    $fields['updated_at'] = gmdate('Y-m-d H:i:s');

    $format = array();

    foreach ($fields as $key => $value) {
      if (in_array($key, array('progress', 'attempts'), true)) {
        $format[] = '%d';
      } else {
        $format[] = '%s';
      }
    }

    $updated = $wpdb->update(
      self::table(),
      $fields,
      array(
        'id' => $id,
      ),
      $format,
      array('%d')
    );

    return false !== $updated;
  }

  /**
   * Pick jobs ready to run.
   *
   * Pending jobs are always eligible.
   *
   * Processing jobs are eligible again once their poll_after time
   * has passed, which is used by async video providers.
   *
   * @param int $limit Maximum number of jobs.
   * @return object[]
   */
  public static function next_jobs($limit = 5)
  {
    global $wpdb;

    $limit = max(1, min(50, (int) $limit));

    $now   = gmdate('Y-m-d H:i:s');
    $table = self::table();

    /*
		 * The table name is generated exclusively by self::table()
		 * and cannot contain user-supplied data. Query values are
		 * passed through wpdb::prepare().
		 *
		 * WordPress versions before 6.2 do not support the %i
		 * identifier placeholder.
		 */
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated exclusively by self::table() and contains no user input.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
				FROM {$table}
				WHERE
					(
						status = 'pending'
						AND scheduled_at <= %s
					)
					OR
					(
						status = 'processing'
						AND poll_after IS NOT NULL
						AND poll_after <= %s
					)
				ORDER BY id ASC
				LIMIT %d",
        $now,
        $now,
        $limit
      )
    );

    return $rows ? $rows : array();
  }

  /**
   * Cancel a pending or processing job.
   *
   * @param int $id Job ID.
   * @return bool
   */
  public static function cancel($id)
  {
    $id  = absint($id);
    $job = self::get($id);

    if (! $job) {
      return false;
    }

    if (
      'completed' === $job->status ||
      'failed' === $job->status ||
      'cancelled' === $job->status
    ) {
      return false;
    }

    return self::update(
      $id,
      array(
        'status' => 'cancelled',
      )
    );
  }

  /**
   * Mark the queue as locked for processing.
   *
   * Uses a unique marker per claim so a failing add_option()
   * reliably means another worker claimed the lock first.
   *
   * @param int $ttl Lock lifetime in seconds.
   * @return bool True when the lock was acquired.
   */
  public static function acquire_lock($ttl = 120)
  {
    $now = time();

    $ttl = max(1, (int) $ttl);

    $lock = get_option('kriti_ai_queue_lock', 0);

    if ($lock) {
      $parts = explode(':', (string) $lock);

      $expires_at = isset($parts[1])
        ? (int) $parts[1]
        : 0;

      if ($expires_at > $now) {
        return false;
      }

      delete_option('kriti_ai_queue_lock');
    }

    $marker = wp_generate_uuid4();

    $value = $marker . ':' . ($now + $ttl);

    /*
		 * add_option() is atomic when creating a new option.
		 * This prevents multiple workers from acquiring the
		 * queue lock simultaneously.
		 */
    if (! add_option('kriti_ai_queue_lock', $value, '', false)) {
      return false;
    }

    return true;
  }

  /**
   * Release the queue lock.
   *
   * @return void
   */
  public static function release_lock()
  {
    delete_option('kriti_ai_queue_lock');
  }

  /**
   * Ensure the cron event is scheduled.
   *
   * @return void
   */
  public static function ensure_cron()
  {
    Install::scheduleCron();
  }
}
