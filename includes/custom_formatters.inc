<?php
/**
 * @file
 * Custom Formatters module integration.
 */

/**
 * Implements hook_custom_formatters_engine().
 */
function custom_formatters_custom_formatters_engine_info() {
  $engines = array();

  // PHP engine.
  $engines['php'] = array(
    'label' => t('PHP'),
    'callbacks' => array(
      'settings form' => 'custom_formatters_engine_php_settings_form',
      'render' => 'custom_formatters_engine_php_render',
    ),
    'file' => drupal_get_path('module', 'custom_formatters') . '/engines/php.inc',
  );

  return $engines;
}

/**
 * Implements hook_custom_formatters_defaults().
 */
function custom_formatters_custom_formatters_defaults() {
  $formatters = array();

  $formatter = new stdClass;
  $formatter->disabled = TRUE;
  $formatter->api_version = 2;
  $formatter->name = 'example_file';
  $formatter->label = 'Example: File (advanced)';
  $formatter->description = 'An advanced example custom formatter for the "filefield" field type.';
  $formatter->mode = 'php';
  $formatter->field_types = 'file';
  $formatter->multiple = 0;
  $formatter->code = '<?php
return $element[\'#item\'][\'filepath\'] . " " . l(t("<sup>Custom Formatter Example</sup>"), \'admin/build/formatters\', array(\'html\' => TRUE));
?>';
  $formatters['example_file'] = $formatter;

  $formatter = new stdClass;
  $formatter->disabled = TRUE;
  $formatter->api_version = 2;
  $formatter->name = 'example_image';
  $formatter->label = 'Example: Image (basic)';
  $formatter->description = 'A basic example custom formatter for the "filefield" field type.';
  $formatter->mode = 'token';
  $formatter->field_types = 'image';
  $formatter->multiple = 0;
  $formatter->code = '<img src="[image-field-value:file:url]" alt="[image-field-value:alt]" title="[image-field-value:title]" /> <a href="[site:url]/admin/build/formatters"><sup>Custom Formatter Example</sup></a>';
  $formatters['example_image'] = $formatter;

  $formatter = new stdClass;
  $formatter->disabled = TRUE;
  $formatter->api_version = 2;
  $formatter->name = 'example_basic';
  $formatter->label = 'Example: Multiple fields';
  $formatter->description = 'An advanced example custom formatter for an assorted group of basic field types.';
  $formatter->mode = 'php';
  $formatter->field_types = 'number_decimal, number_float, number_integer, text';
  $formatter->multiple = 0;
  $formatter->code = 'return $element[\'#item\'][\'value\'] . " " . l(t("<sup>Custom Formatter Example</sup>"), \'admin/build/formatters\', array(\'html\' => TRUE));';
  $formatters['example_basic'] = $formatter;

  $formatter = new stdClass;
  $formatter->disabled = TRUE;
  $formatter->api_version = 2;
  $formatter->name = 'example_basic_multiples';
  $formatter->label = 'Example: Multiple values';
  $formatter->description = 'An advanced example custom formatter for an assorted group of basic field types, with support for multiple values.';
  $formatter->mode = 'php';
  $formatter->field_types = 'number_decimal, number_float, number_integer, text';
  $formatter->multiple = 1;
  $formatter->code = '$output = \'\';
foreach (element_children($element) as $key) {
  $output .= \'<div>\' . $element[$key][\'#item\'][\'value\'] . " " . l(t("<sup>Custom Formatter Example</sup>"), \'admin/build/formatters\', array(\'html\' => TRUE)) . \'</div>\';
}
return $output;';
  $formatters['example_basic_multiples'] = $formatter;

  return $formatters;
}
