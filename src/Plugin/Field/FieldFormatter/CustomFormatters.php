<?php

declare(strict_types=1);

namespace Drupal\custom_formatters\Plugin\Field\FieldFormatter;

use Drupal\custom_formatters\FormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\custom_formatters\FormatterExtrasManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "custom_formatters",
 *   deriver = "Drupal\custom_formatters\Plugin\Derivative\CustomFormatters"
 * )
 *
 * @phpstan-ignore generic.unusedTypeParameter
 */
class CustomFormatters extends EntityReferenceFormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The formatter extras plugin manager.
   *
   * @var \Drupal\custom_formatters\FormatterExtrasManager
   */
  protected $formatterExtrasManager;

  /**
   * Constructs a CustomFormatters formatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\custom_formatters\FormatterExtrasManager $formatter_extras_manager
   *   The formatter extras plugin manager.
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, FormatterExtrasManager $formatter_extras_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->formatterExtrasManager = $formatter_extras_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.custom_formatters.formatter_extras')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $plugin_definition = (array) $this->getPluginDefinition();
    $formatter_id = $plugin_definition['formatter'] ?? NULL;

    $formatter = $this->entityTypeManager
      ->getStorage('formatter')
      ->load($formatter_id);

    if (!$formatter instanceof FormatterInterface) {
      return [];
    }

    $formatter_type = $formatter->getFormatterType();
    if ($formatter_type === FALSE) {
      return [];
    }

    $element = $formatter_type->viewElements($items, $langcode);
    if (!$element) {
      // @todo Fail better.
      return [];
    }

    // Ensure we have a nested array.
    if (is_array($element) && !Element::children($element)) {
      $element = [$element];
    }

    foreach (Element::children($element) as $delta) {
      $element[$delta]['#cf_options'] = $items->viewMode ?? [];
      $element[$delta]['#cache']['tags'] = $formatter->getCacheTags();
    }

    // Allow third party integrations a chance to alter the element.
    $this->formatterExtrasManager
      ->alter('formatterViewElements', $formatter, $element);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {
    if ($this->getFieldSetting('target_type')) {
      parent::prepareView($entities_items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // @todo Re-add form builder functionality once ported.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    // @todo Re-add form builder functionality once ported.
    return [];
  }

}
