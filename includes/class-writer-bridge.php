<?php

/**
 * Bridges Kriti AI Writer's PlatformContract to the local Kriti AI providers.
 *
 * Only loaded when Kriti AI Writer is active (see kriti_ai_writer_platform
 * filter in kriti-ai.php). Runs generation synchronously since jobs execute
 * in-process rather than through a remote queue.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Writer_Bridge
 */
class Writer_Bridge implements \KritiAI\Writer\Platform\PlatformContract
{
  /**
   * How long finished job results stay available to Writer (seconds).
   */
  const JOB_TTL = HOUR_IN_SECONDS;

  /**
   * Minimum output budget per Writer operation. AI Studio's Max tokens is
   * tuned for short generations; Writer sections and reviews need more room,
   * and reasoning models spend part of the budget before writing.
   */
  const MIN_TOKENS = array(
    'generate_outline'    => 2048,
    'generate_section'    => 4096,
    'ai_review'           => 4096,
    'action_expand'       => 4096,
    'action_generate_faq' => 4096,
  );

  /**
   * Floor for operations not listed in MIN_TOKENS.
   */
  const DEFAULT_MIN_TOKENS = 2048;

  /**
   * Characters sent to the audio provider per request. TTS APIs cap a single
   * request (OpenAI at 4096 characters), so longer text is split.
   */
  const PODCAST_CHUNK_CHARS = 4000;

  /**
   * Longest narration accepted for one podcast.
   */
  const PODCAST_MAX_CHARS = 60000;

  /**
   * {@inheritDoc}
   */
  public function isAvailable()
  {
    return '' !== Provider_Manager::default_provider('text');
  }

