<?php

/**
 * Text provider interface.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Interface Text_Provider
 */
interface Text_Provider
{

	/**
	 * Generate text (content, titles, replies).
	 *
	 * @param string $prompt The full prompt to send.
	 * @param array  $params Extra parameters (temperature, max_tokens, model, etc.).
	 * @return array{
	 *   content: string,
	 *   tokens_in: int,
	 *   tokens_out: int,
	 *   model: string
	 * }
	 */
	public function generate_text($prompt, $params);
}
