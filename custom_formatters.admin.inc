<?php
/**
 * @file
 * Contains administration functions for the Custom Formatters module.
 */

function custom_formatters_overview() {
  $header = array(t('Label'), t('Field Types'), t('Actions'));
  $rows = array();

  foreach (custom_formatters_formatters() as $formatter) {
    $row = array();
    $row[] = l($formatter->label, 'admin/config/content/formatters/edit/' . $formatter->cfid);
    $row[] = implode(', ', unserialize($formatter->field_types));
    $row[] = implode('&nbsp;&nbsp;&nbsp;&nbsp;', array(
      l(t('Edit'), 'admin/config/content/formatters/edit/' . $formatter->cfid),
      l(t('Delete'), 'admin/config/content/formatters/delete/' . $formatter->cfid),
      l(t('Clone'), 'admin/config/content/formatters/clone/' . $formatter->cfid),
      l(t('Export'), 'admin/config/content/formatters/export/' . $formatter->cfid),
    ));
    $rows[] = $row;
  }

  $output = theme('table', array('header' => $header, 'rows' => $rows));
  return $output;
}

function custom_formatters_formatter_form($form, &$form_state, $formatter = NULL, $op = 'add') {
  $form = array();

  if ($op == 'clone') {
    drupal_set_message(t('Be sure to change the formatter %name when cloning.', array('%name' => 'Name')), 'warning');
  }

  $form['cfid'] = array(
    '#type' => 'value',
    '#value' => ($op == 'edit') ? $formatter->cfid : NULL,
  );

  $form['basic'] = array(
    '#type' => 'fieldset',
    '#title' => t('Basic information'),
    '#collapsible' => TRUE,
    '#collapsed' => ($op == 'edit'),
  );
  $form['basic']['name'] = array(
    '#type' => 'textfield',
    '#size' => '32',
    '#title' => t('Name'),
    '#default_value' => !is_null($formatter) ? $formatter->name : '',
    '#description' => t('For internal use, must be unique and only use alphanumeric characters and underscores (_).'),
    '#required' => TRUE,
  );
  $form['basic']['label'] = array(
    '#type' => 'textfield',
    '#size' => '32',
    '#title' => t('Label'),
    '#default_value' => !is_null($formatter) ? $formatter->label : '',
    '#description' => t('Human readable name, displayed on Custom Formatters overview page and when selecting your CCK Formatter (prefixed with "!custom").', array('!custom' => t('Custom:'))),
    '#required' => TRUE,
  );
  $form['basic']['description'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => !is_null($formatter) ? $formatter->description : '',
  );

  $form['advanced'] = array(
    '#type' => 'fieldset'
  );
  $form['advanced']['field_types'] = array(
    '#type' => 'textfield',
    '#title' => t('Field types'),
    '#autocomplete_path'  => 'js/formatters/autocomplete',
    '#default_value' => !is_null($formatter) ? implode(', ', unserialize($formatter->field_types)) : '',
    '#description' => t('A comma-separated list of CCK fields. Example: number_integer, number_decimal.'),
    '#required' => TRUE,
  );
  $form['advanced']['multiple'] = array(
    '#type' => 'checkbox',
    '#title' => t('Handle multiple values'),
    '#default_value' => !is_null($formatter) ? $formatter->multiple : 0,
  );
  $form['advanced']['code'] = array(
    '#type' => 'textarea',
    '#title' => t('Code'),
    '#rows' => 10,
    '#default_value' => !is_null($formatter) ? $formatter->code : '',
    '#description' => t('Enter the php code that will be evaluated. You do not need to use enclose the code between %php. The $variables variable provides information about the field being formatted. The code should return a string.', array('%php' => '<?php ?>')),
    '#required' => TRUE,
  );

  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#submit' => array('custom_formatters_formatter_form_save'),
  );

  if (!empty($formatter)) {
    $form['buttons']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#submit' => array('custom_formatters_formatter_form_delete'),
    );
  }

  return $form;
}

function custom_formatters_formatter_form_validate($form, &$form_state) {
  // Formatter name must be alphanumeric characters and underscores (_) only.
  if (preg_match('/[^a-zA-Z0-9_]/', $form_state['values']['name'])) {
    form_error($form['basic']['name'], t('Formatter name must be alphanumeric characters and underscores (_) only.'));
  }

  // Make sure formatter name isn't already in use by another formatter.
  $formatter = custom_formatters_formatter_by_name($form_state['values']['name']);
  if (!empty($formatter) && $formatter->cfid !== $form_state['values']['cfid']) {
    form_error($form['basic']['name'], t('A formatter with the name %name already exists.', array('%name' => $form_state['values']['name'])));
  }
}

function custom_formatters_formatter_form_save($form, &$form_state) {
  $form_state['values']['field_types'] = explode(', ', $form_state['values']['field_types']);

  // Create new formatter.
  if (!$form_state['values']['cfid']) {
    drupal_write_record('formatters', $form_state['values']);
    drupal_set_message(t('Formatter %name was added.', array('%name' => $form_state['values']['name'])));
  }

  // Update existing formatter.
  else {
    drupal_write_record('formatters', $form_state['values'], 'cfid');
    drupal_set_message(t('Formatter %name (ID: @id) was updated.', array('%name' => $form_state['values']['name'], '@id' => $form_state['values']['cfid'])));
  }

  custom_formatters_clear_cache();
  $form_state['redirect'] = 'admin/config/content/formatters';
}

function custom_formatters_formatter_form_delete($form, &$form_state) {
  drupal_goto('admin/config/content/formatters/delete/' . $form_state['values']['cfid']);
}

function custom_formatters_formatter_delete_form($form, &$form_state, $formatter = array()) {
  if (empty($formatter)) {
    drupal_set_message(t('The specified formatter was not found'), 'error');
    drupal_goto('admin/config/content/formatters');
  }

  $form = array();

  $form['cfid'] = array(
    '#type' => 'value',
    '#value' => $formatter->cfid
  );

  return confirm_form(
    $form,
    t('Are you sure you want to delete the formatter %name?', array('%name' => $formatter->name)),
    'admin/settings/formatters',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

function custom_formatters_formatter_delete_form_submit($form, &$form_state) {
  $formatter = custom_formatters_formatter($form_state['values']['cfid']);

  db_delete('formatters')
    ->condition('cfid', $form_state['values']['cfid'])
    ->execute();
  custom_formatters_clear_cache();

  drupal_set_message(t('Formatter %name (ID: @id) was deleted.', array('%name' => $formatter->name, '@id' => $formatter->cfid)));
  $form_state['redirect'] = 'admin/config/content/formatters';
}

function custom_formatters_formatter_export_form($form, &$form_state, $formatter = array()) {
  if (empty($formatter)) {
    drupal_set_message(t('The specified formatter was not found'), 'error');
    drupal_goto('admin/config/content/formatters');
  }

  $form = array();
  $form['extra'] = array(
    '#type' => 'fieldset',
  );
  $form['extra']['module'] = array(
    '#type' => 'textfield',
    '#title' => t('Module name'),
    '#default_value' => !empty($form_state['input']) ? $form_state['input']['module'] : 'mymodule',
  );
  $form['extra']['submit'] = array(
    '#type' => 'submit',
    '#value' => 'Update',
  );
  $export = theme('custom_formatters_export', array('formatter' => $formatter, 'name' => $form['extra']['module']['#default_value']));
  $form['export']['output'] = array(
    '#type' => 'textarea',
    '#title' => t('Export'),
    '#value' => $export,
    '#rows' => count(split("\n", $export)),
  );

  return $form;
}

function custom_formatters_formatter_export_form_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}
