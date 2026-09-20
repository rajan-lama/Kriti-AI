<?php

/**
 * PSR-4 style autoloader for the Kriti AI plugin.
 *
 * Maps KritiAI\Class names to includes/<subdir>/class-<kebab>.php
 *
 * @package KritiAI
 */

if (! defined('ABSPATH')) exit;

spl_autoload_register(
  function ($class_name) {
    $prefix = 'KritiAI\\';

    if (0 !== strpos($class_name, $prefix)) {
      return;
    }

    $relative = substr($class_name, strlen($prefix));
    $parts    = explode('\\', $relative);
    $name     = array_pop($parts);

    $name = str_replace('_', '-', $name);
    $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
    $name = strtolower($name);

    $directory = KRITI_AI_DIR . 'includes';
    if (! empty($parts)) {
      $directory .= '/' . strtolower(implode('/', $parts));
    }

    $name = strtolower($name);

    $files = array(
      $directory . '/class-' . $name . '.php',
      $directory . '/interface-' . $name . '.php',
    );

    foreach ($files as $file) {
      if (is_readable($file)) {
        require_once $file;
        return;
      }
    }
  }
);
