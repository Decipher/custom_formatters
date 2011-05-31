<?php
/**
 * @file
 * Field module integration.
 */

/**
 * Implements hook_field_formatter_info().
 */
function custom_formatters_field_formatter_info() {
  $formatters = array();

  foreach (custom_formatters_crud_load_all(TRUE) as $key => $formatter) {
    $formatters["custom_formatters_{$key}"] = array(
      'label' => t('Custom: !label', array('!label' => $formatter->label)),
      'field types' => array($formatter->field_types),
    );
  }

  return $formatters;
}

/**
 * Implements hook_field_formatter_view().
 */
function custom_formatters_field_formatter_view($obj_type, $object, $field, $instance, $langcode, $items, $display, $formatter = NULL) {
  $element = array();
  $formatter = is_null($formatter) ? custom_formatters_crud_load(drupal_substr($display['type'], 18)) : $formatter;
  $engines = module_invoke_all('custom_formatters_engine_info');
  $engine = $formatter->mode;
  if (isset($engines[$engine]['file']) && file_exists($engines[$engine]['file'])) {
    require_once $engines[$engine]['file'];
  }

  // Format each item individually.
  if (!$formatter->multiple) {
    foreach ($items as $delta => $item) {
      $element[$delta] = array(
        '#markup' => $engines[$engine]['callbacks']['render']($formatter, $obj_type, $object, $field, $instance, $langcode, array($item), $display),
      );
    }
  }

  // Format all items together.
  else {
    $element[0] = array(
      '#markup' => $engines[$engine]['callbacks']['render']($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display),
    );
  }

  // Allow other modules to modify the element.
  drupal_alter('custom_formatters_field_formatter_view_element', $element, $formatter);

  return $element;
}
