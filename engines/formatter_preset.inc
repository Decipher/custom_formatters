<?php

/**
 * @file
 * Formatter preset engine for Custom Formatters modules.
 */

/**
 * Theme alter for 'Formatter preset' engine.
 *
 * @param array $theme
 *   A keyed array as expected by hook_theme().
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
 *
 * @param array $form
 *   The form api array.
 * @param array $form_state
 *   The form state array.
 * @param object $item
 *   The Custom Formatter object.
 */
function custom_formatters_engine_formatter_preset_settings_form(&$form, $form_state, $item) {
  // Turn code field into nested fields.
  $item->code = unserialize($item->code);
  $form['code'] = array(
    '#type' => 'container',
    '#tree' => TRUE,
  );

  // Build formatters list.
  $field_type = isset($form_state['values']['field_types']) ? $form_state['values']['field_types'] : $item->field_types;
  $options = array();
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
    $field = field_info_field($field_type);

    $instance['entity_type'] = isset($form['preview']['entity_type']['#default_value']) ? $form['preview']['entity_type']['#default_value'] : key($form['preview']['entity_type']['#options']);
    $instance['bundle'] = isset($form['preview']['bundle']['#default_value']) ? $form['preview']['bundle']['#default_value'] : key($form['preview']['bundle']['#options']);

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
 * @param array $form
 *   The form api array.
 * @param array $form_state
 *   The form state array.
 */
function custom_formatters_engine_formatter_preset_settings_form_submit($form, &$form_state) {
  $form_state['values']['code'] = serialize($form_state['values']['code']);
}

/**
 * Render callback for Custom Formatters Formatter preset engine.
 *
 * @param object $formatter
 *   The Custom formatter object.
 * @param string $obj_type
 *   The Entity type.
 * @param object $object
 *   The Entity object.
 * @param array $field
 *   The Field settings.
 * @param array $instance
 *   The Field instance.
 * @param string $langcode
 *   The language code.
 * @param array $items
 *   The Field items.
 * @param array $display
 *   The display settings.
 *
 * @return mixed|bool
 *   The output of the Custom formatter or FALSE.
 */
function custom_formatters_engine_formatter_preset_render($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display) {
  if (!is_array($formatter->code)) {
    $formatter->code = unserialize($formatter->code);
  }

  $formatter_preset = field_info_formatter_types($formatter->code['formatter']);
  $function = "{$formatter_preset['module']}_field_formatter_view";
  if (function_exists($function)) {
    $display['settings'] = $formatter->code['settings'];
    $display['type'] = $formatter->code['formatter'];
    $display['module'] = $formatter_preset['module'];

    return $function($obj_type, $object, $field, $instance, $langcode, $items, $display);
  }

  return FALSE;
}

/**
 * Export callback for Custom Formatters Formatter preset engine.
 *
 * @param object $item
 *   The Custom formatter object.
 * @param string $module
 *   The user defined module name.
 *
 * @return string
 *   The exported formatter value.
 */
function custom_formatters_engine_formatter_preset_export($item, $module) {
  if (!is_array($item->code)) {
    $item->code = unserialize($item->code);
  }

  return theme('custom_formatters_formatter_preset_export', array(
    'item'   => $item,
    'module' => $module,
  ));
}
