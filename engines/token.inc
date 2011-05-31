<?php
/**
 * @file
 * Token engine for Custom Formatters modules.
 */

/**
 * Settings form callback for Custom Formatters Token engine.
 */
function custom_formatters_engine_token_settings_form(&$form, $form_state, $item) {
  $options = array();
  foreach (field_info_field_types() as $type => $field) {
    $options[$field['module']][$type] = $field['label'];
  }
  $form['field_types']['#type'] = 'select';
  $form['field_types']['#options'] = $options;
  $form['field_types']['#ajax'] = array(
    'callback' => 'custom_formatters_export_ui_form_js',
    'wrapper' => 'engine-wrapper',
  );

  $form['multiple'] = array(
    '#type' => 'value',
    '#value' => FALSE,
  );

  $field_type = !empty($form_state['values']['field_types']) ? $form_state['values']['field_types'] : (
    !empty($item->field_types) ? $item->field_types : key($options)
  );
  $field = str_replace('_', '-', $field_type) . '-field';
  $info = token_get_info($field);
  $field_value = $info['field-value-type'];
  $form['tokens'] = array(
    '#title' => t('Tokens'),
    '#type' => 'fieldset',
    //'#description' => theme('token_tree', array('token_types' => 'all')),
    '#description' => theme('token_tree', array('token_types' => array($field_value, 'node', 'user'))),
    '#weight' => 50,
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
}

/**
 * Render callback for Custom Formatters Token engine.
 */
function custom_formatters_engine_token_render($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display) {
  return token_replace($formatter->code, array(
    $obj_type => $object,
    'item' => $items[0],
  ));
}
