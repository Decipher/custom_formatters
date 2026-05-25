<?php

declare(strict_types=1);

/**
 * @file
 * Formatter Preset engine plugin for building formatters from existing ones.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_formatters\FormatterTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Formatter Preset Formatter type.
 *
 * @FormatterType(
 *   id = "formatter_preset",
 *   label = "Formatter preset",
 *   description = "Build formatters from existing ones with preset settings.",
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, FormatterPluginManager $formatter_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler);
    $this->formatterManager = $formatter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'), $container->get('plugin.manager.field.formatter'));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $formatter_definitions = $this->formatterManager->getDefinitions();
    $data = $this->entity->get('data');
    if (is_array($data) && isset($formatter_definitions[$data['formatter']])) {
      // Add the provider of the referenced formatter as a dependency.
      $dependencies['module'][] = $formatter_definitions[$data['formatter']]['provider'];

      // Get dependencies of the referenced formatter.
      $formatter_instance = $this->getFormatter($data['formatter'], $this->entity->get('field_types')[0]);
      if ($formatter_instance instanceof DependentPluginInterface) {
        $formatter_dependencies = $formatter_instance->calculateDependencies();
        $dependencies = array_merge_recursive($dependencies, $formatter_dependencies);
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form['data'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Ensure we have a Field type to work with.
    $field_type = !is_null($form_state->getValue('field_types')) ? $form_state->getValue('field_types') : $this->entity->get('field_types')[0];
    if (is_null($field_type)) {
      // @todo Add message about selecting a field type.
      return $form;
    }
    // Build formatters list.
    $options = [];
    $formatters = $this->formatterManager->getDefinitions();
    foreach ($formatters as $formatter_name => $formatter) {
      if (in_array($field_type, $formatter['field_types'])) {
        // Check if label is a TranslatableMarkup or a string.
        $label = $formatter['label'];

        // If it's a string, wrap it in TranslatableMarkup.
        if (is_string($label)) {
          $label = $this->t($label); // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        }

        // Ensure label is now a TranslatableMarkup object.
        if ($label instanceof TranslatableMarkup) {
          $options[$formatter_name] = $label->render();
        }
      }
    }

    if (empty($options)) {
      // @todo Prevent field type from being an option in the first place.
      $form['error'] = [
        '#type'   => 'markup',
        '#markup' => $this->t("The selected field type doesn't have any available formatters."),
      ];

      return $form;
    }

    $form['data']['formatter'] = [
      '#title'         => $this->t('Formatter'),
      '#type'          => 'select',
      '#options'       => $options,
      '#default_value' => $this->entity->get('data')['formatter'] ?? '',
      '#ajax'          => [
        'callback' => '::formAjax',
        'wrapper'  => 'plugin-wrapper',
      ],
    ];

    // Get currently selected formatter.
    $formatter_name = $form_state->getValue('data')['formatter'] ?? $form['data']['formatter']['#default_value'];
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
  public function submitForm(array $form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    // Ensure that the field types value is an array.
    $this->entity->set('field_types', [$this->entity->get('field_types')]);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $data = $this->entity->get('data');
    $field_types = $this->entity->get('field_types');
    return $this->getFormatter($data['formatter'], $field_types[0])
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
  protected function getFormatter(string $formatter_name, string $field_type): FormatterInterface {
    $result = $this->formatterManager->createInstance($formatter_name, [
      'field_definition'     => BaseFieldDefinition::create($field_type),
      'settings'             => $this->entity->get('data')['settings'] ?? [],
      'label'                => '',
      'view_mode'            => '',
      'third_party_settings' => [],
    ]);
    \assert($result instanceof FormatterInterface);

    return $result;
  }

}
