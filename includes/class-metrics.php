<?php

/**
 * Usage, cost and performance metrics.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Metrics.
 */
class Metrics
{

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    // Static utility class; nothing to hook.
  }

  /**
   * The metrics table name.
   *
   * @return string
   */
  public static function table()
  {
    global $wpdb;

    return $wpdb->prefix . 'kriti_ai_metrics';
  }

  /**
   * Record a successful job.
   *
   * @param object $job       Job row.
   * @param string $item_type Item type: text|audio|video.
   * @param array  $tokens    Token counts.
   * @return void
   */
  public static function record_success($job, $item_type, $tokens = array())
  {
    global $wpdb;

    $tokens_in  = isset($tokens['in']) ? absint($tokens['in']) : 0;
    $tokens_out = isset($tokens['out']) ? absint($tokens['out']) : 0;

    $duration_ms = self::duration_ms($job->created_at);

    $wpdb->insert(
      self::table(),
      array(
        'job_id'      => absint($job->id),
        'item_type'   => sanitize_key($item_type),
        'provider'    => sanitize_key($job->provider),
        'model'       => sanitize_text_field($job->model),
        'status'      => 'success',
        'tokens_in'   => $tokens_in,
        'tokens_out'  => $tokens_out,
        'cost'        => self::estimate_cost(
          $job->provider,
          $job->model,
          $tokens_in,
          $tokens_out
        ),
        'duration_ms' => $duration_ms,
        'created_at'  => gmdate('Y-m-d H:i:s'),
      ),
      array(
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%f',
        '%d',
        '%s',
      )
    );
  }

  /**
   * Record a failed job.
   *
   * @param object $job   Job row.
   * @param string $error Error message.
   * @return void
   */
  public static function record_failure($job, $error)
  {
    global $wpdb;

    $wpdb->insert(
      self::table(),
      array(
        'job_id'      => absint($job->id),
        'item_type'   => sanitize_key($job->job_type),
        'provider'    => sanitize_key($job->provider),
        'model'       => sanitize_text_field($job->model),
        'status'      => 'failed',
        'tokens_in'   => 0,
        'tokens_out'  => 0,
        'cost'        => 0,
        'duration_ms' => self::duration_ms($job->created_at),
        'created_at'  => gmdate('Y-m-d H:i:s'),
      ),
      array(
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%d',
        '%f',
        '%d',
        '%s',
      )
    );

    unset($error);
  }

  /**
   * Milliseconds elapsed since a job was created.
   *
   * @param string $created_at MySQL datetime.
   * @return int
   */
  private static function duration_ms($created_at)
  {
    $created = strtotime($created_at . ' UTC');

    if (false === $created) {
      return 0;
    }

    return max(0, (time() - $created) * 1000);
  }

  /**
   * Estimate API cost in USD for a generation.
   *
   * Rates are approximate per-1M token figures.
   *
   * @param string $provider  Provider slug.
   * @param string $model     Model name.
   * @param int    $tokens_in Input tokens.
   * @param int    $tokens_out Output tokens.
   * @return float
   */
  public static function estimate_cost(
    $provider,
    $model,
    $tokens_in,
    $tokens_out
  ) {
    $provider  = sanitize_key($provider);
    $model     = sanitize_text_field($model);
    $tokens_in = absint($tokens_in);
    $tokens_out = absint($tokens_out);

    $rates = array(
      'openai' => array(
        'gpt-4o'       => array(2.50, 10.00),
        'gpt-4o-mini'  => array(0.15, 0.60),
        'gpt-4.1'      => array(2.00, 8.00),
        'gpt-4.1-mini' => array(0.40, 1.60),
        'gpt-4.1-nano' => array(0.10, 0.40),
      ),
      'gemini' => array(
        'gemini-3.6-flash'      => array(0.10, 0.40),
        'gemini-3.7-flash'      => array(0.10, 0.40),
        'gemini-3.1-pro'        => array(1.25, 5.00),
        'gemini-3.5-flash-lite' => array(0.075, 0.30),
      ),
    );

    $in_rate  = 0.0;
    $out_rate = 0.0;

    if (isset($rates[$provider][$model])) {
      $in_rate  = (float) $rates[$provider][$model][0];
      $out_rate = (float) $rates[$provider][$model][1];
    } elseif (isset($rates[$provider])) {
      // Fall back to the first model's rate.
      $first = reset($rates[$provider]);

      $in_rate  = (float) $first[0];
      $out_rate = (float) $first[1];
    }

    return (
      ($tokens_in / 1000000) * $in_rate
    ) + (
      ($tokens_out / 1000000) * $out_rate
    );
  }

  /**
   * Aggregate metrics for the dashboard.
   *
   * @param int $days Lookback window in days.
   * @return array
   */
  public static function summary($days = 30)
  {
    global $wpdb;

    $days = max(1, min(3650, absint($days)));

    $table = self::table();
    $since = gmdate(
      'Y-m-d H:i:s',
      time() - ($days * DAY_IN_SECONDS)
    );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated exclusively by self::table() and contains no user input.
    $totals = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT
					COUNT(*) AS requests,
					SUM(status = 'success') AS success,
					SUM(status = 'failed') AS failed,
					COALESCE(SUM(tokens_in), 0) AS tokens_in,
					COALESCE(SUM(tokens_out), 0) AS tokens_out,
					COALESCE(SUM(cost), 0) AS cost,
					COALESCE(
						AVG(
							CASE
								WHEN status = 'success'
								THEN duration_ms
							END
						),
						0
					) AS avg_duration
				FROM {$table}
				WHERE created_at >= %s",
        $since
      )
    );

    if (! $totals) {
      return array(
        'requests'     => 0,
        'success'      => 0,
        'failed'       => 0,
        'success_rate' => 0,
        'tokens_in'    => 0,
        'tokens_out'   => 0,
        'cost'         => 0,
        'avg_duration' => 0,
      );
    }

    $requests = (int) $totals->requests;
    $success  = (int) $totals->success;

    return array(
      'requests'     => $requests,
      'success'      => $success,
      'failed'       => (int) $totals->failed,
      'success_rate' => $requests > 0
        ? round(100 * $success / $requests, 1)
        : 0,
      'tokens_in'    => (int) $totals->tokens_in,
      'tokens_out'   => (int) $totals->tokens_out,
      'cost'         => round((float) $totals->cost, 4),
      'avg_duration' => round((float) $totals->avg_duration),
    );
  }

  /**
   * Per-provider breakdown.
   *
   * @param int $days Lookback window in days.
   * @return array
   */
  public static function by_provider($days = 30)
  {
    global $wpdb;

    $days = max(1, min(3650, absint($days)));

    $table = self::table();
    $since = gmdate(
      'Y-m-d H:i:s',
      time() - ($days * DAY_IN_SECONDS)
    );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated exclusively by self::table() and contains no user input.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
					provider,
					COUNT(*) AS requests,
					SUM(status = 'success') AS success,
					COALESCE(SUM(tokens_in), 0) AS tokens_in,
					COALESCE(SUM(tokens_out), 0) AS tokens_out,
					COALESCE(SUM(cost), 0) AS cost
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY provider
				ORDER BY requests DESC",
        $since
      )
    );

    if (! $rows) {
      return array();
    }

    return array_map('get_object_vars', $rows);
  }

  /**
   * Daily usage series for the chart.
   *
   * @param int $days Lookback window in days.
   * @return array
   */
  public static function daily($days = 14)
  {
    global $wpdb;

    $days = max(1, min(3650, absint($days)));

    $table = self::table();
    $since = gmdate(
      'Y-m-d',
      time() - (($days - 1) * DAY_IN_SECONDS)
    );

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated exclusively by self::table() and contains no user input.
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
					DATE(created_at) AS day,
					COUNT(*) AS requests,
					SUM(status = 'success') AS success,
					COALESCE(SUM(cost), 0) AS cost
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY day ASC",
        $since . ' 00:00:00'
      )
    );

    $data        = array();
    $rows_by_day = array();

    if ($rows) {
      foreach ($rows as $row) {
        $rows_by_day[$row->day] = $row;
      }
    }

    for ($i = $days - 1; $i >= 0; $i--) {
      $day = gmdate(
        'Y-m-d',
        time() - ($i * DAY_IN_SECONDS)
      );

      $row = isset($rows_by_day[$day])
        ? $rows_by_day[$day]
        : null;

      $data[] = array(
        'day'      => $day,
        'requests' => $row ? (int) $row->requests : 0,
        'success'  => $row ? (int) $row->success : 0,
        'cost'     => $row ? round((float) $row->cost, 4) : 0,
      );
    }

    return $data;
  }

  /**
   * Count generated items by output type for dashboard KPIs.
   *
   * @return array
   */
  public static function generated_totals()
  {
    global $wpdb;

    $articles = $wpdb->get_var(
      "SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm
				ON p.ID = pm.post_id
			WHERE p.post_type = 'kriti_ai_article'
			AND pm.meta_key = '_kriti_ai_generated'
			AND pm.meta_value = '1'"
    );

    $media = $wpdb->get_results(
      "SELECT
				im.meta_value AS item_type,
				COUNT(DISTINCT p.ID) AS total
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS generated_meta
				ON p.ID = generated_meta.post_id
			INNER JOIN {$wpdb->postmeta} AS im
				ON p.ID = im.post_id
			WHERE p.post_type = 'kriti_ai_media'
			AND generated_meta.meta_key = '_kriti_ai_generated'
			AND generated_meta.meta_value = '1'
			AND im.meta_key = '_kriti_ai_item_type'
			GROUP BY im.meta_value"
    );

    $totals = array(
      'articles' => (int) $articles,
      'images'   => 0,
      'audio'    => 0,
      'videos'   => 0,
    );

    if ($media) {
      foreach ($media as $row) {
        if ('image' === $row->item_type) {
          $totals['images'] = (int) $row->total;
        } elseif ('audio' === $row->item_type) {
          $totals['audio'] = (int) $row->total;
        } elseif ('video' === $row->item_type) {
          $totals['videos'] = (int) $row->total;
        }
      }
    }

    return $totals;
  }

  /**
   * Count draft vs published generated items.
   *
   * @return array
   */
  public static function draft_vs_published()
  {
    global $wpdb;

    $media = $wpdb->get_row(
      "SELECT
				COALESCE(
					SUM(pm.meta_value = 'draft'),
					0
				) AS draft,
				COALESCE(
					SUM(pm.meta_value = 'published'),
					0
				) AS published
			FROM {$wpdb->postmeta} AS pm
			INNER JOIN {$wpdb->posts} AS p
				ON p.ID = pm.post_id
			WHERE pm.meta_key = '_kriti_ai_state'
			AND p.post_type IN (
				'kriti_ai_media',
				'kriti_ai_article'
			)"
    );

    if (! $media) {
      return array(
        'draft'     => 0,
        'published' => 0,
      );
    }

    return array(
      'draft'     => (int) $media->draft,
      'published' => (int) $media->published,
    );
  }
}
