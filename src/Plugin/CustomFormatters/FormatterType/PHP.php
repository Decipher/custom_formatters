<?php

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\custom_formatters\FormatterTypeBase;

/**
 * Plugin implementation of the PHP Formatter type.
 *
 * @FormatterType(
 *   id = "php",
 *   label = "PHP",
 *   description = "A PHP based editor with support for multiple fields and multiple values.",
 *   multipleFields = "true"
 * )
 */
class PHP extends FormatterTypeBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
//    global $theme_path, $theme_info, $conf;

//    // Store current theme path.
//    $old_theme_path = $theme_path;

//    // Restore theme_path to the theme, as long as php_eval() executes,
//    // so code evaluated will not see the caller module as the current theme.
//    // If theme info is not initialized get the path from theme_default.
//    if (!isset($theme_info)) {
//      $theme_path = drupal_get_path('theme', $conf['theme_default']);
//    }
//    else {
//      $theme_path = dirname($theme_info->filename);
//    }

//    // Build variables array for formatter.
//    $variables = array(
//      '#obj_type' => $obj_type,
//      '#object'   => $object,
//      '#field'    => $field,
//      '#instance' => $instance,
//      '#langcode' => $langcode,
//      '#items'    => $items,
//      '#display'  => $display,
//    );

    ob_start();
    $output = eval($this->entity->get('data'));
    $output = !empty($output) ? $output : ob_get_contents();
    ob_end_clean();

    // Preview debugging; Show the available variables data.
//    if (module_exists('devel') && isset($formatter->preview) && $formatter->preview['options']['dpm']['vars']) {
//      dpm($variables);
//    }

    return [
      '#type'   => 'markup',
      '#markup' => $output,
    ];

//    // Recover original theme path.
//    $theme_path = $old_theme_path;

//    return empty($output) ? FALSE : $output;
  }

}
