<?php

/**
 * Image provider interface.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Interface Image_Provider
 */
interface Image_Provider
{

	/**
	 * Generate an image from a prompt.
	 *
	 * @param string $prompt Description of the image to generate.
	 * @param array  $params Extra parameters (resolution, aspect_ratio, style, etc.).
	 * @return array{
	 *   tmp_path: string,
	 *   mime: string,
	 *   extension: string
	 * }|\WP_Error
	 */
	public function generate_image($prompt, $params);
}
