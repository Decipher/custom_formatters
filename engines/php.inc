<?php
/**
 * @file
 * Token engine for Custom Formatters modules.
 */

/**
 * Settings form callback for Custom Formatters PHP engine.
 */
function custom_formatters_engine_php_settings_form(&$form) {
}

/**
 * Render callback for Custom Formatters PHP engine.
 */
function custom_formatters_engine_php_render($formatter, $obj_type, $object, $field, $instance, $langcode, $items, $display) {
  global $theme_path, $theme_info, $conf;

  // Store current theme path.
  $old_theme_path = $theme_path;

  // Restore theme_path to the theme, as long as php_eval() executes,
  // so code evaluated will not see the caller module as the current theme.
  // If theme info is not initialized get the path from theme_default.
  if (!isset($theme_info)) {
    $theme_path = drupal_get_path('theme', $conf['theme_default']);
  }
  else {
    $theme_path = dirname($theme_info->filename);
  }

  // Build variables array for formatter.
  $variables = array(
    '#formatter' => $formatter,
    '#obj_type' => $obj_type,
    '#object' => $object,
    '#field' => $field,
    '#instance' => $instance,
    '#langcode' => $langcode,
    '#items' => $items,
    '#display' => $display,
  );

  ob_start();
  print eval('?>' . $formatter->code);
  $output = ob_get_contents();
  ob_end_clean();

  // Recover original theme path.
  $theme_path = $old_theme_path;

  return $output;
}
