<?php
/**
 * @file
 * Devel Generate module integration.
 */

/**
 * Implements hook_custom_formatters_form_alter_alter on behalf of
 * devel_generate.module.
 */
function devel_generate_custom_formatters_form_alter_alter(&$form, &$form_state, $form_id) {
  if (in_array($form_id, array('ctools_export_ui_edit_item_form', 'ctools_export_ui_edit_item_wizard_form')) && isset($form['#formatters'])) {
    if (count($form['engine']['preview']['field']['#options']) > 0) {
      $form['engine']['preview']['entity']['#options']['devel_generate'] = 'Devel generate*';
      $form['engine']['preview']['entity']['#disabled'] = FALSE;
      $form['engine']['preview']['button']['#ajax']['callback'] = 'custom_formatters_export_ui_form_js_preview_devel_generate';
      $form['engine']['preview']['button']['#disabled'] = FALSE;
    }
  }
}

/**
 *
 */
function custom_formatters_export_ui_form_js_preview_devel_generate($form, $form_state) {
  if ($form_state['values']['preview']['entity'] !== 'devel_generate') {
    return custom_formatters_export_ui_form_js_preview($form, $form_state);
  }

  module_load_include('inc', 'devel_generate', 'devel_generate');

  // Clear messages.
  drupal_get_messages(NULL, TRUE);

  $obj_type = $form_state['values']['preview']['entity_type'];
  //$objects = entity_load($obj_type, array($form_state['values']['preview']['entity']));

  $object = new stdClass();
  $object->type = $form_state['values']['preview']['bundle'];
  $object->uid = $GLOBALS['user']->uid;
  $type = node_type_get_type($object);
  $object->title = $type->has_title ? devel_create_greeking(mt_rand(2, rand(4, 10)), TRUE) : '';
  $object->revision = mt_rand(0,1);
  $object->promote = mt_rand(0, 1);

  devel_generate_set_language(array(), $object);

  $object->created = REQUEST_TIME;

  // Populate all core fields on behalf of field.module
  module_load_include('inc', 'devel_generate', 'devel_generate.fields');
  devel_generate_fields($object, $obj_type, $object->type);

  $field = field_info_field($form_state['values']['preview']['field']);
  $instance = field_info_instance($obj_type, $field['field_name'], $form_state['values']['preview']['bundle']);
  $langcode = field_language($obj_type, $object, $field['field_name']);
  $items = $object->{$field['field_name']}[$langcode];
  $display = $instance['display']['default'];
  $formatter = (object) $form_state['values'];

  $js = drupal_add_js();
  $form['engine']['preview']['preview']['content'] = array(
    '#markup' => render(custom_formatters_field_formatter_view($obj_type, $object, $field, $instance, $langcode, $items, $display, $formatter)),
  );

  $js = array_diff_assoc(drupal_add_js(), $js);
  $form['engine']['preview']['preview']['content']['#markup'] .= drupal_get_js('header', $js);

  return $form['engine']['preview']['preview'];
}
