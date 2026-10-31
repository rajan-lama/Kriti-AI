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

    // Try the plain lowercase name first (OpenAI_Text => openai-text), then a
    // CamelCase-split name (SomeClass => some-class).
    $names = array_unique(
      array(
        strtolower($name),
        strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name)),
      )
    );

    $directory = KRITI_AI_DIR . 'includes';
    if (! empty($parts)) {
      $directory .= '/' . strtolower(implode('/', $parts));
    }

    $files = array();
    foreach ($names as $candidate) {
      $files[] = $directory . '/class-' . $candidate . '.php';
      $files[] = $directory . '/interface-' . $candidate . '.php';
    }

    foreach ($files as $file) {
      if (is_readable($file)) {
        require_once $file;
        return;
      }
    }
  }
);
