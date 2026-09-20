<?php

/**
 * Orchestrates generation results: creates drafts, stores media,
 * and records metrics.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Generator
 */
class Generator
{

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    // Static service class; nothing to hook.
  }

  /**
   * Finalize a successful text generation job.
   *
   * @param object $job Job row.
   * @param array  $result Provider result.
   * @param array  $params Job parameters.
   * @return void
   */
  public static function finalize_text($job, $result, $params)
  {
    $content = isset($result['content']) ? (string) $result['content'] : '';
    $mode    = isset($params['mode']) ? $params['mode'] : 'text';

    if (defined('WP_DEBUG') && true === WP_DEBUG) {
      error_log(
        sprintf(
          "[Kriti AI] Generated text for job %d (%s/%s):\n%s",
          (int) $job->id,
          (string) $job->provider,
          isset($result['model']) ? (string) $result['model'] : (string) $job->model,
          $content
        )
      );
    }

    $output  = array(
      'content' => $content,
      'model'   => isset($result['model']) ? $result['model'] : $job->model,
    );

    $post_id = 0;

    if ('post' === $mode) {
      list($title, $body) = self::split_title_content($content);
      $post_type           = Post_Types::ARTICLE;
      $item_type           = 'article';

      $post_id = wp_insert_post(
        array(
          'post_type'    => $post_type,
          'post_status'  => 'draft',
          'post_title'   => $title,
          'post_content' => $body,
          'post_author'  => self::current_user_id(),
        )
      );

      if ($post_id && ! is_wp_error($post_id)) {
        self::save_generated_meta($post_id, $job, $params, $item_type, 'draft');

        $output['post_id']  = (int) $post_id;
        $output['title']    = $title;
        $output['body']     = $body;
        $output['edit_url'] = get_edit_post_link($post_id, 'raw');
        $output['view_url'] = get_preview_post_link($post_id);
      }
    }

    Queue::update(
      $job->id,
      array(
        'status'   => 'completed',
        'progress' => 100,
        'result'   => wp_json_encode($output),
      )
    );

    Metrics::record_success(
      $job,
      'text',
      array(
        'in'  => isset($result['tokens_in']) ? (int) $result['tokens_in'] : 0,
        'out' => isset($result['tokens_out']) ? (int) $result['tokens_out'] : 0,
      )
    );
  }

  /**
   * Finalize a successful audio or video generation job.
   *
   * @param object $job Job row.
   * @param array  $result Provider result (tmp_path etc.).
   * @param string $item_type audio|video.
   * @param array  $params Job parameters.
   * @return void
   */
  public static function finalize_media($job, $result, $item_type, $params)
  {
    $media_id = Media::store(
      $result['tmp_path'],
      $result['mime'],
      $result['extension'],
      $params,
      $job->id,
      $item_type
    );

    if (is_wp_error($media_id)) {
      self::fail_job($job, $media_id->get_error_message());
      return;
    }

    self::save_generated_meta($media_id, $job, $params, $item_type, 'draft');

    $output = array(
      'post_id'       => (int) $media_id,
      'attachment_id' => (int) $media_id,
      'file'          => get_post_meta($media_id, 'file', true),
      'url'           => get_post_meta($media_id, 'url', true),
      'mime'          => $result['mime'],
      'title'         => isset($params['title']) ? $params['title'] : ucfirst($item_type),
    );

    Queue::update(
      $job->id,
      array(
        'status'   => 'completed',
        'progress' => 100,
        'result'   => wp_json_encode($output),
      )
    );

    Metrics::record_success(
      $job,
      $item_type,
      array(
        'in'  => 0,
        'out' => 0,
      )
    );
  }

  /**
   * Save shared metadata for generated Kriti AI items.
   *
   * @param int    $post_id Post ID.
   * @param object $job Job row.
   * @param array  $params Generation params.
   * @param string $type Generated item type.
   * @param string $state Draft/published state.
   * @return void
   */
  private static function save_generated_meta($post_id, $job, $params, $type, $state)
  {
    $provider = isset($params['provider']) ? sanitize_key($params['provider']) : sanitize_key($job->provider);
    $model    = isset($params['model']) ? sanitize_text_field($params['model']) : sanitize_text_field($job->model);
    $type     = sanitize_key($type);
    $state    = sanitize_key($state);

    update_post_meta($post_id, '_kriti_ai_generated', 1);
    update_post_meta($post_id, '_kriti_ai_state', $state);
    update_post_meta($post_id, '_kriti_ai_item_type', $type);
    update_post_meta($post_id, '_kriti_ai_job_id', (int) $job->id);
    update_post_meta($post_id, '_kriti_ai_provider', $provider);
    update_post_meta($post_id, '_kriti_ai_model', $model);

    update_post_meta($post_id, 'type', $type);
    update_post_meta($post_id, 'state', $state);
    update_post_meta($post_id, 'provider', $provider);
    update_post_meta($post_id, 'model', $model);
  }

  /**
   * Build the prompt sent to a text provider when generating a blog post.
   *
   * @param string $user_prompt The instructions typed by the user.
   * @param array  $context Optional context values for tags.
   * @return string
   */
  public static function build_post_prompt($user_prompt, $context = array())
  {
    $processed_prompt = self::replace_variables($user_prompt, $context);
    $system_instructions = isset($context['system_instructions']) ? trim((string) $context['system_instructions']) : '';

    if ('' !== $system_instructions) {
      $processed_prompt = sprintf(
        /* translators: 1: system instructions, 2: user instructions */
        __("System instructions:\n%1\$s\n\nInstructions:\n%2\$s", 'kriti-ai'),
        $system_instructions,
        $processed_prompt
      );
    }

    return sprintf(
      /* translators: %s: user instructions */
      __(
        "You are an expert content writer. Write a high-quality blog post based on the instructions below.\n\nReturn ONLY a JSON object with exactly two keys: \"title\" and \"content\". The \"content\" value must be a single string of well-formed HTML (paragraphs, headings, lists). Do not include any text outside the JSON object.\n\nInstructions:\n%s",
        'kriti-ai'
      ),
      $processed_prompt
    );
  }

  /**
   * Replace dynamic variable placeholders in a prompt template.
   *
   * Supported placeholders: {{site_name}}, {{site_url}}, {{current_date}}, {{author}},
   * {{post_title}}, {{product_name}}, {{product_price}}, {{brand_voice}}, {{tone}}, {{keywords}}.
   *
   * @param string $prompt Raw prompt string with tags.
   * @param array  $context Context data overrides.
   * @return string
   */
  public static function replace_variables($prompt, $context = array())
  {
    $user = wp_get_current_user();

    $defaults = array(
      '{{site_name}}'       => get_bloginfo('name'),
      '{{site_url}}'        => home_url(),
      '{{current_date}}'    => wp_date('Y-m-d'),
      '{{author}}'          => $user && $user->ID ? $user->display_name : get_bloginfo('name'),
      '{{post_title}}'      => isset($context['post_title']) ? $context['post_title'] : '',
      '{{product_name}}'    => isset($context['product_name']) ? $context['product_name'] : '',
      '{{product_price}}'   => isset($context['product_price']) ? $context['product_price'] : '',
      '{{product_sku}}'     => isset($context['product_sku']) ? $context['product_sku'] : '',
      '{{brand_voice}}'     => isset($context['brand_voice']) ? $context['brand_voice'] : 'Professional, clear, authoritative',
      '{{tone}}'            => isset($context['tone']) ? $context['tone'] : 'Informative',
      '{{keywords}}'        => isset($context['keywords']) ? $context['keywords'] : '',
    );

    /**
     * Filter prompt variable replacements.
     *
     * @param array  $defaults Array of tag => replacement strings.
     * @param string $prompt Original prompt text.
     * @param array  $context Context values passed.
     */
    $replacements = apply_filters('kriti_ai_prompt_variables', $defaults, $prompt, $context);

    return str_replace(array_keys($replacements), array_values($replacements), (string) $prompt);
  }

  /**
   * Split generated content into a title and body.
   *
   * Tries JSON first, then falls back to first-line-as-title.
   *
   * @param string $content Provider output.
   * @return array{0: string, 1: string}
   */
  private static function split_title_content($content)
  {
    $decoded = self::normalize_ai_content($content);

    if (!is_array($decoded)) {
      return array(
        __('Untitled generation', 'kriti-ai'),
        '',
      );
    }

    // Structured response: title + content.
    if (!empty($decoded['title'])) {
      return array(
        trim(wp_strip_all_tags((string) $decoded['title'])),
        isset($decoded['content']) ? (string) $decoded['content'] : '',
      );
    }

    // Plain content response.
    $text = isset($decoded['content'])
      ? (string) $decoded['content']
      : '';

    if ('' === trim($text)) {
      return array(
        __('Untitled generation', 'kriti-ai'),
        '',
      );
    }

    // If the content starts with an H1, use it as the title
    // without modifying the actual HTML content.
    if (preg_match('/^\s*<h1\b[^>]*>(.*?)<\/h1>/is', $text, $matches)) {
      $title = trim(wp_strip_all_tags($matches[1]));

      return array(
        $title,
        $text,
      );
    }

    // Fallback: first non-empty line as title.
    $lines = preg_split('/\R/', $text);

    $title = '';
    foreach ($lines as $line) {
      $line = trim($line);

      if ('' !== $line) {
        $title = wp_strip_all_tags($line);
        break;
      }
    }

    if ('' === $title) {
      $title = __('Untitled generation', 'kriti-ai');
    }

    return array(
      $title,
      $text,
    );
  }

  public static function normalize_ai_content($content)
  {

    if (is_array($content)) {
      return $content;
    }

    if (is_object($content)) {
      return json_decode(json_encode($content), true);
    }

    if (is_string($content)) {
      $decoded = json_decode($content, true);

      if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
      }

      return [
        'content' => $content,
      ];
    }

    return null;
  }

  /**
   * Get the current user id, falling back to the job author when
   * running in the background.
   *
   * @return int
   */
  private static function current_user_id()
  {
    if (is_user_logged_in()) {
      return (int) get_current_user_id();
    }

    $admins = get_users(
      array(
        'role'    => 'administrator',
        'number'  => 1,
        'fields'  => 'ID',
        'orderby' => 'ID',
        'order'   => 'ASC',
      )
    );

    return ! empty($admins) ? (int) $admins[0] : 0;
  }

  /**
   * Mark a job failed with a message.
   *
   * @param object $job Job row.
   * @param string $error Error message.
   * @return void
   */
  private static function fail_job($job, $error)
  {
    Queue::update(
      $job->id,
      array(
        'status' => 'failed',
        'error'  => $error,
      )
    );
    Metrics::record_failure($job, $error);
  }
}
