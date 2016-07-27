<?php

namespace Drupal\custom_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "custom_formatters",
 *   deriver = "Drupal\custom_formatters\Plugin\Derivative\CustomFormatters"
 * )
 */
class CustomFormatters extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\custom_formatters\FormatterInterface $formatter */
    $formatter = $this->getPluginDefinition()['formatter'];

    $element = $formatter->getFormatterType()
      ->viewElements($items, $langcode);
    if (!$element) {
      return FALSE;
    }
    if (is_string($element)) {
      $element = [
        [
          '#markup' => $element,
        ],
      ];
    }
//    foreach (element_children($element) as $delta) {
//      $element[$delta]['#cf_options'] = isset($display['#cf_options']) ? $display['#cf_options'] : [];
//    }

    // Allow other modules to modify the element.
//    drupal_alter('custom_formatters_field_formatter_view_element', $element, $formatter);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {
    // @TODO
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
//    $display = $instance['display'][$view_mode];
//    $settings = $display['settings'];
//    $formatter = custom_formatters_crud_load(drupal_substr($display['type'], 18));
//
//    $element = [];
//
//    if (isset($formatter->fapi) && !empty($formatter->fapi)) {
//      ob_start();
//      eval($formatter->fapi);
//      ob_get_clean();
//
//      if (isset($form)) {
//        $element = $form;
//        foreach (array_keys($element) as $key) {
//          if (is_array($element[$key])) {
//            $element[$key]['#default_value'] = $settings[$key];
//          }
//        }
//      }
//    }
//
//    return $element;
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
//    $display = $instance['display'][$view_mode];
//    $settings = $display['settings'];
//    $formatter = custom_formatters_crud_load(drupal_substr($display['type'], 18));
//
//    $summary = '';
//
//    if (isset($formatter->fapi)) {
//      ob_start();
//      eval($formatter->fapi);
//      ob_get_clean();
//
//      if (isset($form)) {
//        foreach ($form as $key => $element) {
//          if (isset($element['#type']) && !in_array($element['#type'], ['fieldset'])) {
//            $value = empty($settings[$key]) ? '<em>' . t('Empty') . '</em>' : $settings[$key];
//            $value = is_array($value) ? implode(', ', array_filter($value)) : $value;
//            $summary .= "{$element['#title']}: {$value}<br />";
//          }
//        }
//        $summary = !empty($summary) ? $summary : ' ';
//      }
//    }
//
//    return $summary;

    return [];
  }

}
