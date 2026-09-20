<?php

/**
 * Audio provider interface.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Interface Audio_Provider
 */
interface Audio_Provider
{

	/**
	 * Synthesize speech from text.
	 *
	 * @param string $text The text to convert to speech.
	 * @param array  $params Extra parameters (voice, speed, model, etc.).
	 * @return array{
	 *   tmp_path: string,
	 *   mime: string,
	 *   extension: string
	 * }
	 */
	public function synthesize_audio($text, $params);
}
