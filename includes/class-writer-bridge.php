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
  const JOB_TTL = 300;

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
    return array();
  }

  /**
   * {@inheritDoc}
   */
  public function getPrompt($prompt_id)
  {
    return array();
  }

  /**
   * {@inheritDoc}
   *
   * Runs the generation immediately and caches the result under a
   * generated job id, since local providers have no async queue.
   */
  public function createJob($operation, $payload)
  {
    $job_id = wp_generate_uuid4();
    $slug   = Provider_Manager::default_provider('text');
    $provider = $slug ? Provider_Manager::get($slug) : null;

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

    $settings = Settings::get();
    $params   = array(
      'temperature' => isset($settings['temperature']) ? (float) $settings['temperature'] : 0.7,
      'max_tokens'  => isset($settings['max_tokens']) ? (int) $settings['max_tokens'] : 1024,
      'top_p'       => isset($settings['top_p']) ? (float) $settings['top_p'] : 1.0,
    );

    if (isset($payload['model']) && '' !== (string) $payload['model']) {
      $params['model'] = (string) $payload['model'];
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

    return is_array($job) ? $job : array('status' => 'failed');
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
}
