<?php

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\FormatterTypeBase;

/**
 * Plugin implementation of the Formatter Preset Formatter type.
 *
 * @FormatterType(
 *   id = "formatter_preset",
 *   label = "Formatter preset",
 *   description = "Create simple formatters from existing formatters with preset formatter settings.",
 * )
 */
class FormatterPreset extends FormatterTypeBase {

  /**
   * The Formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatterManager = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formatterManager = \Drupal::service('plugin.manager.field.formatter');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $formatter_definitions = $this->formatterManager->getDefinitions();
    if (isset($formatter_definitions[$this->entity->data['formatter']])) {
      // Add the provider of the referenced formatter as a dependency.
      $dependencies['module'][] = $formatter_definitions[$this->entity->data['formatter']]['provider'];

      // Get dependencies of the referenced formatter.
      $formatter_instance = $this->getFormatter($this->entity->data['formatter'], $this->entity->get('field_types')[0]);
      $formatter_dependencies = $formatter_instance->calculateDependencies();
      $dependencies = array_merge_recursive($dependencies, $formatter_dependencies);
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state) {
    $form['data'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Ensure we have a Field type to work with.
    $field_type = !is_null($form_state->getValue('field_types')) ? $form_state->getValue('field_types') : $this->entity->get('field_types')[0];
    if (is_null($field_type)) {
      // @TODO - Add message about selecting a field type.
      return $form;
    }

    // Build formatters list.
    $options = [];
    $formatters = $this->formatterManager->getDefinitions();
    foreach ($formatters as $formatter_name => $formatter) {
      if (in_array($field_type, $formatter['field_types'])) {
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
        $label = $formatter['label'];
        $options[$formatter_name] = $label->render();
      }
    }

    if (empty($options)) {
      // @TODO - Prevent field type from being an option in the first place.
      $form['error'] = [
        '#type'   => 'markup',
        '#markup' => t("The selected field type doesn't have any available formatters."),
      ];

      return $form;
    }

    $form['data']['formatter'] = [
      '#title'         => t('Formatter'),
      '#type'          => 'select',
      '#options'       => $options,
      '#default_value' => isset($this->entity->get('data')['formatter']) ? $this->entity->get('data')['formatter'] : '',
      '#ajax'          => [
        'callback' => [
          'Drupal\custom_formatters\Form\FormatterForm',
          'formAjax',
        ],
        'wrapper'  => 'plugin-wrapper',
      ],
    ];

    // Get currently selected formatter.
    $formatter_name = isset($form_state->getValue('data')['formatter']) ? $form_state->getValue('data')['formatter'] : $form['data']['formatter']['#default_value'];
    if (!isset($form['data']['formatter']['#options'][$formatter_name])) {
      $formatter_name = key($form['data']['formatter']['#options']);
    }

    $formatter = $this->getFormatter($formatter_name, $field_type);

    // Formatter settings.
    $form['data']['settings'] = $formatter->settingsForm($form, $form_state);
    $form['data']['settings']['#tree'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Ensure that the field types value is an array.
    $this->entity->set('field_types', [$this->entity->get('field_types')]);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->getFormatter($this->entity->get('data')['formatter'], $this->entity->get('field_types')[0])
      ->viewElements($items, $langcode);
  }

  /**
   * Returns a dummy formatter instance.
   *
   * @param string $formatter_name
   *   The formatter identifier.
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   *   A dummy formatter instance.
   */
  protected function getFormatter($formatter_name, $field_type) {
    return $this->formatterManager->createInstance($formatter_name, [
      'field_definition'     => BaseFieldDefinition::create($field_type),
      'settings'             => isset($this->entity->get('data')['settings']) ? $this->entity->get('data')['settings'] : [],
      'label'                => '',
      'view_mode'            => '',
      'third_party_settings' => [],
    ]);
  }

}

//<?php
//
///**
// * @file
// * Formatter preset engine for Custom Formatters modules.
// */
//
///**
// * Theme alter for 'Formatter preset' engine.
// *
// * @param array $theme
// *   A keyed array as expected by hook_theme().
// */
//function custom_formatters_engine_formatter_preset_theme_alter(&$theme) {
//  $theme['custom_formatters_formatter_preset_export'] = array(
//    'variables' => array(
//      'item'   => NULL,
//      'module' => NULL,
//    ),
//    'template'  => 'formatter_preset.export',
//    'path'      => drupal_get_path('module', 'custom_formatters') . '/engines',
//  );
//}
//
///**
// * Settings form callback for Custom Formatters Formatter preset engine.
// *
// * @param array $form
// *   The form api array.
// * @param array $form_state
// *   The form state array.
// * @param object $item
// *   The Custom Formatter object.
// */
//function custom_formatters_engine_formatter_preset_settings_form(&$form, $form_state, $item) {

//}
//
///**
// * Settings form submit callback for Custom Formatters Formatter preset engine.
// *
// * @param array $form
// *   The form api array.
// * @param array $form_state
// *   The form state array.
// */
//function custom_formatters_engine_formatter_preset_settings_form_submit($form, &$form_state) {
//  $form_state['values']['code'] = serialize($form_state['values']['code']);
//}
//
///**
// * Export callback for Custom Formatters Formatter preset engine.
// *
// * @param object $item
// *   The Custom formatter object.
// * @param string $module
// *   The user defined module name.
// *
// * @return string
// *   The exported formatter value.
// */
//function custom_formatters_engine_formatter_preset_export($item, $module) {
//  if (!is_array($item->code)) {
//    $item->code = unserialize($item->code);
//  }
//
//  return theme('custom_formatters_formatter_preset_export', array(
//    'item'   => $item,
//    'module' => $module,
//  ));
//}
