<?php

declare(strict_types=1);

/**
 * @file
 * Field formatter plugin that delegates to custom formatter config entities.
 */

namespace Drupal\custom_formatters\Plugin\Field\FieldFormatter;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterInterface as FieldFormatterInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\custom_formatters\Form\FormatterForm;
use Drupal\custom_formatters\FormatterExtrasManager;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'custom_formatters' field formatter.
 *
 * @FieldFormatter(
 *   id = "custom_formatters",
 *   deriver = "Drupal\custom_formatters\Plugin\Derivative\CustomFormatters"
 * )
 *
 * @phpstan-ignore generic.unusedTypeParameter
 */
class CustomFormatters extends FormatterBase {

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
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The loaded formatter entity, cached for form/summary building.
   *
   * @var \Drupal\custom_formatters\FormatterInterface|null
   */
  protected ?FormatterInterface $loadedFormatter = NULL;

  /**
   * Accumulated cache metadata from the most recent loadSettingsFromEntity().
   */
  protected ?CacheableMetadata $settingsCacheMetadata = NULL;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, FormatterExtrasManager $formatter_extras_manager, EntityFieldManagerInterface $entity_field_manager, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->formatterExtrasManager = $formatter_extras_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->renderer = $renderer;
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
      $configuration['third_party_settings'] ?? [],
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.custom_formatters.formatter_extras'),
      $container->get('entity_field.manager'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'formatter_setting_uuid' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $formatter = $this->loadFormatter();
    if (!$formatter instanceof FormatterInterface) {
      return [];
    }

    $formatter_type = $formatter->getFormatterType();
    if ($formatter_type === FALSE) {
      return [];
    }

    $settings = $this->loadSettingsFromEntity($langcode);
    $element = $formatter_type->viewElements($items, $langcode, $settings);
    if (!$element) {
      return [];
    }

    // Ensure we have a nested array.
    if (!Element::children($element)) {
      $element = [$element];
    }

    $formatter_setting = $this->loadSettingEntity();
    foreach (Element::children($element) as $delta) {
      $element[$delta]['#cf_options'] = $items->viewMode ?? [];
      $cache_tags = $formatter->getCacheTags();
      if ($formatter_setting) {
        $cache_tags = array_merge($cache_tags, $formatter_setting->getCacheTags());
      }
      $element[$delta]['#cache']['tags'] = Cache::mergeTags(
        $element[$delta]['#cache']['tags'] ?? [],
        $cache_tags
      );
    }

    // Bubble cache contexts and max-age from the rendered settings fields.
    // Use explicit merges rather than applyTo(), which overwrites in older
    // Drupal versions instead of merging.
    if ($this->settingsCacheMetadata) {
      $contexts = $this->settingsCacheMetadata->getCacheContexts();
      $tags = $this->settingsCacheMetadata->getCacheTags();
      $max_age = $this->settingsCacheMetadata->getCacheMaxAge();
      foreach (Element::children($element) as $delta) {
        if ($contexts) {
          $element[$delta]['#cache']['contexts'] = Cache::mergeContexts(
            $element[$delta]['#cache']['contexts'] ?? [],
            $contexts
          );
        }
        if ($tags) {
          $element[$delta]['#cache']['tags'] = Cache::mergeTags(
            $element[$delta]['#cache']['tags'] ?? [],
            $tags
          );
        }
        if ($max_age !== Cache::PERMANENT) {
          $element[$delta]['#cache']['max-age'] = Cache::mergeMaxAges(
            $element[$delta]['#cache']['max-age'] ?? Cache::PERMANENT,
            $max_age
          );
        }
      }
      $this->settingsCacheMetadata = NULL;
    }

    // Allow third party integrations a chance to alter the element.
    $this->formatterExtrasManager
      ->alter('formatterViewElements', $formatter, $element);

    return $element;
  }