  /**
   * {@inheritDoc}
   */
  public function getStatus()
  {
    return array(
      'authenticated' => true,
      'mode'          => 'local-plugin',
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getCapabilities()
  {
    return array(
      'text_generation'  => ! empty(Provider_Manager::enabled_slugs('text')),
      'image_generation' => ! empty(Provider_Manager::enabled_slugs('image')),
      'audio_generation' => ! empty(Provider_Manager::enabled_slugs('audio')),
      'video_generation' => ! empty(Provider_Manager::enabled_slugs('video')),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function listPrompts()
  {
    $posts = get_posts(
      array(
        'post_type'      => Post_Types::PROMPT,
        'post_status'    => array('publish', 'private', 'draft'),
        'posts_per_page' => 100,
        'orderby'        => 'title',
        'order'          => 'ASC',
      )
    );

    $prompts = array();

    foreach ($posts as $post) {
      if ($this->is_text_prompt($post)) {
        $prompts[] = array(
          'id'   => (string) $post->ID,
          'name' => get_the_title($post),
        );
      }
    }

    return $prompts;
  }

  /**
   * {@inheritDoc}
   */
  public function getPrompt($prompt_id)
  {
    $post = $this->prompt_post($prompt_id);

    if (! $post) {
      return array();
    }

    return array(
      'id'       => (string) $post->ID,
      'name'     => get_the_title($post),
      'content'  => (string) $post->post_content,
      'provider' => (string) get_post_meta($post->ID, 'kriti_ai_provider', true),
      'model'    => (string) get_post_meta($post->ID, 'kriti_ai_model', true),
    );
  }

  /**
   * {@inheritDoc}
   *
   * Runs the generation immediately and caches the result under a
   * generated job id, since local providers have no async queue.
   */
  public function createJob($operation, $payload)
  {
    $job_id      = wp_generate_uuid4();

    // Podcasts use the audio providers, not the text provider below.
    if ('generate_podcast' === $operation) {
      return $this->create_podcast_job($job_id, $payload);
    }

    $prompt_post = $this->prompt_post(isset($payload['prompt_id']) ? $payload['prompt_id'] : '');
    $slug        = $this->resolve_provider($payload, $prompt_post);
    $provider    = $slug ? Provider_Manager::get($slug) : null;

    if (! $provider || ! Provider_Manager::is_ready($provider)) {
      $this->store_job(
        $job_id,
        array(
          'status' => 'failed',
          'error'  => array('message' => __('No text AI provider is configured in Kriti AI.', 'kriti-ai')),
        )
      );

      return array('job_id' => $job_id, 'status' => 'failed');
    }

    $prompt = $this->build_prompt($operation, $payload);

    // A Prompt Library entry chosen in Writer adds its instructions.
    if ($prompt_post && '' !== trim($prompt_post->post_content)) {
      $prompt = "Follow these instructions:\n" . trim($prompt_post->post_content) . "\n\n" . $prompt;
    }

    // Values chosen in Writer's "Kriti AI Settings" panel override the Settings page defaults.
    $settings = Settings::get();
    $params   = array(
      'temperature' => (float) $this->setting($payload, $settings, 'temperature', 0.7),
      'max_tokens'  => $this->max_tokens($operation, (int) $this->setting($payload, $settings, 'max_tokens', 1024)),
      'top_p'       => (float) $this->setting($payload, $settings, 'top_p', 1.0),
      'top_k'       => (int) $this->setting($payload, $settings, 'top_k', 0),
    );

    // Model: one sent by Writer, else the chosen prompt's model when it targets this provider.
    $prompt_model = $prompt_post ? (string) get_post_meta($prompt_post->ID, 'kriti_ai_model', true) : '';
    $prompt_slug  = $prompt_post ? sanitize_key(get_post_meta($prompt_post->ID, 'kriti_ai_provider', true)) : '';

    if (isset($payload['model']) && '' !== (string) $payload['model']) {
      $params['model'] = (string) $payload['model'];
    } elseif ('' !== $prompt_model && $prompt_slug === $slug) {
      $params['model'] = $prompt_model;
    }

    $result = $provider->generate_text($prompt, $params);

    if (is_wp_error($result)) {
      $this->store_job(
        $job_id,
        array(
          'status' => 'failed',
          'error'  => array('message' => $result->get_error_message()),
        )
      );

      return array('job_id' => $job_id, 'status' => 'failed');
    }

    $this->store_job(
      $job_id,
      array(
        'status' => 'completed',
        'result' => array('content' => $result['content']),
        'usage'  => array(
          'provider'      => $slug,
          'model'         => isset($result['model']) ? $result['model'] : '',
          'input_tokens'  => isset($result['tokens_in']) ? (int) $result['tokens_in'] : 0,
          'output_tokens' => isset($result['tokens_out']) ? (int) $result['tokens_out'] : 0,
        ),
      )
    );

    return array('job_id' => $job_id, 'status' => 'completed');
  }

  /**
   * {@inheritDoc}
   */
  public function getJob($job_id)
  {
    $job = get_transient($this->job_key($job_id));

    if (is_array($job)) {
      return $job;
    }

    return array(
      'status' => 'failed',
      'error'  => array('message' => __('This AI job has expired or does not exist. Please run it again.', 'kriti-ai')),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function cancelJob($job_id)
  {
    $this->store_job($job_id, array('status' => 'cancelled'));

    return array('status' => 'cancelled');
  }

  /**
   * {@inheritDoc}
   */
  public function getUsage($filters = array())
  {
    return array();
  }

  /**
   * Generation choices Writer can offer in the editor: enabled text
   * providers with their models, and the Settings page defaults.
   *
   * Not part of PlatformContract (remote platforms do not offer it); Writer
   * checks for this method before showing its settings panel.
   *
   * @return array
   */
  public function getGenerationOptions()
  {
    $providers = array();

    foreach (Provider_Manager::enabled_slugs('text') as $slug) {
      $providers[] = array(
        'slug'          => $slug,
        'label'         => Provider_Manager::label($slug),
        'models'        => array_values(Provider_Manager::model_choices($slug)),
        'default_model' => Provider_Manager::default_model($slug, Settings::provider($slug)),
        // Model ids that are open-ended get a free-text field in the editor.
        'free_model'    => Provider_Manager::has_model_setting($slug) || 'openrouter' === $slug,
      );
    }

    $settings = Settings::get();

    return array(
      'providers'        => $providers,
      'default_provider' => Provider_Manager::default_provider('text'),
      'defaults'         => array(
        'temperature' => (float) $settings['temperature'],
        'max_tokens'  => (int) $settings['max_tokens'],
        'top_p'       => (float) $settings['top_p'],
        'top_k'       => (int) $settings['top_k'],
      ),
    );
  }

  /**
   * {@inheritDoc}
   *
   * Local generation stays on this site, so content sharing is allowed and
   * nothing is retained beyond the transient job cache.
   */
  public function getDataStorageSettings()
  {
    return array(
      'allow_content_sent' => true,
      'store_prompts'      => false,
    );
  }

  /**
   * Builds a provider prompt for the requested Writer operation.
   *
   * @param string $operation Operation slug.
   * @param array  $payload   Job payload from Writer.
   *
   * @return string
   */
  private function build_prompt($operation, $payload)
  {
    if ('generate_outline' === $operation) {
      $title        = isset($payload['title']) ? (string) $payload['title'] : '';
      $instructions = isset($payload['instructions']) ? (string) $payload['instructions'] : '';

      return sprintf(
        "Create an outline for a blog post titled \"%s\".\n\nInstructions: %s\n\nRespond with one markdown heading (##) per section, followed by a one-line description of that section.",
        $title,
        $instructions
      );
    }

    if ('action_generate_faq' === $operation) {
      $content     = isset($payload['content']) ? wp_strip_all_tags((string) $payload['content']) : '';
      $instruction = isset($payload['instruction']) ? trim((string) $payload['instruction']) : '';

      return "Write a FAQ section for the article below: 4 to 6 questions a reader is likely to ask, each answered in 1-3 sentences using only information from the article.\n"
        . ('' !== $instruction ? 'Additional instructions: ' . $instruction . "\n" : '')
        . 'Respond with only a JSON object, no other text, in exactly this shape: {"faqs": [{"question": "...", "answer": "..."}]}' . "\n"
        . "Keep the same language as the article.\n\nArticle:\n" . $content;
    }

    if ('action_summarize' === $operation) {
      $content     = isset($payload['content']) ? wp_strip_all_tags((string) $payload['content']) : '';
      $instruction = isset($payload['instruction']) ? trim((string) $payload['instruction']) : '';

      $prompt = "Write a concise summary (2-4 sentences) of the text below. Capture its main points in neutral, clear language.\n"
        . ('' !== $instruction ? 'Additional instructions: ' . $instruction . "\n" : '');

      // From a block's toolbar, offer alternatives like the other block actions.
      if (isset($payload['section_id']) && 0 === strpos((string) $payload['section_id'], 'block:')) {
        $prompt .= "Write exactly 3 different summaries. Put each on its own line, starting with \"Option 1:\", \"Option 2:\" and \"Option 3:\". No other text, headings or commentary.\n";
      } else {
        $prompt .= "Respond with only the summary as plain text: no heading, no introduction, no markdown.\n";
      }

      return $prompt . "Keep the same language as the text.\n\nText:\n" . $content;
    }

    if ('ai_review' === $operation) {
      $context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : array();
      $title   = isset($context['article_title']) ? (string) $context['article_title'] : '';
      $content = isset($context['content']) ? (string) $context['content'] : '';
      $outline = isset($context['outline']) && is_array($context['outline']) ? implode("\n- ", array_map('strval', $context['outline'])) : '';

      return "You are an experienced editor. Review the blog post below for SEO, readability, structure, engagement and overall quality.\n\n"
        . 'Title: ' . ('' !== $title ? $title : '(untitled)') . "\n"
        . ('' !== $outline ? "Outline:\n- " . $outline . "\n" : '')
        . "\nArticle:\n" . ('' !== trim($content) ? $content : '(empty)') . "\n\n"
        . "Respond with only a JSON object, no other text, in exactly this shape:\n"
        . '{"score": 0-100, "categories": {"seo": 0-100, "readability": 0-100, "structure": 0-100, "engagement": 0-100, "quality": 0-100}, '
        . '"issues": [{"severity": "high|medium|low", "message": "specific problem"}], "suggestions": ["specific, actionable improvement"]}' . "\n"
        . 'Give at most 8 issues and 8 suggestions, most important first. Score honestly: an empty or very short article should score low.';
    }

    if ('generate_section' === $operation) {
      $context      = isset($payload['context']) ? wp_json_encode($payload['context']) : '';
      $instructions = isset($payload['instructions']) ? (string) $payload['instructions'] : '';

      return sprintf(
        "Write the content for one section of a blog post.\n\nSection context: %s\n\nInstructions: %s\n\nRespond with well-formed HTML or markdown for this section only.",
        $context,
        $instructions
      );
    }

    $content     = isset($payload['content']) ? (string) $payload['content'] : '';
    $instruction = isset($payload['instruction']) ? (string) $payload['instruction'] : '';
    $label       = str_replace('action_', '', (string) $operation);

    $editing = $this->editing_instruction($label, $payload);

    if ('' !== $editing) {
      $prompt = $editing . "\n";

      if ('' !== trim($instruction)) {
        $prompt .= 'Additional instructions: ' . trim($instruction) . "\n";
      }

      // From a block's toolbar, offer alternatives to choose from.
      if (isset($payload['section_id']) && 0 === strpos((string) $payload['section_id'], 'block:')) {
        $prompt .= "Write exactly 3 different versions. Put each on its own line, starting with \"Option 1:\", \"Option 2:\" and \"Option 3:\". "
          . "Each option must be a complete replacement for the text. No other text, headings or commentary.\n";
      } else {
        $prompt .= "Respond with only the resulting text: no options, introduction or commentary.\n";
      }

      return $prompt . "Keep the same language as the text.\n\nText:\n" . $content;
    }

    $prompt = sprintf("Perform the following editorial operation: %s\n\n", $label);

    if ('' !== $instruction) {
      $prompt .= $instruction . "\n\n";
    }

    if ('' !== $content) {
      $prompt .= "Content:\n" . $content;
    }

    return $prompt;
  }

  /**
   * Transient key for a cached job result.
   *
   * @param string $job_id Job id.
   *
   * @return string
   */
  private function job_key($job_id)
  {
    return 'kriti_ai_writer_job_' . md5((string) $job_id);
  }

  /**
   * Caches a job result.
   *
   * @param string $job_id Job id.
   * @param array  $data   Job data.
   *
   * @return void
   */
  private function store_job($job_id, $data)
  {
    set_transient($this->job_key($job_id), $data, self::JOB_TTL);
  }

  /**
   * Picks the text provider for a job: one requested by Writer, then the
   * chosen prompt's provider, as long as it is enabled; otherwise the default.
   *
   * @param array         $payload     Job payload.
   * @param \WP_Post|null $prompt_post Chosen Prompt Library entry.
   *
   * @return string Provider slug, or '' when none is available.
   */
  private function resolve_provider($payload, $prompt_post)
  {
    $enabled    = Provider_Manager::enabled_slugs('text');
    $candidates = array(
      isset($payload['provider']) ? sanitize_key($payload['provider']) : '',
      $prompt_post ? sanitize_key(get_post_meta($prompt_post->ID, 'kriti_ai_provider', true)) : '',
    );

    foreach ($candidates as $candidate) {
      if ('' !== $candidate && in_array($candidate, $enabled, true)) {
        return $candidate;
      }
    }

    return Provider_Manager::default_provider('text');
  }

  /**
   * Output token budget for an operation: AI Studio's Max tokens, raised to
   * the operation's minimum.
   *
   * @param string $operation  Operation slug.
   * @param int    $max_tokens Configured Max tokens.
   *
   * @return int
   */
  private function max_tokens($operation, $max_tokens)
  {
    $floor = isset(self::MIN_TOKENS[$operation]) ? self::MIN_TOKENS[$operation] : self::DEFAULT_MIN_TOKENS;

    /**
     * Filter the token budget for a Writer operation.
     *
     * @param int    $tokens    Budget.
     * @param string $operation Operation slug.
     */
    return (int) apply_filters('kriti_ai_writer_bridge_max_tokens', max($max_tokens, $floor), $operation);
  }

  /**
   * Loads a Prompt Library entry usable for text by id.
   *
   * @param string|int $prompt_id Prompt post id.
   *
   * @return \WP_Post|null
   */
  private function prompt_post($prompt_id)
  {
    $post = absint($prompt_id) ? get_post(absint($prompt_id)) : null;

    return $post && Post_Types::PROMPT === $post->post_type && $this->is_text_prompt($post) ? $post : null;
  }

  /**
   * Whether a prompt is meant for text (content) generation.
   *
   * @param \WP_Post $post Prompt post.
   *
   * @return bool
   */
  private function is_text_prompt($post)
  {
    $type = (string) get_post_meta($post->ID, 'kriti_ai_item_type', true);

    return '' === $type || 'content' === $type;
  }

  /**
   * A numeric generation setting: the job's own value when Writer sent one,
   * else the Settings page value, else a fallback.
   *
   * @param array  $payload  Job payload.
   * @param array  $settings Kriti AI settings.
   * @param string $key      Setting key.
   * @param mixed  $fallback Fallback value.
   *
   * @return mixed
   */
  private function setting($payload, $settings, $key, $fallback)
  {
    if (isset($payload[$key]) && is_numeric($payload[$key])) {
      return $payload[$key];
    }

    return isset($settings[$key]) ? $settings[$key] : $fallback;
  }

  /**
   * Generates a podcast (spoken audio) for a text with the default audio
   * provider and saves it to the Media Library, attached to the post.
   *
   * Long text is split at sentence boundaries, each part is synthesized and
   * the parts are joined into one file (MP3 or WAV).
   *
   * @param string $job_id  Job id.
   * @param array  $payload Job payload: text, post_id, title.
   *
   * @return array
   */
  private function create_podcast_job($job_id, $payload)
  {
    $fail = function ($message) use ($job_id) {
      $this->store_job($job_id, array('status' => 'failed', 'error' => array('message' => $message)));

      return array('job_id' => $job_id, 'status' => 'failed');
    };

    $slug     = Provider_Manager::default_provider('audio');
    $provider = $slug ? Provider_Manager::get($slug) : null;

    if (! $provider || ! Provider_Manager::is_ready($provider)) {
      return $fail(__('No audio AI provider is configured in Kriti AI.', 'kriti-ai'));
    }

    $text = isset($payload['text']) ? trim((string) $payload['text']) : '';

    if ('' === $text) {
      return $fail(__('There is no text to turn into audio.', 'kriti-ai'));
    }

    if (mb_strlen($text) > self::PODCAST_MAX_CHARS) {
      return $fail(
        sprintf(
          /* translators: %d: maximum number of characters. */
          __('The text is too long for one podcast (maximum %d characters).', 'kriti-ai'),
          self::PODCAST_MAX_CHARS
        )
      );
    }

    // Several provider calls can take a while.
    if (function_exists('set_time_limit')) {
      @set_time_limit(600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Hosts may refuse.
    }

    $settings = Settings::get();
    $params   = array(
      'voice'            => (string) $settings['audio_voice'],
      'speed'            => (float) $settings['audio_speed'],
      'stability'        => (float) $settings['audio_stability'],
      'similarity_boost' => (float) $settings['audio_similarity_boost'],
      'format'           => 'mp3',
    );

    $chunks    = $this->split_text($text, self::PODCAST_CHUNK_CHARS);
    $parts     = array();
    $extension = '';
    $mime      = '';

    foreach ($chunks as $index => $chunk) {
      $result = $provider->synthesize_audio($chunk, $params);

      if (is_wp_error($result)) {
        return $fail(
          count($chunks) > 1
            ? sprintf(
              /* translators: 1: part number, 2: number of parts, 3: provider error. */
              __('Audio part %1$d of %2$d failed: %3$s', 'kriti-ai'),
              $index + 1,
              count($chunks),
              $result->get_error_message()
            )
            : $result->get_error_message()
        );
      }

      $parts[]   = (string) file_get_contents($result['tmp_path']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local provider temp file.
      $extension = strtolower((string) $result['extension']);
      $mime      = (string) $result['mime'];
      wp_delete_file($result['tmp_path']);
    }

    $audio = $this->join_audio($parts, $extension);

    if (is_wp_error($audio)) {
      return $fail($audio->get_error_message());
    }

    $post_id       = isset($payload['post_id']) ? (int) $payload['post_id'] : 0;
    $title         = isset($payload['title']) && '' !== (string) $payload['title'] ? (string) $payload['title'] : __('AI podcast', 'kriti-ai');
    $attachment_id = $this->save_audio_attachment($audio, $extension, $mime, $title, $post_id);

    if (is_wp_error($attachment_id)) {
      return $fail($attachment_id->get_error_message());
    }

    $this->store_job(
      $job_id,
      array(
        'status' => 'completed',
        'result' => array(
          'attachment_id' => $attachment_id,
          'url'           => wp_get_attachment_url($attachment_id),
          'mime'          => get_post_mime_type($attachment_id),
          'parts'         => count($chunks),
        ),
        'usage'  => array(
          'provider' => $slug,
          'model'    => (string) $provider->get_config()['model'],
        ),
      )
    );

    return array('job_id' => $job_id, 'status' => 'completed');
  }

  /**
   * Splits text into pieces of at most $limit characters, at sentence
   * boundaries where possible (then at word boundaries).
   *
   * @param string $text  Text.
   * @param int    $limit Max characters per piece.
   *
   * @return string[]
   */
  private function split_text($text, $limit)
  {
    $sentences = preg_split('/(?<=[.!?…])\s+|\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $chunks    = array();
    $current   = '';

    foreach ($sentences as $sentence) {
      $sentence = trim($sentence);

      // A single sentence longer than the limit is split by words.
      while (mb_strlen($sentence) > $limit) {
        $cut      = mb_strrpos(mb_substr($sentence, 0, $limit), ' ');
        $cut      = $cut ? $cut : $limit;
        $chunks[] = trim(mb_substr($sentence, 0, $cut));
        $sentence = trim(mb_substr($sentence, $cut));
      }

      if ('' !== $current && mb_strlen($current) + 1 + mb_strlen($sentence) > $limit) {
        $chunks[] = $current;
        $current  = '';
      }

      $current = '' === $current ? $sentence : $current . ' ' . $sentence;
    }

    if ('' !== $current) {
      $chunks[] = $current;
    }

    return $chunks;
  }

  /**
   * Joins audio parts produced by the same provider into one file.
   *
   * @param string[] $parts     Raw file contents.
   * @param string   $extension File extension of every part.
   *
   * @return string|\WP_Error
   */
  private function join_audio($parts, $extension)
  {
    if (1 === count($parts)) {
      return $parts[0];
    }

    if ('mp3' === $extension) {
      $joined = '';

      foreach ($parts as $index => $part) {
        // Drop the ID3 tag of every part but the first; the MPEG frames join directly.
        if ($index > 0 && 'ID3' === substr($part, 0, 3) && strlen($part) > 10) {
          $size = ((ord($part[6]) & 0x7f) << 21) | ((ord($part[7]) & 0x7f) << 14) | ((ord($part[8]) & 0x7f) << 7) | (ord($part[9]) & 0x7f);
          $part = substr($part, 10 + $size);
        }

        $joined .= $part;
      }

      return $joined;
    }

    if ('wav' === $extension) {
      $format = null;
      $data   = '';

      foreach ($parts as $part) {
        $chunks = $this->wav_chunks($part);

        if (! $chunks) {
          return new \WP_Error('kriti_ai_audio_join', __('Could not read a generated WAV file.', 'kriti-ai'));
        }

        if (null !== $format && $format !== $chunks['fmt']) {
          return new \WP_Error('kriti_ai_audio_join', __('The audio parts use different formats and cannot be joined.', 'kriti-ai'));
        }

        $format = $chunks['fmt'];
        $data  .= $chunks['data'];
      }

      return 'RIFF' . pack('V', 4 + 8 + strlen($format) + 8 + strlen($data)) . 'WAVE'
        . 'fmt ' . pack('V', strlen($format)) . $format
        . 'data' . pack('V', strlen($data)) . $data;
    }

    return new \WP_Error(
      'kriti_ai_audio_join',
      sprintf(
        /* translators: %s: audio file extension. */
        __('This text is too long for a single %s file from this provider. Use an MP3 or WAV voice, or a shorter text.', 'kriti-ai'),
        strtoupper($extension)
      )
    );
  }

  /**
   * Reads the fmt and data chunks of a WAV file.
   *
   * @param string $wav WAV contents.
   *
   * @return array{fmt: string, data: string}|null
   */
  private function wav_chunks($wav)
  {
    if ('RIFF' !== substr($wav, 0, 4) || 'WAVE' !== substr($wav, 8, 4)) {
      return null;
    }

    $chunks = array();
    $offset = 12;
    $length = strlen($wav);

    while ($offset + 8 <= $length) {
      $id   = substr($wav, $offset, 4);
      $size = unpack('V', substr($wav, $offset + 4, 4))[1];

      $chunks[trim($id)] = substr($wav, $offset + 8, $size);
      $offset           += 8 + $size + ($size % 2);
    }

    return isset($chunks['fmt'], $chunks['data']) ? array('fmt' => $chunks['fmt'], 'data' => $chunks['data']) : null;
  }

  /**
   * Saves audio to the Media Library, attached to a post.
   *
   * @param string $audio     File contents.
   * @param string $extension File extension.
   * @param string $mime      Mime type.
   * @param string $title     Attachment title.
   * @param int    $post_id   Parent post id.
   *
   * @return int|\WP_Error Attachment id.
   */
  private function save_audio_attachment($audio, $extension, $mime, $title, $post_id)
  {
    $upload = wp_upload_bits(sanitize_title($title) . '-' . gmdate('Ymd-His') . '.' . $extension, null, $audio);

    if (! empty($upload['error'])) {
      return new \WP_Error('kriti_ai_upload_error', $upload['error']);
    }

    $attachment_id = wp_insert_attachment(
      array(
        'post_mime_type' => trim(explode(';', $mime)[0]),
        'post_title'     => sanitize_text_field($title),
        'post_status'    => 'inherit',
      ),
      $upload['file'],
      $post_id
    );

    if (is_wp_error($attachment_id) || ! $attachment_id) {
      return new \WP_Error('kriti_ai_media_error', __('Could not add the audio to the Media Library.', 'kriti-ai'));
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
    update_post_meta($attachment_id, '_kriti_ai_generated', 1);

    return (int) $attachment_id;
  }

  /**
   * What an editing action asks the model to do, or '' for other actions.
   *
   * @param string $label   Action slug (e.g. rewrite, change_tone).
   * @param array  $payload Job payload.
   *
   * @return string
   */
  private function editing_instruction($label, $payload)
  {
    switch ($label) {
      case 'rewrite':
        return 'Rewrite the text below in fresh wording, keeping its meaning.';
      case 'improve':
        return 'Improve the text below: fix grammar and spelling, and make it clearer and more fluent while keeping its meaning.';
      case 'expand':
        return 'Expand the text below with more detail, explanation or examples, keeping its meaning and style.';
      case 'change_tone':
        $tone = isset($payload['tone']) ? trim(wp_strip_all_tags((string) $payload['tone'])) : '';

        return '' !== $tone
          ? sprintf('Rewrite the text below in a %s tone, keeping its meaning.', $tone)
          : 'Rewrite the text below in a different tone, keeping its meaning.';
      default:
        return '';
    }
  }
}
