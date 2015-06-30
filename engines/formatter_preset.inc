<?php

/**
 * @file
 * Formatter preset engine for Custom Formatters modules.
 */

/**
 * Implements custom_formatters_engine_hook_theme_alter().
 */
function custom_formatters_engine_formatter_preset_theme_alter(&$theme) {
  $theme['custom_formatters_formatter_preset_export'] = array(
    'variables' => array(
      'item'   => NULL,
      'module' => NULL,
    ),
    'template'  => 'formatter_preset.export',
    'path'      => drupal_get_path('module', 'custom_formatters') . '/engines',
  );
}

/**
 * Settings form callback for Custom Formatters Formatter preset engine.
 */
function custom_formatters_engine_formatter_preset_settings_form(&$form, $form_state, $item) {
  $options = array();
  $fields  = field_info_field_types();
  // Give modules a chance to alter fields.
  drupal_alter('custom_formatters_fields', $fields);
  ksort($fields);

  foreach ($fields as $type => $field) {
    $options[$field['module']][$type] = $field['label'];
  }
  ksort($options);
  $form['field_types']['#type']    = 'select';
  $form['field_types']['#options'] = $options;
  $form['field_types']['#ajax']    = array(
    'callback' => 'custom_formatters_export_ui_form_js',
    'wrapper'  => 'engine-wrapper',
  );
  unset($form['field_types']['#description']);

  // Turn code field into nested fields.
  $item->code   = unserialize($item->code);
  $form['code'] = array(
    '#type' => 'container',
    '#tree' => TRUE,
  );

  // Build formatters list.
  $field_type = isset($form_state['values']['field_types']) ? $form_state['values']['field_types'] : $item->field_types;
  $options    = array();
  $formatters = field_info_formatter_types();
  foreach ($formatters as $formatter_name => $formatter) {
    if (in_array($field_type, $formatter['field types'])) {
      $options[$formatter_name] = $formatter['label'];
    }
  }
  $form['code']['formatter'] = array(
    '#title'         => t('Formatter'),
    '#type'          => 'select',
    '#options'       => $options,
    '#default_value' => isset($item->code['formatter']) ? $item->code['formatter'] : '',
    '#ajax'          => array(
      'callback' => 'custom_formatters_export_ui_form_js',
      'wrapper'  => 'engine-wrapper',
    ),
  );

  // Get currently selected formatter.
  $formatter_name = isset($form_state['values']['code']['formatter']) ? $form_state['values']['code']['formatter'] : $form['code']['formatter']['#default_value'];
  if (!isset($form['code']['formatter']['#options'][$formatter_name])) {
    $formatter_name = key($form['code']['formatter']['#options']);
  }
  $formatter = $formatters[$formatter_name];

  // Formatter settings.
  $form['code']['settings'] = array(
    '#tree' => TRUE,
  );

  $function = "{$formatter['module']}_field_formatter_settings_form";
  if (!empty($formatter['settings']) && function_exists($function)) {
    $view_mode = '_custom_display';
    $field     = field_info_field($field_type);

    $instance['display'][$view_mode] = array(
      'type'     => $formatter_name,
      'settings' => isset($item->code['settings']) ? $item->code['settings'] : $formatter['settings'],
      'module'   => $formatter['module'],
    );

    $formatter_settings = $function($field, $instance, $view_mode, $form, $form_state);
    if (is_array($formatter_settings) && !empty($formatter_settings)) {
      $form['code']['settings'] += $formatter_settings;
    }
  }
}

/**
 * Settings form submit callback for Custom Formatters Formatter preset engine.
 *
 * @param $form
 * @param $form_state
 */
function custom_formatters_engine_formatter_preset_settings_form_submit($form, &$form_state) {
  $form_state['values']['code'] = serialize($form_state['values']['code']);
}

/**
 * Render callback for Custom Formatters Formatter preset engine.
 */
function custom_formatters_engine_formatter_preset_render($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display) {
  if (!is_array($formatter->code)) {
    $formatter->code = unserialize($formatter->code);
  }

  $formatter_preset = field_info_formatter_types($formatter->code['formatter']);
  $function         = "{$formatter_preset['module']}_field_formatter_view";
  if (function_exists($function)) {
    $display['settings'] = $formatter->code['settings'];
    $display['type']     = $formatter->code['formatter'];
    $display['module']   = $formatter_preset['module'];

    return $function($obj_type, $object, $field, $instance, $langcode, $items, $display);
  }

  return FALSE;
}

/**
 * Export callback for Custom Formatters Formatter preset engine.
 */
function custom_formatters_engine_formatter_preset_export($item, $module) {
  if (!is_array($item->code)) {
    $item->code = unserialize($item->code);
  }

  return theme('custom_formatters_formatter_preset_export', array(
    'item'   => $item,
    'module' => $module
  ));
}

/**
 * Help callback for Custom Formatters Formatter preset engine.
 */
function custom_formatters_engine_formatter_preset_help() {
  return t('A Formatter preset is a formatter created from an existing formatter, bundled together with pre-set formatter settings.') . "<br />\n"
       . t('This allows for simplified formatters in areas where the formatter choice is provided to the end user, formatters with the power of settings without the confusion.');
}