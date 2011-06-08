<?php
/**
 * @file
 * Form Builder module integration.
 */

function form_builder_custom_formatters_form_alter_alter(&$form, &$form_state, $form_id) {
  if ($form_id == 'ctools_export_ui_edit_item_form') {
    module_load_include('inc', 'form_builder', 'includes/form_builder.admin');
//
//    // TODO: These should be auto-loaded as necessary.
//    drupal_add_js('misc/autocomplete.js');
//    drupal_add_js('misc/collapse.js');
//    drupal_add_js('misc/tableheader.js');
//    drupal_add_js('misc/textarea.js');
//
    $form['engine']['settings'][] = form_builder_interface('custom_formatters', 'example_php_image');
  }
}

/**
 * Implements hook_form_builder_types()..
 */
function custom_formatters_form_builder_types() {
  $fields = array();

  $fields['number'] = array(
    'title' => t('Number'),
    'properties' => array(
      'title',
      'description',
      'field_prefix',
      'field_suffix',
      'default_value',
      'required',
    ),
    // TODO: how to handle validate functions?
    'validate' => array(
      'number',
    ),
    'default' => array(
      '#key' => 'number',
      '#title' => t('New number'),
      '#type' => 'textfield',
    ),
  );

  $fields['select'] = array(
    'title' => t('Select list'),
    'properties' => array(
      'title',
      'description',
      'default_value',
      'required',
      'options',
      'multiple', // Handled by options element.
      'key_type', // Handled by options element.
      'key_type_toggle', // Handled by options element.
      'key_type_toggled', // Handled by options element.
    ),
    'default' => array(
      '#title' => t('New select list'),
      '#type' => 'select',
      '#options' => array('1' => 'one', '2' => 'two', '3' => 'three'),
      '#multiple_toggle' => TRUE,
    ),
  );

  $fields['checkboxes'] = array(
    'title' => t('Checkboxes'),
    'properties' => array(
      'title',
      'description',
      'default_value',
      'required',
      'options',
      'multiple',
      'key_type', // Handled by options element.
      'key_type_toggle', // Handled by options element.
      'key_type_toggled', // Handled by options element.
    ),
    'default' => array(
      '#title' => t('New checkboxes'),
      '#type' => 'checkboxes',
      '#options' => array('one' => 'one', 'two' => 'two', 'three' => 'three'),
    ),
  );

  $fields['radios'] = array(
    'title' => t('Radios'),
    'properties' => array(
      'title',
      'description',
      'default_value',
      'required',
      'options',
      'key_type', // Handled by options element.
      'key_type_toggle', // Handled by options element.
      'key_type_toggled', // Handled by options element.
    ),
    'default' => array(
      '#title' => t('New radios'),
      '#type' => 'radios',
      '#options' => array('one' => 'one', 'two' => 'two', 'three' => 'three'),
    ),
  );

  $fields['textfield'] = array(
    'title' => t('Textfield'),
    'properties' => array(
      'title',
      'description',
      'field_prefix',
      'field_suffix',
      'default_value',
      'required',
      'size',
    ),
    'default' => array(
      '#title' => t('New textfield'),
      '#type' => 'textfield',
    ),
  );

  $fields['textarea'] = array(
    'title' => t('Textarea'),
    'properties' => array(
      'title',
      'description',
      'default_value',
      'required',
      'rows',
      'cols',
    ),
    'default' => array(
      '#title' => t('New textarea'),
      '#type' => 'textarea',
    ),
  );

  return array(
    'custom_formatters' => $fields
  );
}

/**
 * Implements hook_form_builder_load().
 */
function custom_formatters_form_builder_load($form_type, $form_id) {
  if ($form_type == 'custom_formatters') {
    $form = array();
    //dpm($form_id);
    //$form = form_builder_examples_example();

    return $form;
  }
}

/**
 * Implementas hook_form_builder_save().
 */
function custom_formatters_form_builder_save($form, $form_type, $form_id) {
  if ($form_type == 'custom_formatters') {
    //dpm($form);
    //dpm($form_id);
    // Save the settings based on the changes to the $form array.
  }
}
