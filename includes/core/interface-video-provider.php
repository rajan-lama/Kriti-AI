<?php

/**
 * Video provider interface.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Interface Video_Provider
 */
interface Video_Provider
{

	/**
	 * Start video generation. May return immediately or be async.
	 *
	 * @param string $prompt The video description prompt.
	 * @param array  $params Extra parameters (duration, resolution, model, etc.).
	 * @return array{
	 *   status: string,
	 *   external_id?: string,
	 *   tmp_path?: string,
	 *   mime?: string,
	 *   extension?: string,
	 *   error?: string
	 * }
	 */
	public function start_video($prompt, $params);

	/**
	 * Poll an in-flight async video generation.
	 *
	 * @param string $external_id Provider-side job identifier.
	 * @param array  $params Extra parameters from the original request.
	 * @return array Same shape as start_video().
	 */
	public function poll_video($external_id, $params);
}
