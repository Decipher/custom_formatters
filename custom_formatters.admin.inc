<?php
// $Id$
/**
 * @file
 * Contains administration functions for the Custom Formatters module.
 */

function custom_formatters_overview() {
  $header = array(t('Label'), t('Field Types'), t('Actions'));
  $rows = array();

  foreach (custom_formatters_formatters() as $formatter) {
    $row = array();
    $row[] = l($formatter['label'], 'admin/settings/formatters/' . $formatter['cfid']);
    $row[] = implode(', ', unserialize($formatter['field_types']));
    $row[] = implode('&nbsp;&nbsp;&nbsp;&nbsp;', array(
      l(t('Edit'), 'admin/settings/formatters/' . $formatter['cfid']),
      l(t('Delete'), 'admin/settings/formatters/' . $formatter['cfid'] . '/delete'),
    ));
    $rows[] = $row;
  }

  $output = theme('table', $header, $rows);
  return $output;
}

function custom_formatters_formatter_form($form_state, $formatter = array()) {
  $form = array();

  $form['cfid'] = array(
    '#type' => 'value',
    '#value' => $formatter['cfid'],
  );

  $form['basic'] = array(
    '#type' => 'fieldset',
    '#title' => t('Basic information'),
    '#collapsible' => TRUE,
    '#collapsed' => !empty($formatter),
  );
  $form['basic']['name'] = array(
    '#type' => 'textfield',
    '#size' => '32',
    '#title' => t('Name'),
    '#default_value' => $formatter['name'],
    '#description' => t('Please only use alphanumeric characters and underscores (_) for formatter names.'),
    '#required' => TRUE,
  );
  $form['basic']['label'] = array(
    '#type' => 'textfield',
    '#size' => '32',
    '#title' => t('Label'),
    '#default_value' => $formatter['label'],
    '#required' => TRUE,
  );
  $form['basic']['description'] = array(
    '#type' => 'textfield',
    '#size' => '128',
    '#title' => t('Description'),
    '#default_value' => $formatter['description'],
    '#required' => TRUE,
  );

  $form['field_types'] = array(
    '#type' => 'textfield',
    '#title' => t('Field types'),
    '#autocomplete_path'  => 'js/formatters/autocomplete',
    '#default_value' => implode(', ', unserialize($formatter['field_types'])),
    '#description' => t('A comma-separated list of CCK fields. Example: number_integer, number_decimal.'),
    '#required' => TRUE,
  );

  $form['code'] = array(
    '#type' => 'textarea',
    '#title' => t('Code'),
    '#rows' => 10,
    '#default_value' => $formatter['code'],
    '#description' => t('Enter the php code that will be evaluated. You do not need to use enclose the code between %php. The $element variable provides information about the field being formatted. The code should return a string.', array('%php' => '<?php ?>')),
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
  if (!empty($formatter) && $formatter['cfid'] !== $form_state['values']['cfid']) {
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
  $form_state['redirect'] = 'admin/settings/formatters';
}

function custom_formatters_formatter_form_delete($form, &$form_state) {
  drupal_goto('admin/settings/formatters/' . $form_state['values']['cfid'] . '/delete');
}

function custom_formatters_formatter_delete_form($form_state, $formatter = array()) {
  if (empty($formatter)) {
    drupal_set_message(t('The specified formatter was not found'), 'error');
    drupal_goto('admin/settings/formatters');
  }

  $form = array();

  $form['cfid'] = array(
    '#type' => 'value',
    '#value' => $formatter['cfid']
  );

  return confirm_form(
    $form,
    t('Are you sure you want to delete the formatter %name?', array('%name' => $formatter['name'])),
    'admin/settings/formatters',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

function custom_formatters_formatter_delete_form_submit($form, &$form_state) {
  $formatter = custom_formatters_formatter($form_state['values']['cfid']);

  db_query("DELETE FROM {formatters} WHERE cfid = %d", $form_state['values']['cfid']);
  custom_formatters_clear_cache();

  drupal_set_message(t('Formatter %name (ID: @id) was deleted.', array('%name' => $formatter['name'], '@id' => $formatter['cfid'])));
  $form_state['redirect'] = 'admin/settings/formatters';
}
