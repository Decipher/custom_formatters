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
  unset($form['field_types']['#description']);

  $fieldable = array();
  $entity_types = entity_get_info();
  foreach ($entity_types as $entity_type => $entity) {
    if ($entity['fieldable']) {
      $fieldable[] = $entity_type;
    }
  }

  $field_type = !empty($form_state['values']['field_types']) ? $form_state['values']['field_types'] : (
    !empty($item->field_types) ? $item->field_types : key($options)
  );
  $field = str_replace('_', '-', $field_type) . '-field';
  $info = token_get_info($field);
  $field_value = $info['field-value-type'];
  $form['tokens'] = array(
    '#title' => t('Tokens'),
    '#type' => 'fieldset',
    '#description' => theme('token_tree', array('token_types' => array_merge(
      array($field_value),
      $fieldable
    ))),
    '#weight' => 50,
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
}

/**
 * Render callback for Custom Formatters Token engine.
 */
function custom_formatters_engine_token_render($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display) {
  $output = '';

  $field = str_replace('_', '-', $formatter->field_types) . '-field';
  $info = token_get_info($field);
  $field_value = $info['field-value-type'];

  foreach ($items as $item) {
    $output .= token_replace($formatter->code, array(
      $obj_type => $object,
      $field_value => $item,
      'item' => $item,
    ));
  }

  return $output;
}
