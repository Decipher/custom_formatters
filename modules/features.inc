<?php
// $Id$
/**
 * @file
 * Provides Custom Formatters integration with the Features module.
 */

/**
 * Implements hook_features_api().
 */
function custom_formatters_features_api() {
  return array(
    'custom_formatters' => array(
      'name' => t('Custom Formatters'),
      'feature_source' => TRUE,
      'default_hook' => 'custom_formatters_defaults',
      'default_file' => FEATURES_DEFAULTS_INCLUDED,
    ),
  );
}

/**
 * Implements hook_features_export_options().
 */
function custom_formatters_features_export_options() {
  $formatters = array();

  foreach (custom_formatters_formatters() as $id => $formatter) {
    $formatters[$formatter->name] = $formatter->label;
  }

  return $formatters;
}

/**
 * Implements hook_features_export().
 */
function custom_formatters_features_export($data, &$export, $module_name = '') {
  $export['dependencies']['custom_formatters'] = 'custom_formatters';
  foreach ($data as $name) {
    $formatter = custom_formatters_formatter($name);

    $export['features']['custom_formatters'][$formatter->name] = $formatter->name;
  }
}

/**
 * Implements hook_features_export_render().
 */
function custom_formatters_features_export_render($module_name = '', $data) {
  $formatters = array();
  foreach ($data as $name) {
    $formatter = custom_formatters_formatter($name);
    $formatters[$formatter->name] = $formatter;
  }

  return array(
    'custom_formatters_defaults' => '  return ' . features_var_export($formatters, '  '),
  );
}
