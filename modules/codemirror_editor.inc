<?php

/**
 * @file
 * CodeMirror Editor integration for Custom Formatters.
 *
 * Loaded automatically by hook_module_implements_alter() when the
 * codemirror_editor module is installed.
 */

declare(strict_types=1);

/**
 * Implements hook_codemirror_mode_info_alter().
 */
function custom_formatters_codemirror_mode_info_alter(array &$modes): void {
  // Mark the modes our engines use as active so their JS files are always
  // loaded alongside the CodeMirror library.
  //
  // clike must be listed explicitly: getActiveModes() appends dependencies
  // after the declaring mode, so php.js would otherwise load before clike.js.
  // php.js calls CodeMirror.getMode(config, "clike") at define time, so if
  // clike.js has not loaded yet PHP highlighting silently breaks.
  foreach (['clike', 'php', 'htmlmixed', 'twig'] as $mode) {
    if (isset($modes[$mode])) {
      $modes[$mode]['usage'][] = 'custom_formatters';
    }
  }
}

/**
 * Implements hook_codemirror_editor_assets_alter().
 */
function custom_formatters_codemirror_editor_assets_alter(array &$assets): void {
  // Load the show-hint addon and hint sources for Ctrl+Space autocomplete.
  $assets['js'][] = 'addon/hint/show-hint.js';
  $assets['js'][] = 'addon/hint/anyword-hint.js';
  $assets['js'][] = 'addon/hint/xml-hint.js';
  // html-hint is required for CodeMirror.hint.html; xml-hint alone needs an
  // explicit schema and produces no completions for plain HTML content.
  $assets['js'][] = 'addon/hint/html-hint.js';
  $assets['css'][] = 'addon/hint/show-hint.css';
}

/**
 * Implements hook_library_info_alter().
 */
function custom_formatters_library_info_alter(array &$libraries, string $extension): void {
  // Require codemirror_editor/editor so CodeMirror instances exist in the
  // DOM before codemirror-hints.js runs its attach().
  if ($extension === 'custom_formatters' && isset($libraries['formatter_form'])
    && \Drupal::moduleHandler()->moduleExists('codemirror_editor')) {
    $libraries['formatter_form']['dependencies'][] = 'codemirror_editor/editor';
  }
}
