<?php

/**
 * Custom post types.
 *
 * @package KritiAI
 */

namespace KritiAI;

if (! defined('ABSPATH')) exit;

/**
 * Class Post_Types
 */
class Post_Types
{

  /**
   * The prompt library post type.
   *
   * @var string
   */
  const PROMPT = 'kriti_ai_prompt';

  /**
   * The article post type.
   *
   * @var string
   */
  const ARTICLE = 'kriti_ai_article';

  /**
   * The media post type.
   *
   * @var string
   */
  const MEDIA = 'kriti_ai_media';

  /**
   * The video post type.
   *
   * @var string
   */
  const VIDEO = 'kriti_ai_video';

  /**
   * The audio post type.
   *
   * @var string
   */
  const AUDIO = 'kriti_ai_audio';

  /**
   * Register hooks.
   *
   * @return void
   */
  public static function register()
  {
    add_action('init', array(__CLASS__, 'register_prompt_type'));
    add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
    add_action('save_post_' . self::PROMPT, array(__CLASS__, 'save_meta'));
    add_action('save_post_' . self::ARTICLE, array(__CLASS__, 'save_article_meta'));
  }

  /**
   * Register the kriti_ai_prompt custom post type.
   *
   * @return void
   */
  public static function register_prompt_type()
  {
    register_post_type(
      self::PROMPT,
      array(
        'labels' => array(
          'name' => __('Prompt Library', 'kriti-ai'),
          'singular_name' => __('AI Prompt', 'kriti-ai'),
          'add_new' => __('Add New Prompt', 'kriti-ai'),
          'add_new_item' => __('Add New Prompt', 'kriti-ai'),
          'edit_item' => __('Edit Prompt', 'kriti-ai'),
          'new_item' => __('New Prompt', 'kriti-ai'),
          'view_item' => __('View Prompt', 'kriti-ai'),
          'search_items' => __('Search Prompts', 'kriti-ai'),
          'not_found' => __('No prompts found', 'kriti-ai'),
          'not_found_in_trash' => __('No prompts found in Trash', 'kriti-ai'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'kriti-ai',
        'supports' => array('title', 'editor'),
        'rewrite' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
      )
    );

    register_post_type(
      self::ARTICLE,
      array(
        'labels' => array(
          'name' => __('Content Library', 'kriti-ai'),
          'singular_name' => __('AI Article', 'kriti-ai'),
          'add_new' => __('Add New Article', 'kriti-ai'),
          'add_new_item' => __('Add New Article', 'kriti-ai'),
          'edit_item' => __('Edit Article', 'kriti-ai'),
          'new_item' => __('New Article', 'kriti-ai'),
          'view_item' => __('View Article', 'kriti-ai'),
          'search_items' => __('Search Articles', 'kriti-ai'),
          'not_found' => __('No articles found', 'kriti-ai'),
          'not_found_in_trash' => __('No articles found in Trash', 'kriti-ai'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'kriti-ai',
        'show_in_rest' => true,
        'supports' => array('title', 'editor'),
        'rewrite' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
      )
    );

    register_post_type(
      self::MEDIA,
      array(
        'labels' => array(
          'name' => __('Media Library', 'kriti-ai'),
          'singular_name' => __('AI Media', 'kriti-ai'),
          'add_new' => __('Add New Media', 'kriti-ai'),
          'add_new_item' => __('Add New Media', 'kriti-ai'),
          'edit_item' => __('Edit Media', 'kriti-ai'),
          'new_item' => __('New Media', 'kriti-ai'),
          'view_item' => __('View Media', 'kriti-ai'),
          'search_items' => __('Search Media', 'kriti-ai'),
          'not_found' => __('No media found', 'kriti-ai'),
          'not_found_in_trash' => __('No media found in Trash', 'kriti-ai'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'kriti-ai',
        'supports' => array('title'),
        'rewrite' => false,
        'capability_type' => 'post',
        'map_meta_cap' => true,
      )
    );
  }

  /**
   * Register the meta box for prompt parameters.
   *
   * @return void
   */
  public static function add_meta_boxes()
  {
    add_meta_box(
      'kriti_ai_prompt_params',
      __('AI Generation Settings', 'kriti-ai'),
      array(__CLASS__, 'render_meta_box'),
      self::PROMPT,
      'normal',
      'high'
    );

    add_meta_box(
      'kriti_ai_article_publish_target',
      __('Publish Target', 'kriti-ai'),
      array(__CLASS__, 'render_article_meta_box'),
      self::ARTICLE,
      'side',
      'default'
    );

    foreach (array(self::ARTICLE, self::MEDIA) as $postType) {
      add_meta_box(
        'kriti_ai_generated_data',
        __('Generated Data', 'kriti-ai'),
        array(__CLASS__, 'render_generated_data_meta_box'),
        $postType,
        'normal',
        'default'
      );
    }
  }

  /**
   * Render generated item metadata for admin visibility.
   *
   * @param WP_Post $post Current post.
   * @return void
   */
  public static function render_generated_data_meta_box($post)
  {
    $fields = array(
      'type' => __('Type', 'kriti-ai'),
      'state' => __('State', 'kriti-ai'),
      'provider' => __('Provider', 'kriti-ai'),
      'model' => __('Model', 'kriti-ai'),
      '_kriti_ai_job_id' => __('Job ID', 'kriti-ai'),
      'target_post_type' => __('Target post type', 'kriti-ai'),
      'migrated_post_type' => __('Migrated post type', 'kriti-ai'),
      'migrated_post_id' => __('Migrated post ID', 'kriti-ai'),
      'attachment_id' => __('Attachment ID', 'kriti-ai'),
      'url' => __('URL', 'kriti-ai'),
      'file' => __('File', 'kriti-ai'),
      'mime' => __('MIME type', 'kriti-ai'),
      'extension' => __('Extension', 'kriti-ai'),
    );

    if (self::ARTICLE === $post->post_type) {
      unset($fields['attachment_id'], $fields['url'], $fields['file'], $fields['mime'], $fields['extension']);
    } elseif (self::MEDIA === $post->post_type) {
      unset($fields['target_post_type'], $fields['migrated_post_type'], $fields['migrated_post_id']);
    }

    $rows = array();

    foreach ($fields as $key => $label) {
      $value = get_post_meta($post->ID, $key, true);

      if ('' === (string) $value) {
        continue;
      }

      $rows[$key] = array(
        'label' => $label,
        'value' => $value,
      );
    }

    if (empty($rows)) {
      echo '<p>' . esc_html__('No generated data is available yet.', 'kriti-ai') . '</p>';
      return;
    }
?>
    <table class="widefat striped">
      <tbody>
        <?php foreach ($rows as $key => $row) : ?>
          <tr>
            <th scope="row" style="width: 220px;"><?php echo esc_html($row['label']); ?></th>
            <td><?php self::render_generated_meta_value($key, $row['value']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php
  }

  /**
   * Render a generated metadata value.
   *
   * @param string $key Meta key.
   * @param mixed  $value Meta value.
   * @return void
   */
  private static function render_generated_meta_value($key, $value)
  {
    if ('' === (string) $value) {
      echo esc_html('—');
      return;
    }

    if ('url' === $key) {
      printf(
        '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
        esc_url($value),
        esc_html($value)
      );
      return;
    }

    if (in_array($key, array('migrated_post_id', 'attachment_id'), true)) {
      $editLink = get_edit_post_link((int) $value);

      if ($editLink) {
        printf(
          '<a href="%1$s">#%2$d</a>',
          esc_url($editLink),
          (int) $value
        );
        return;
      }
    }

    echo esc_html($value);
  }

  /**
   * Render article publish target meta box.
   *
   * @param WP_Post $post Current post.
   * @return void
   */
  public static function render_article_meta_box($post)
  {
    wp_nonce_field('kriti_ai_article_meta', 'kriti_ai_article_meta_nonce');

    $selected = get_post_meta($post->ID, 'target_post_type', true);
    $selected = $selected ? $selected : 'post';

    $post_types = get_post_types(
      array(
        'public'  => true,
        'show_ui' => true,
      ),
      'objects'
    );

    unset($post_types['attachment']);
  ?>
    <p>
      <label for="kriti_ai_target_post_type"><?php esc_html_e('Copy to post type on publish', 'kriti-ai'); ?></label>
    </p>
    <select name="kriti_ai_target_post_type" id="kriti_ai_target_post_type" class="widefat">
      <?php foreach ($post_types as $post_type => $object) : ?>
        <option value="<?php echo esc_attr($post_type); ?>" <?php selected($selected, $post_type); ?>>
          <?php echo esc_html($object->labels->singular_name); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="description">
      <?php esc_html_e('Publishing this AI article creates a draft copy in the selected post type.', 'kriti-ai'); ?>
    </p>
  <?php
  }

  /**
   * Render the prompt parameters meta box.
   *
   * @param WP_Post $post Current post.
   * @return void
   */
  public static function render_meta_box($post)
  {
    wp_nonce_field('kriti_ai_prompt_meta', 'kriti_ai_prompt_meta_nonce');

    $item_type = get_post_meta($post->ID, 'kriti_ai_item_type', true);
    $provider = get_post_meta($post->ID, 'kriti_ai_provider', true);
    $model = get_post_meta($post->ID, 'kriti_ai_model', true);
    $temperature = get_post_meta($post->ID, 'kriti_ai_temperature', true);
    $max_tokens = get_post_meta($post->ID, 'kriti_ai_max_tokens', true);

    $item_types = array(
      'content' => __('Content (title + body)', 'kriti-ai'),
      'image' => __('Image (text to image)', 'kriti-ai'),
      'audio' => __('Audio (text to speech)', 'kriti-ai'),
      'video' => __('Video (text to video)', 'kriti-ai'),
    );
  ?>
    <table class="form-table">
      <tr>
        <th><label for="kriti_ai_meta_item_type"><?php esc_html_e('Item type', 'kriti-ai'); ?></label></th>
        <td>
          <select name="kriti_ai_item_type" id="kriti_ai_meta_item_type">
            <?php foreach ($item_types as $value => $label): ?>
              <option value="<?php echo esc_attr($value); ?>" <?php selected($item_type, $value); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="kriti_ai_meta_provider"><?php esc_html_e('Provider', 'kriti-ai'); ?></label></th>
        <td>
          <input type="text" name="kriti_ai_provider" id="kriti_ai_meta_provider" value="<?php echo esc_attr($provider); ?>"
            class="regular-text" placeholder="openai" />
        </td>
      </tr>
      <tr>
        <th><label for="kriti_ai_meta_model"><?php esc_html_e('Model', 'kriti-ai'); ?></label></th>
        <td>
          <input type="text" name="kriti_ai_model" id="kriti_ai_meta_model" value="<?php echo esc_attr($model); ?>"
            class="regular-text" placeholder="gpt-4o-mini" />
        </td>
      </tr>
      <tr data-kriti-ai-text-setting>
        <th><label for="kriti_ai_meta_temperature"><?php esc_html_e('Temperature', 'kriti-ai'); ?></label></th>
        <td>
          <input type="number" name="kriti_ai_temperature" id="kriti_ai_meta_temperature" min="0" max="2" step="0.1"
            value="<?php echo esc_attr($temperature); ?>" class="small-text" />
        </td>
      </tr>
      <tr data-kriti-ai-text-setting>
        <th><label for="kriti_ai_meta_max_tokens"><?php esc_html_e('Max tokens', 'kriti-ai'); ?></label></th>
        <td>
          <input type="number" name="kriti_ai_max_tokens" id="kriti_ai_meta_max_tokens" min="1" step="1"
            value="<?php echo esc_attr($max_tokens); ?>" class="small-text" />
        </td>
      </tr>
    </table>
<?php
  }

  /**
   * Save prompt parameters.
   *
   * @param int $post_id Post id.
   * @return void
   */
  public static function save_meta($post_id)
  {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (!isset($_POST['kriti_ai_prompt_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kriti_ai_prompt_meta_nonce'])), 'kriti_ai_prompt_meta')) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $meta = array(
      'kriti_ai_item_type' => isset($_POST['kriti_ai_item_type']) ? sanitize_text_field(wp_unslash($_POST['kriti_ai_item_type'])) : 'content',
      'kriti_ai_provider' => isset($_POST['kriti_ai_provider']) ? sanitize_text_field(wp_unslash($_POST['kriti_ai_provider'])) : '',
      'kriti_ai_model' => isset($_POST['kriti_ai_model']) ? sanitize_text_field(wp_unslash($_POST['kriti_ai_model'])) : '',
      'kriti_ai_temperature' => isset($_POST['kriti_ai_temperature']) ? sanitize_text_field(wp_unslash($_POST['kriti_ai_temperature'])) : '',
      'kriti_ai_max_tokens' => isset($_POST['kriti_ai_max_tokens']) ? sanitize_text_field(wp_unslash($_POST['kriti_ai_max_tokens'])) : '',
    );

    self::save_prompt_meta($post_id, $meta);
  }

  /**
   * Save generated article publish target metadata.
   *
   * @param int $post_id Post id.
   * @return void
   */
  public static function save_article_meta($post_id)
  {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (!isset($_POST['kriti_ai_article_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kriti_ai_article_meta_nonce'])), 'kriti_ai_article_meta')) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $target = isset($_POST['kriti_ai_target_post_type']) ? sanitize_key(wp_unslash($_POST['kriti_ai_target_post_type'])) : 'post';
    $post_types = get_post_types(array('public' => true, 'show_ui' => true), 'names');

    if (!in_array($target, $post_types, true) || 'attachment' === $target) {
      $target = 'post';
    }

    update_post_meta($post_id, 'target_post_type', $target);

    $migrated_id = (int) get_post_meta($post_id, 'migrated_post_id', true);
    $migrated_post_type = get_post_meta($post_id, 'migrated_post_type', true);

    if ('publish' === get_post_status($post_id) && (!$migrated_id || $migrated_post_type !== $target)) {
      $copy_id = call_user_func(array(__NAMESPACE__ . '\\Media', 'publish'), $post_id);

      if (is_wp_error($copy_id)) {
        return;
      }
    }
  }

  /**
   * Save default meta for a prompt post.
   *
   * @param int $post_id Post ID.
   * @param array $meta Meta to save.
   * @return void
   */
  public static function save_prompt_meta($post_id, $meta)
  {
    $allowed = array(
      'kriti_ai_provider' => 'sanitize_text_field',
      'kriti_ai_model' => 'sanitize_text_field',
      'kriti_ai_temperature' => 'sanitize_text_field',
      'kriti_ai_max_tokens' => 'sanitize_text_field',
      'kriti_ai_item_type' => 'sanitize_text_field',
    );

    foreach ($allowed as $key => $sanitize) {
      $value = isset($meta[$key]) ? call_user_func($sanitize, $meta[$key]) : '';
      update_post_meta($post_id, $key, $value);
    }
  }
}


/**
 * Add custom columns to KritiAI Article admin list.
 */
add_filter('manage_kriti_ai_article_posts_columns', function ($columns) {

  $new_columns = array();

  foreach ($columns as $key => $label) {

    $new_columns[$key] = $label;

    // Add custom columns after the title.
    if ($key === 'title') {
      $new_columns['provider'] = __('Provider', 'kriti-ai');
      $new_columns['model'] = __('Model', 'kriti-ai');
      $new_columns['post_status'] = __('Status', 'kriti-ai');
      $new_columns['state'] = __('State', 'kriti-ai');
    }
  }

  return $new_columns;
});


/**
 * Display custom column values.
 */
add_action(
  'manage_kriti_ai_article_posts_custom_column',
  function ($column, $post_id) {

    switch ($column) {

      case 'post_status':
        $status = get_post_status($post_id);

        echo esc_html(
          $status ? ucfirst($status) : '—'
        );
        break;

      case 'migrated_post_type':
        $value = get_post_meta(
          $post_id,
          'migrated_post_type',
          true
        );

        $migrated_id = (int) get_post_meta(
          $post_id,
          'migrated_post_id',
          true
        );

        if ($value && $migrated_id && get_edit_post_link($migrated_id)) {
          printf(
            '<a href="%1$s">%2$s #%3$d</a>',
            esc_url(get_edit_post_link($migrated_id)),
            esc_html($value),
            (int) $migrated_id
          );
          break;
        }

        echo esc_html(
          $value ?: '—'
        );
        break;

      case 'provider':
        $value = get_post_meta(
          $post_id,
          'provider',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
      case 'model':
        $value = get_post_meta(
          $post_id,
          'model',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
      case 'state':
        $value = get_post_meta(
          $post_id,
          'state',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
    }
  },
  10,
  2
);

/**
 * Add custom columns to kriti Article admin list.
 */
add_filter('manage_kriti_ai_media_posts_columns', function ($columns) {

  $new_columns = array();

  foreach ($columns as $key => $label) {

    $new_columns[$key] = $label;

    // Add custom columns after the title.
    if ($key === 'title') {
      $new_columns['type'] = __('Type', 'kriti-ai');
      $new_columns['provider'] = __('Provider', 'kriti-ai');
      $new_columns['model'] = __('Model', 'kriti-ai');
      $new_columns['post_status'] = __('Status', 'kriti-ai');
      $new_columns['state'] = __('State', 'kriti-ai');
    }
  }

  return $new_columns;
});


/**
 * Display custom column values.
 */
add_action(
  'manage_kriti_ai_media_posts_custom_column',
  function ($column, $post_id) {

    switch ($column) {

      case 'post_status':
        $status = get_post_status($post_id);

        echo esc_html($status ? ucfirst($status) : '—');
        break;

      case 'type':
        $value = get_post_meta(
          $post_id,
          'type',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;

      case 'migrated_post_type':
        $value = get_post_meta(
          $post_id,
          'migrated_post_type',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;

      case 'provider':
        $value = get_post_meta(
          $post_id,
          'provider',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
      case 'model':
        $value = get_post_meta(
          $post_id,
          'model',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
      case 'state':
        $value = get_post_meta(
          $post_id,
          'state',
          true
        );

        echo esc_html(
          $value ?: '—'
        );
        break;
    }
  },
  10,
  2
);

add_filter('post_row_actions', function ($actions, $post) {
  if (! $post || Post_Types::MEDIA !== $post->post_type) {
    return $actions;
  }

  $state = get_post_meta($post->ID, 'state', true);
  $is_published = 'published' === $state;

  $actions['kriti_ai_publish_state'] = sprintf(
    '<a href="#" class="%1$s" data-id="%2$d">%3$s</a>',
    esc_attr($is_published ? 'kriti-ai-unpublish' : 'kriti-ai-publish'),
    (int) $post->ID,
    esc_html($is_published ? __('Unpublish', 'kriti-ai') : __('Publish', 'kriti-ai'))
  );
  $actions['kriti_ai_delete_media'] = sprintf(
    '<a href="#" class="kriti-ai-delete-media submitdelete" data-id="%1$d">%2$s</a>',
    (int) $post->ID,
    esc_html__('Delete', 'kriti-ai')
  );

  return $actions;
}, 10, 2);
