<?php

namespace Drupal\custom_formatters\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Retrieves field formatter plugin definitions for all custom formatters.
 */
class CustomFormatters extends DeriverBase {

  protected $settings = [];

  /**
   * CustomFormatters constructor.
   */
  public function __construct() {
    $this->settings = \Drupal::config('custom_formatters.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $formatters = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->loadMultiple();
    /** @var \Drupal\custom_formatters\FormatterInterface $formatter */
    foreach ($formatters as $formatter) {
      if ($formatter->get('status')) {
        $this->derivatives[$formatter->id()] = $base_plugin_definition;
        $this->derivatives[$formatter->id()]['label'] = t($this->getLabel($formatter->label()));
        $this->derivatives[$formatter->id()]['field_types'] = $formatter->get('field_types');
        $this->derivatives[$formatter->id()]['formatter'] = $formatter;
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Returns Formatter label with optional prefix.
   *
   * @param string $label
   *   Formatter label.
   *
   * @return string
   *   The Formatter label with optional prefix.
   */
  protected function getLabel($label) {
    // Label prefix.
    if ($this->settings->get('label_prefix')) {
      $label = "{$this->settings->get('label_prefix_value')}: {$label}";
    }

    return $label;
  }

}

///**
// * Implements hook_field_formatter_info().
// */
//function custom_formatters_field_formatter_info() {

//  foreach (custom_formatters_crud_load_all(TRUE) as $key => $formatter) {
//    $formatters["custom_formatters_{$key}"] = array(
//      'label'       => $label,
//      'field types' => drupal_explode_tags($formatter->field_types),
//    );
//
//    if (isset($formatter->fapi)) {
//      ob_start();
//      eval($formatter->fapi);
//      ob_get_clean();
//
//      if (isset($form)) {
//        $formatters["custom_formatters_{$key}"]['settings'] = array();
//        foreach ($form as $form_key => $element) {
//          $formatters["custom_formatters_{$key}"]['settings'][$form_key] = isset($element['#default_value']) ? $element['#default_value'] : '';
//        }
//      }
//    }
//  }
//
//  return $formatters;
//}
//
