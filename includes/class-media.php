<?php

/**
 * Media pipeline: store generated files in the uploads folder and
 * register them as draft attachments until published.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Media
 */
class Media
{


  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action('transition_post_status', array(__CLASS__, 'maybeCopyArticleToTarget'), 10, 3);
  }

  /**
   * Copy AI articles when they are published through native WordPress controls.
   *
   * @param string  $new_status New post status.
   * @param string  $old_status Old post status.
   * @param WP_Post $post Post object.
   * @return void
   */
  public static function maybeCopyArticleToTarget($new_status, $old_status, $post)
  {
    if ('publish' !== $new_status || 'publish' === $old_status || ! $post || Post_Types::ARTICLE !== $post->post_type) {
      return;
    }

    $target = isset($_POST['kriti_ai_target_post_type']) ? sanitize_key(wp_unslash($_POST['kriti_ai_target_post_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read during native publish transition; save handler verifies nonce.
    $copy_id = self::copyArticleToTarget((int) $post->ID, $target);

    if (is_wp_error($copy_id)) {
      return;
    }

    update_post_meta($post->ID, '_kriti_ai_state', 'published');
    update_post_meta($post->ID, 'state', 'published');
    update_post_meta($post->ID, 'migrated_post_id', (int) $copy_id);
    update_post_meta($post->ID, '_kriti_ai_migrated_post_id', (int) $copy_id);
    do_action('kriti_ai_content_published', $post->ID);
  }

  /**
   * Store a generated file in the WordPress uploads directory.
   *
   * @param string $tmp_path Absolute path to the temporary file.
   * @param string $mime Mime type.
   * @param string $extension File extension without dot.
   * @param array  $params Generation parameters (used for naming).
   * @param int    $job_id Associated job id.
   * @param string $item_type image|audio|video.
   * @return int|WP_Error Media post id.
   */
  public static function store($tmp_path, $mime, $extension, $params, $job_id, $item_type)
  {
    if (! is_readable($tmp_path)) {
      return new \WP_Error('KRITI_AI_FILE_missing', __('The generated file could not be read.', 'kriti-ai'));
    }

    $contents = file_get_contents($tmp_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local provider-generated temporary file.

    if (false === $contents || '' === $contents) {
      return new \WP_Error('KRITI_AI_FILE_empty', __('The generated file is empty.', 'kriti-ai'));
    }

    $title = isset($params['title']) ? sanitize_title($params['title']) : 'kriti-ai-' . $item_type;
    $title = $title ? $title : 'kriti-ai-' . $item_type;

    $filename = sprintf(
      '%s-%s.%s',
      $title,
      gmdate('Ymd-His'),
      $extension
    );

    $upload = wp_upload_bits($filename, null, $contents);

    if (! empty($upload['error'])) {
      return new \WP_Error('kriti_ai_upload_error', $upload['error']);
    }

    $file = $upload['file'];

    $media_id = wp_insert_post(
      array(
        'post_type'    => Post_Types::MEDIA,
        'post_title'   => isset($params['title']) ? sanitize_text_field($params['title']) : ucfirst($item_type) . ' ' . $job_id,
        'post_content' => '',
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
      )
    );

    if (is_wp_error($media_id) || ! $media_id) {
      return new \WP_Error('kriti_ai_media_error', __('Could not register the media item.', 'kriti-ai'));
    }

    update_post_meta($media_id, 'file', $file);
    update_post_meta($media_id, 'url', $upload['url']);
    update_post_meta($media_id, 'mime', $mime);
    update_post_meta($media_id, 'extension', sanitize_key($extension));

    do_action('kriti_ai_media_stored', $media_id, $job_id);

    return (int) $media_id;
  }

  /**
   * Get the publish state of a generated item.
   *
   * @param int $item_id Attachment or post id.
   * @return string draft|published|unknown
   */
  public static function state($item_id)
  {
    $post = get_post($item_id);

    if (! $post) {
      return 'unknown';
    }

    if (Post_Types::MEDIA === $post->post_type || 'attachment' === $post->post_type) {
      $state = get_post_meta($item_id, '_kriti_ai_state', true);
      return in_array($state, array('draft', 'published'), true) ? $state : 'unknown';
    }

    if (in_array($post->post_type, array(Post_Types::ARTICLE, 'post'), true)) {
      return ('publish' === $post->post_status) ? 'published' : 'draft';
    }

    return 'unknown';
  }

  /**
   * Publish a generated item.
   *
   * Attachments flip a state meta; generated posts flip post_status.
   *
   * @param int $item_id Attachment or post id.
   * @return bool|WP_Error
   */
  public static function publish($item_id)
  {
    $post = get_post($item_id);

    if (! $post) {
      return new \WP_Error('kriti_ai_missing_item', __('Item not found.', 'kriti-ai'));
    }

    if (Post_Types::MEDIA === $post->post_type) {
      $attachment_id = self::ensure_attachment($item_id);

      if (is_wp_error($attachment_id)) {
        return $attachment_id;
      }

      wp_update_post(
        array(
          'ID'          => $item_id,
          'post_status' => 'publish',
        )
      );
      update_post_meta($item_id, '_kriti_ai_state', 'published');
      update_post_meta($item_id, 'state', 'published');
      update_post_meta($item_id, '_kriti_ai_attachment_id', (int) $attachment_id);
      update_post_meta($item_id, 'attachment_id', (int) $attachment_id);
      do_action('kriti_ai_media_published', $item_id);
      return true;
    }

    if ('attachment' === $post->post_type) {
      update_post_meta($item_id, '_kriti_ai_state', 'published');
      update_post_meta($item_id, 'state', 'published');
      do_action('kriti_ai_media_published', $item_id);
      return true;
    }

    if (Post_Types::ARTICLE === $post->post_type) {
      $copy_id = self::copyArticleToTarget($item_id);

      if (is_wp_error($copy_id)) {
        return $copy_id;
      }

      update_post_meta($item_id, 'migrated_post_id', (int) $copy_id);
      update_post_meta($item_id, '_kriti_ai_migrated_post_id', (int) $copy_id);

      wp_update_post(
        array(
          'ID'          => $item_id,
          'post_status' => 'publish',
        )
      );
      update_post_meta($item_id, '_kriti_ai_state', 'published');
      update_post_meta($item_id, 'state', 'published');
      do_action('kriti_ai_content_published', $item_id);
      return true;
    }

    if ('post' === $post->post_type && 'publish' !== $post->post_status) {
      wp_update_post(
        array(
          'ID'          => $item_id,
          'post_status' => 'publish',
        )
      );
      update_post_meta($item_id, '_kriti_ai_state', 'published');
      update_post_meta($item_id, 'state', 'published');
      do_action('kriti_ai_content_published', $item_id);
      return true;
    }

    return new \WP_Error('kriti_ai_not_generated', __('This item cannot be published from Kriti AI.', 'kriti-ai'));
  }

  /**
   * Unpublish a generated item (move back to draft).
   *
   * @param int $item_id Attachment or post id.
   * @return bool|WP_Error
   */
  public static function unpublish($item_id)
  {
    $post = get_post($item_id);

    if (! $post) {
      return new \WP_Error('kriti_ai_missing_item', __('Item not found.', 'kriti-ai'));
    }

    if (Post_Types::MEDIA === $post->post_type) {
      wp_update_post(
        array(
          'ID'          => $item_id,
          'post_status' => 'draft',
        )
      );
      update_post_meta($item_id, '_kriti_ai_state', 'draft');
      update_post_meta($item_id, 'state', 'draft');
      return true;
    }

    if ('attachment' === $post->post_type) {
      update_post_meta($item_id, '_kriti_ai_state', 'draft');
      update_post_meta($item_id, 'state', 'draft');
      return true;
    }

    if (in_array($post->post_type, array(Post_Types::ARTICLE, 'post'), true) && 'publish' === $post->post_status) {
      wp_update_post(
        array(
          'ID'          => $item_id,
          'post_status' => 'draft',
        )
      );
      update_post_meta($item_id, '_kriti_ai_state', 'draft');
      update_post_meta($item_id, 'state', 'draft');
      return true;
    }

    return new \WP_Error('kriti_ai_not_generated', __('This item cannot be unpublished.', 'kriti-ai'));
  }

  /**
   * Delete a generated media item and its linked Media Library attachment.
   *
   * @param int $item_id Media post or attachment id.
   * @return bool|WP_Error
   */
  public static function delete($item_id)
  {
    $post = get_post($item_id);

    if (! $post) {
      return new \WP_Error('kriti_ai_missing_item', __('Item not found.', 'kriti-ai'));
    }

    if ('attachment' === $post->post_type) {
      return (bool) wp_delete_attachment($item_id, true);
    }

    if (Post_Types::MEDIA !== $post->post_type) {
      return new \WP_Error('kriti_ai_not_generated', __('This media item cannot be deleted from Kriti AI.', 'kriti-ai'));
    }

    $attachment_id = (int) get_post_meta($item_id, '_kriti_ai_attachment_id', true);
    $file = (string) get_post_meta($item_id, 'file', true);

    if ($attachment_id && get_post($attachment_id)) {
      wp_delete_attachment($attachment_id, true);
    } elseif ($file && file_exists($file)) {
      wp_delete_file($file);
    }

    return (bool) wp_delete_post($item_id, true);
  }

  /**
   * Ensure a generated media post has a matching Media Library attachment.
   *
   * @param int $media_id Generated media post id.
   * @return int|WP_Error Attachment id.
   */
  private static function ensure_attachment($media_id)
  {
    $attachment_id = (int) get_post_meta($media_id, '_kriti_ai_attachment_id', true);

    if ($attachment_id && get_post($attachment_id)) {
      return $attachment_id;
    }

    $file = (string) get_post_meta($media_id, 'file', true);
    $mime = (string) get_post_meta($media_id, 'mime', true);

    if (! $file || ! is_readable($file)) {
      return new \WP_Error('KRITI_AI_FILE_missing', __('The generated file could not be read.', 'kriti-ai'));
    }

    $attachment_id = wp_insert_attachment(
      array(
        'post_mime_type' => $mime,
        'post_title'     => get_the_title($media_id),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => get_current_user_id(),
      ),
      $file,
      0
    );

    if (is_wp_error($attachment_id) || ! $attachment_id) {
      return new \WP_Error('kriti_ai_attachment_error', __('Could not register the media attachment.', 'kriti-ai'));
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $metadata = wp_generate_attachment_metadata($attachment_id, $file);
    wp_update_attachment_metadata($attachment_id, $metadata);

    update_post_meta($attachment_id, '_kriti_ai_generated', 1);
    update_post_meta($attachment_id, '_kriti_ai_source_media_id', (int) $media_id);
    update_post_meta($attachment_id, '_kriti_ai_state', 'published');
    update_post_meta($attachment_id, '_kriti_ai_item_type', get_post_meta($media_id, 'type', true));
    update_post_meta($attachment_id, '_kriti_ai_provider', get_post_meta($media_id, 'provider', true));
    update_post_meta($attachment_id, '_kriti_ai_job_id', (int) get_post_meta($media_id, '_kriti_ai_job_id', true));

    $attachment_url = wp_get_attachment_url($attachment_id);

    if ($attachment_url) {
      update_post_meta($media_id, 'url', $attachment_url);
    }

    return (int) $attachment_id;
  }

  /**
   * Copy a generated AI article to the selected public post type.
   *
   * @param int    $article_id AI article post id.
   * @param string $target_post_type Optional target post type override.
   * @return int|WP_Error Copied post id.
   */
  public static function copyArticleToTarget($article_id, $target_post_type = '')
  {
    $article = get_post($article_id);

    if (! $article || Post_Types::ARTICLE !== $article->post_type) {
      return new \WP_Error('kriti_ai_not_article', __('This item is not an AI article.', 'kriti-ai'));
    }

    $target = $target_post_type ? sanitize_key($target_post_type) : get_post_meta($article_id, 'target_post_type', true);
    $target = $target ? sanitize_key($target) : 'post';
    $post_types = get_post_types(array('public' => true, 'show_ui' => true), 'names');

    if (!in_array($target, $post_types, true) || 'attachment' === $target) {
      $target = 'post';
    }

    $existing_id = (int) get_post_meta($article_id, 'migrated_post_id', true);
    $existing = $existing_id ? get_post($existing_id) : null;

    if ($existing && $target === $existing->post_type) {
      update_post_meta($article_id, 'migrated_post_type', $target);
      update_post_meta($article_id, '_kriti_ai_migrated_post_type', $target);
      return $existing_id;
    }

    $copy_id = wp_insert_post(
      array(
        'post_type'    => $target,
        'post_status'  => 'draft',
        'post_title'   => $article->post_title,
        'post_content' => $article->post_content,
        'post_author'  => (int) $article->post_author,
      )
    );

    if (is_wp_error($copy_id) || ! $copy_id) {
      return new \WP_Error('kriti_ai_copy_failed', __('Could not copy the article to the selected post type.', 'kriti-ai'));
    }

    update_post_meta($copy_id, '_kriti_ai_generated', 1);
    update_post_meta($copy_id, '_kriti_ai_source_article_id', (int) $article_id);
    update_post_meta($copy_id, '_kriti_ai_provider', get_post_meta($article_id, 'provider', true));
    update_post_meta($copy_id, '_kriti_ai_model', get_post_meta($article_id, 'model', true));
    update_post_meta($copy_id, '_kriti_ai_job_id', (int) get_post_meta($article_id, '_kriti_ai_job_id', true));

    update_post_meta($article_id, 'migrated_post_type', $target);
    update_post_meta($article_id, '_kriti_ai_migrated_post_type', $target);

    return (int) $copy_id;
  }
}
