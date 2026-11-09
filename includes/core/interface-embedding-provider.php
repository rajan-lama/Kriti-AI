<?php

/**
 * Embedding provider interface.
 *
 * Implemented by providers that can turn text into vectors, next to their other capabilities.
 *
 * @package KritiAI
 */

namespace KritiAI\Core;

/**
 * Interface Embedding_Provider
 */
interface Embedding_Provider
{

	/**
	 * Turn texts into vectors.
	 *
	 * @param string[] $texts Texts to embed, in order.
	 * @param array    $params Optional: model, dimensions.
	 * @return array{
	 *   vectors: float[][],
	 *   dimensions: int,
	 *   tokens_in: int,
	 *   model: string
	 * }|\WP_Error One vector per text, in the same order.
	 */
	public function generate_embeddings($texts, $params);
}
