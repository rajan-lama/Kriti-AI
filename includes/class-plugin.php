<?php

/**
 * Main plugin class. Wires every component together.
 *
 * @package KritiAI
 */

namespace KritiAI;

/**
 * Class Plugin
 */
final class Plugin
{

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything into WordPress.
	 *
	 * @return void
	 */
	public function register()
	{
		Assets::register();
		Post_Types::register();
		Settings::register();
		Admin::register();
		Ajax::register();
		Queue::register();
		Worker::register();
		Metrics::register();
		Generator::register();
		Media::register();
		Provider_Manager::register();
	}
}