  /**
   * Loads the formatter entity for this plugin instance.
   *
   * @return \Drupal\custom_formatters\FormatterInterface|null
   *   The formatter entity or NULL.
   */
  protected function loadFormatter(): ?FormatterInterface {
    if (isset($this->loadedFormatter)) {
      return $this->loadedFormatter;
    }

    $plugin_definition = (array) $this->getPluginDefinition();
    $formatter_id = $plugin_definition['formatter'] ?? NULL;
    if (!$formatter_id) {
      return NULL;
    }

    $formatter = $this->entityTypeManager
      ->getStorage('formatter')
      ->load($formatter_id);

    if ($formatter instanceof FormatterInterface) {
      $this->loadedFormatter = $formatter;
    }

    return $this->loadedFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $formatter = $this->loadFormatter();
    if (!$formatter) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('formatter_setting', (string) $formatter->id());
    $configurable_fields = array_filter($fields, fn($f) => $f instanceof FieldConfigInterface);
    if (empty($configurable_fields)) {
      return [];
    }

    try {
      $formatter_setting = $this->loadOrCreateSettingEntity($formatter);
    }
    catch (\Exception $e) {
      $form['error'] = [
        '#markup' => $this->t('Unable to load formatter settings. Ensure the database is up to date (run <code>drush updatedb</code>).'),
      ];
      return $form;
    }

    $form['formatter_setting_uuid'] = [
      '#type' => 'value',
      '#value' => $formatter_setting->uuid() ?: '',
    ];

    $form['formatter_setting'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Formatter settings'),
      '#description' => $this->t('Configure settings for this formatter. These settings only apply to this field display instance.'),
      '#tree' => TRUE,
      '#element_validate' => [[static::class, 'validateSettingsEntity']],
    ];

    $form_display = EntityFormDisplay::collectRenderDisplay($formatter_setting, 'default');
    FormatterForm::populateMissingFormDisplayComponents($form_display, (string) $formatter->id());
    $form_display->buildForm($formatter_setting, $form['formatter_setting'], $form_state);

    // Store the entity for the validate callback.
    $form['formatter_setting']['#formatter_setting_entity'] = $formatter_setting;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $formatter = $this->loadFormatter();
    if (!$formatter) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('formatter_setting', (string) $formatter->id());
    $configurable_fields = array_filter($fields, fn($f) => $f instanceof FieldConfigInterface);
    if (empty($configurable_fields)) {
      return [];
    }

    $formatter_setting = $this->loadSettingEntity();
    if (!$formatter_setting) {
      return [];
    }

    $summary = [];
    foreach ($configurable_fields as $field_name => $field_definition) {
      if (!$formatter_setting->hasField($field_name)) {
        continue;
      }
      $field_item_list = $formatter_setting->get($field_name);
      if ($field_item_list->isEmpty()) {
        continue;
      }
      $summary[] = (string) $field_definition->getLabel() . ': ' . (string) $field_item_list->getString();
    }

    return $summary;
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
   * Loads or creates a FormatterSetting entity for this display instance.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $formatter
   *   The formatter config entity.
   *
   * @return \Drupal\custom_formatters\Entity\FormatterSetting
   *   The formatter setting entity.
   */
  protected function loadOrCreateSettingEntity(FormatterInterface $formatter): FormatterSetting {
    $existing = $this->loadSettingEntity();
    if ($existing) {
      return $existing;
    }

    return FormatterSetting::create([
      'formatter' => (string) $formatter->id(),
      'label' => $formatter->label(),
    ]);
  }

  /**
   * Loads the FormatterSetting entity from the stored UUID.
   *
   * @return \Drupal\custom_formatters\Entity\FormatterSetting|null
   *   The formatter setting entity or NULL.
   */
  protected function loadSettingEntity(): ?FormatterSetting {
    $uuid = $this->getSetting('formatter_setting_uuid');
    if (empty($uuid) || !Uuid::isValid($uuid)) {
      return NULL;
    }

    $entities = $this->entityTypeManager
      ->getStorage('formatter_setting')
      ->loadByProperties(['uuid' => $uuid]);

    $entity = $entities ? reset($entities) : NULL;
    return $entity instanceof FormatterSetting ? $entity : NULL;
  }

  /**
   * Loads settings values from the FormatterSetting entity.
   *
   * @return array
   *   An associative array of field name → value pairs.
   */
  protected function loadSettingsFromEntity(string $langcode = 'en'): array {
    $formatter = $this->loadFormatter();
    if (!$formatter) {
      return [];
    }

    $formatter_setting = $this->loadSettingEntity();
    if (!$formatter_setting) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('formatter_setting', (string) $formatter->id());
    $configurable_fields = array_filter($fields, fn($f) => $f instanceof FieldConfigInterface);

    $view_display = EntityViewDisplay::collectRenderDisplay($formatter_setting, 'default');

    $settings = [];
    $metadata = new CacheableMetadata();
    foreach ($configurable_fields as $field_name => $field_definition) {
      if (!$formatter_setting->hasField($field_name)) {
        continue;
      }
      $field_item_list = $formatter_setting->get($field_name);
      if ($field_item_list->isEmpty()) {
        continue;
      }

      $rendered = '';
      $field_renderer = $view_display->getRenderer($field_name);
      if ($field_renderer instanceof FieldFormatterInterface) {
        foreach ($field_renderer->viewElements($field_item_list, $langcode) as $element) {
          $metadata->merge(CacheableMetadata::createFromRenderArray($element));
          $rendered .= (string) $this->renderer->renderInIsolation($element);
        }
      }
      $settings[$field_name] = $rendered;
    }

    $this->settingsCacheMetadata = $metadata;
    return $settings;
  }

  /**
   * Element validate callback: saves the FormatterSetting entity.
   *
   * Extracts form values, populates and saves the entity, then sets the
   * UUID in the parent settings so it persists in entity_view_display config.
   */
  public static function validateSettingsEntity(array &$element, FormStateInterface $form_state): void {
    $entity = $element['#formatter_setting_entity'] ?? NULL;
    if (!$entity) {
      return;
    }

    $form_display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
    FormatterForm::populateMissingFormDisplayComponents($form_display, $entity->bundle());
    $form_display->extractFormValues($entity, $element, $form_state);

    // Stash the entity for saving in submitForm().
    $form_state->set('formatter_setting_entity_' . $entity->bundle(), $entity);

    // Update the UUID value element so field_ui stores the saved UUID.
    $uuid_parents = $element['#parents'];
    array_pop($uuid_parents);
    $uuid_parents[] = 'formatter_setting_uuid';
    $form_state->setValue($uuid_parents, $entity->uuid());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state) {
    $formatter = $this->loadFormatter();
    if (!$formatter) {
      return;
    }

    $entity = $form_state->get('formatter_setting_entity_' . $formatter->id());
    if (!$entity) {
      return;
    }

    $entity->save();
  }

}
