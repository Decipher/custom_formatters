<?php

declare(strict_types=1);

/**
 * @file
 * Base class for formatter type plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for formatter type plugins.
 */
abstract class FormatterTypeBase extends PluginBase implements FormatterTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The Formatter entity.
   *
   * @var \Drupal\custom_formatters\FormatterInterface
   */
  protected $entity = NULL;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager) {
    $this->entity = $configuration['entity'];
    $this->moduleHandler = $module_handler;
    $this->entityFieldManager = $entity_field_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form['data'] = $this->buildCodeEditorElement($this->getCodeEditorMode());

    $reference = $this->buildSettingsReference();
    if ($reference !== []) {
      $form['settings_reference'] = $reference;
    }

    return $form;
  }

  /**
   * Builds a reference table of available formatter settings fields.
   *
   * @return array
   *   A render array for the settings reference table, or empty if none.
   */
  protected function buildSettingsReference(): array {
    if (!$this->entity->id()) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('formatter_setting', (string) $this->entity->id());
    $configurable_fields = array_filter($fields, fn($f) => $f instanceof FieldConfigInterface);

    if (empty($configurable_fields)) {
      return [];
    }

    $rows = [];
    foreach ($configurable_fields as $field_name => $field_definition) {
      $rows[] = [
        $field_name,
        $field_definition->getType(),
        $field_definition->getLabel(),
        $field_definition->getDescription(),
      ];
    }

    $url = Url::fromRoute('entity.formatter_setting.field_ui_fields', [
      'formatter' => $this->entity->id(),
    ])->toString();

    return [
      '#type' => 'details',
      '#title' => $this->t('Available settings fields'),
      '#description' => $this->t('The following fields are available via the <code>$settings</code> variable. Add fields on the <a href=":url">Manage fields</a> tab.', [
        ':url' => $url,
      ]),
      '#open' => TRUE,
      '#weight' => 100,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field name'),
          $this->t('Type'),
          $this->t('Label'),
          $this->t('Description'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No settings fields configured.'),
      ],
    ];
  }

  /**
   * Returns the CodeMirror language mode for this engine.
   *
   * Override in child classes to enable CodeMirror integration. Return NULL
   * to use a plain textarea regardless of module availability.
   *
   * @return string|null
   *   A CodeMirror mode string (e.g. 'application/x-httpd-php'), or NULL.
   */
  protected function getCodeEditorMode(): ?string {
    return NULL;
  }

  /**
   * Returns extra CodeMirror options specific to this engine.
   *
   * Override in child classes to add engine-specific settings like
   * autoCloseTags.
   *
   * @return array
   *   An associative array of CodeMirror options.
   */
  protected function getCodeMirrorExtraSettings(): array {
    return [];
  }

  /**
   * Builds a code editor form element.
   *
   * Uses CodeMirror when the codemirror_editor module is installed and a mode
   * is provided. Falls back to a plain textarea otherwise.
   *
   * @param string|null $mode
   *   The CodeMirror language mode, or NULL for plain textarea.
   *
   * @return array
   *   A render array for the code editor form element.
   */
  protected function buildCodeEditorElement(?string $mode = NULL): array {
    $has_codemirror = $mode !== NULL && $this->moduleHandler->moduleExists('codemirror_editor');

    $element = [
      '#title'         => $this->t('Formatter'),
      '#type'          => $has_codemirror ? 'codemirror' : 'textarea',
      '#default_value' => $this->entity->get('data'),
      '#required'      => TRUE,
      '#rows'          => 10,
    ];

    if ($has_codemirror) {
      $element['#codemirror'] = [
        'mode'             => $mode,
        'lineNumbers'      => TRUE,
        'lineWrapping'     => TRUE,
        'styleActiveLine'  => TRUE,
        'toolbar'          => FALSE,
      ] + $this->getCodeMirrorExtraSettings();
    }

    return $element;
  }

  /**
   * Acts on loaded entities.
   */
  public function postLoad(): void {
  }

  /**
   * Acts on a saved entity before the insert or update hook is invoked.
   *
   * Used after the entity is saved, but before invoking the insert or update
   * hook. Note that in case of translatable content entities this callback is
   * only fired on their current translation. It is up to the developer to
   * iterate over all translations if needed.
   */
  public function preSave(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {
  }

  /**
   * Returns engine-specific preview settings form elements.
   *
   * Allows each engine type to contribute debug options to the preview
   * section, such as variable dumps or raw HTML output. Third-party
   * plugins that do not override this method will not display debug
   * settings in the preview section.
   *
   * @return array
   *   A form array of preview settings elements.
   */
  public function previewSettingsForm(): array {
    return [];
  }

  /**
   * Returns engine-specific preview debug data.
   *
   * Each engine can override this to provide debug output tailored to its
   * template context. Called via method_exists() guard in FormatterForm
   * to preserve BC with third-party plugins that don't implement it.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items being rendered.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being previewed.
   *
   * @return mixed
   *   Data suitable for Devel's dumper output.
   */
  public function previewDebugData(FieldItemListInterface $items, FieldableEntityInterface $entity): mixed {
    return $items->getValue();
  }

}
