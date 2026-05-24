<?php

declare(strict_types=1);

/**
 * @file
 * Form controller for the custom formatter entity.
 */

namespace Drupal\custom_formatters\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\custom_formatters\Entity\Formatter;
use Drupal\custom_formatters\FormatterExtrasManager;
use Drupal\custom_formatters\FormatterTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the custom formatter entity edit forms.
 */
class FormatterForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\custom_formatters\FormatterInterface
   */
  protected $entity;

  /**
   * Formatter extras plugin manager.
   *
   * @var \Drupal\custom_formatters\FormatterExtrasManager
   */
  protected $formatterExtrasManager;

  /**
   * Field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $fieldFormatterManager;

  /**
   * Field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Devel dumper service, or NULL if Devel is not installed.
   *
   * @var object|null
   */
  protected $develDumper = NULL;

  /**
   * Constructs a FormatterForm object.
   *
   * @param \Drupal\custom_formatters\FormatterExtrasManager $formatter_extras_manager
   *   The formatter extras plugin manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $field_formatter_manager
   *   The field formatter plugin manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param object|null $devel_dumper
   *   The Devel dumper service, or NULL if Devel is not installed.
   */
  public function __construct(FormatterExtrasManager $formatter_extras_manager, FormatterPluginManager $field_formatter_manager, FieldTypePluginManagerInterface $field_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, RendererInterface $renderer, $devel_dumper = NULL) {
    $this->formatterExtrasManager = $formatter_extras_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->fieldFormatterManager = $field_formatter_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->renderer = $renderer;
    $this->develDumper = $devel_dumper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // The Devel dumper service is optional. When the Devel module is not
    // installed, the service won't exist and NULL is returned instead.
    // This avoids hard-coding a dependency on an optional module.
    $devel_dumper = $container->get('devel.dumper', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    return new static(
      $container->get('plugin.manager.custom_formatters.formatter_extras'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('renderer'),
      $devel_dumper
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $formatter_type = $this->entity->getFormatterType();

    if (!$formatter_type) {
      $this->messenger()->addError($this->t('The formatter type for this formatter is missing or invalid.'));
      return parent::form($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'custom_formatters/formatter_form';

    // Show warning if formatter is currently in use.
    $dependent_entities = $this->entity->getDependentEntities();
    if (!empty($dependent_entities)) {
      $form['warning'] = [
        '#theme'           => 'status_messages',
        '#message_list'    => [
          'warning' => [
            $this->t("Changing the field type(s) are currently disabled as this formatter is required by the following configuration(s): @config", [
              '@config' => $this->getDependentEntitiesList($dependent_entities),
            ]),
          ],
        ],
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
      ];
    }

    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Formatter name'),
      '#description'   => $this->t('This will appear in the administrative interface to easily identify it.'),
      '#required'      => TRUE,
      '#default_value' => $this->entity->label(),
    ];

    $form['id'] = [
      '#type'          => 'machine_name',
      '#machine_name'  => [
        'exists'          => '\Drupal\custom_formatters\Entity\Formatter::load',
        'source'          => ['label'],
        'replace_pattern' => '[^a-z0-9_]+',
        'replace'         => '_',
      ],
      '#default_value' => $this->entity->isNew() ? NULL : $this->entity->id(),
      '#disabled'      => !$this->entity->isNew(),
      '#maxlength'     => 255,
    ];

    $form['type'] = [
      '#type'  => 'value',
      '#value' => $this->entity->get('type'),
    ];

    $form['status'] = [
      '#type'  => 'value',
      '#value' => TRUE,
    ];

    $form['description'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['field_types'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Field type(s)'),
      '#options'       => $this->getFieldTypes(),
      '#default_value' => $this->entity->get('field_types'),
      '#required'      => TRUE,
      '#multiple'      => (bool) (((array) $formatter_type->getPluginDefinition())['multipleFields'] ?? FALSE),
      '#ajax'          => [
        'callback' => '::formAjax',
        'wrapper'  => 'plugin-wrapper',
      ],
      '#disabled'      => $dependent_entities,
    ];

    // Get Formatter type settings form.
    $plugin_form = [];
    $form['plugin'] = $formatter_type->settingsForm($plugin_form, $form_state);
    $form['plugin']['#type'] = 'container';
    $form['plugin']['#prefix'] = "<div id='plugin-wrapper'>";
    $form['plugin']['#suffix'] = "</div>";

    // Additional settings vertical tabs group.
    $form['additional_settings'] = [
      '#type' => 'vertical_tabs',
    ];

    // Preview section.
    $form['preview'] = $this->buildPreviewFieldset($form, $form_state);

    // Third party integration settings form.
    $extras = $this->getFormatterExtrasForm();
    if ($extras && is_array($extras)) {
      $form['extras'] = $extras;
      $form['extras']['#tree'] = TRUE;
    }

    return $form;
  }

  /**
   * Builds the preview fieldset.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The preview fieldset render array.
   */
  protected function buildPreviewFieldset(array $form, FormStateInterface $form_state): array {
    $fieldset = [
      '#type'   => 'details',
      '#title'  => $this->t('Preview'),
      '#group'  => 'additional_settings',
      '#weight' => -10,
      '#tree'   => TRUE,
    ];

    $defaults = $this->getPreviewDefaults($form_state);
    $entity_type_id = $form_state->getValue(['preview', 'selects', 'entity_type']) ?? $defaults['entity_type'];
    $bundle = $form_state->getValue(['preview', 'selects', 'bundle']) ?? $defaults['bundle'];
    $field_name = $form_state->getValue(['preview', 'selects', 'field']) ?? $defaults['field'];
    $entity_id = $form_state->getValue(['preview', 'selects', 'entity']) ?? $defaults['entity'];

    $entity_type_options = $this->getPreviewEntityTypes();
    if (!isset($entity_type_options[$entity_type_id])) {
      $entity_type_id = key($entity_type_options) ?: NULL;
      $bundle = NULL;
      $field_name = NULL;
      $entity_id = NULL;
    }

    $bundle_options = $entity_type_id ? $this->getPreviewBundles($entity_type_id) : [];
    if ($bundle !== NULL && !isset($bundle_options[$bundle])) {
      $bundle = key($bundle_options) ?: NULL;
      $field_name = NULL;
      $entity_id = NULL;
    }

    $field_types = (array) ($form_state->getValue('field_types') ?? $this->entity->get('field_types'));
    $field_options = ($entity_type_id && $bundle) ? $this->getPreviewFields($entity_type_id, $bundle, $field_types) : [];
    if ($field_name !== NULL && !isset($field_options[$field_name])) {
      $field_name = key($field_options) ?: NULL;
      $entity_id = NULL;
    }

    $entity_options = ($entity_type_id && $bundle && $field_name) ? $this->getPreviewEntities($entity_type_id, $bundle, $field_name) : [];
    if ($entity_id !== NULL && !isset($entity_options[$entity_id])) {
      $entity_id = key($entity_options) ?: NULL;
    }

    $preview_disabled = empty($entity_options);

    // Build the selects container with a flexbox-friendly class. The CSS
    // targets .preview-selects to arrange selects and the preview button
    // horizontally, matching the Claro admin theme's exposed filter layout.
    $fieldset['selects'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['preview-selects']],
      '#prefix'     => '<div id="preview-selects-wrapper">',
      '#suffix'     => '</div>',
    ];

    // Entity type, bundle, field, and entity selects form a cascading
    // hierarchy. Each select triggers an AJAX rebuild of the entire selects
    // container, ensuring downstream options are recalculated on every change.
    $fieldset['selects']['entity_type'] = [
      '#type'               => 'select',
      '#title'              => $this->t('Entity type'),
      '#options'            => $entity_type_options,
      '#default_value'      => $entity_type_id,
      '#empty_option'       => $this->t('- Select -'),
      '#ajax'               => [
        'callback' => '::previewSelectsAjax',
        'wrapper'  => 'preview-selects-wrapper',
      ],
    ];

    $fieldset['selects']['bundle'] = [
      '#type'               => 'select',
      '#title'              => $this->t('Bundle'),
      '#options'            => $bundle_options,
      '#default_value'      => $bundle,
      '#empty_option'       => $this->t('- Select -'),
      '#validated'          => TRUE,
      '#disabled'           => empty($bundle_options),
      '#ajax'               => [
        'callback' => '::previewSelectsAjax',
        'wrapper'  => 'preview-selects-wrapper',
      ],
    ];

    $fieldset['selects']['field'] = [
      '#type'               => 'select',
      '#title'              => $this->t('Field'),
      '#options'            => $field_options,
      '#default_value'      => $field_name,
      '#empty_option'       => empty($field_options) && !empty($bundle_options)
        ? $this->t('No fields')
        : $this->t('- Select -'),
      '#validated'          => TRUE,
      '#disabled'           => empty($field_options),
      '#ajax'               => [
        'callback' => '::previewSelectsAjax',
        'wrapper'  => 'preview-selects-wrapper',
      ],
    ];

    $fieldset['selects']['entity'] = [
      '#type'               => 'select',
      '#title'              => $this->t('Entity'),
      '#options'            => $entity_options,
      '#default_value'      => $entity_id,
      '#empty_option'       => $preview_disabled && !empty($field_options)
        ? $this->t('No data')
        : $this->t('- Select -'),
      '#validated'          => TRUE,
      '#disabled'           => $preview_disabled,
    ];

    // Place the Preview button inside the selects container so that CSS
    // flexbox rules include it in the horizontal layout alongside the
    // selects. The #type is 'submit' (not 'button') to ensure
    // BrowserTestBase can target it reliably in functional tests.
    $fieldset['selects']['button'] = [
      '#type'                    => 'submit',
      '#value'                   => $this->t('Preview'),
      '#submit'                  => ['::previewSubmit'],
      '#limit_validation_errors' => [
        ['preview', 'selects'],
        ['preview', 'debug'],
        ['preview', 'toggle'],
        ['type'],
        ['field_types'],
        ['data'],
        ['label'],
      ],
      '#ajax'                    => [
        'callback' => '::previewAjaxCallback',
        'wrapper'  => 'preview-output-wrapper',
      ],
      '#button_type'             => 'primary',
      '#disabled'                => $preview_disabled,
      '#prefix'                  => '<div class="preview-actions">',
      '#suffix'                  => '</div>',
    ];

    $formatter_type = $this->entity->getFormatterType();

    // Only include the debug settings when Devel is installed, following
    // the core pattern of conditional form element inclusion (e.g.,
    // NodeTypeForm hides language settings when Language module is disabled).
    if ($this->moduleHandler->moduleExists('devel')) {
      $debug_form = $formatter_type ? $formatter_type->previewSettingsForm() : [];
      if (!empty($debug_form)) {
        $fieldset['debug'] = [
          '#type'  => 'details',
          '#title' => $this->t('Debugging'),
          '#open'  => FALSE,
        ];

        foreach ($debug_form as $key => $element) {
          $fieldset['debug'][$key] = $element;
        }
      }
    }

    $fieldset['toggle'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show full field theming'),
      '#default_value' => $form_state->getValue(['preview', 'toggle']) ?? FALSE,
    ];

    $fieldset['output'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'preview-output-wrapper'],
    ];

    $preview_output = $form_state->get('preview_output');
    if ($preview_output !== NULL) {
      $fieldset['output'][] = $preview_output;
    }

    return $fieldset;
  }

  /**
   * Computes pre-selection defaults for preview selects.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Associative array with entity_type, bundle, field, entity keys.
   */
  protected function getPreviewDefaults(FormStateInterface $form_state): array {
    if ($form_state->hasValue(['preview', 'selects', 'entity_type'])) {
      return [
        'entity_type' => NULL,
        'bundle'      => NULL,
        'field'       => NULL,
        'entity'      => NULL,
      ];
    }

    $entity_types = $this->getPreviewEntityTypes();
    $entity_type_id = isset($entity_types['node']) ? 'node' : (string) key($entity_types);

    if (!$entity_type_id) {
      return [
        'entity_type' => NULL,
        'bundle'      => NULL,
        'field'       => NULL,
        'entity'      => NULL,
      ];
    }

    $bundles = $this->getPreviewBundles($entity_type_id);
    $bundle = (string) key($bundles);

    $field_types = (array) ($form_state->getValue('field_types') ?? $this->entity->get('field_types'));
    $fields = $bundle ? $this->getPreviewFields($entity_type_id, $bundle, $field_types) : [];
    $field_name = (string) key($fields);

    $entities = $field_name ? $this->getPreviewEntities($entity_type_id, $bundle, $field_name) : [];
    $entity_id = key($entities);

    return [
      'entity_type' => $entity_type_id,
      'bundle'      => $bundle,
      'field'       => $field_name,
      'entity'      => $entity_id,
    ];
  }

  /**
   * AJAX callback for preview select changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The selects container render array.
   */
  public function previewSelectsAjax(array $form, FormStateInterface $form_state): array {
    return $form['preview']['selects'];
  }

  /**
   * AJAX callback for the preview button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The preview output container render array.
   */
  public function previewAjaxCallback(array $form, FormStateInterface $form_state): array {
    return $form['preview']['output'];
  }

  /**
   * Submit handler for the preview button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function previewSubmit(array $form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);

    $selects = $form_state->getValue(['preview', 'selects']) ?? [];
    $entity_type_id = $selects['entity_type'] ?? NULL;
    $bundle = $selects['bundle'] ?? NULL;
    $field_name = $selects['field'] ?? NULL;
    $entity_id = $selects['entity'] ?? NULL;

    if (empty($entity_type_id) || empty($bundle) || empty($field_name) || empty($entity_id)) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'warning' => [$this->t('Please select an entity type, bundle, field, and entity to preview.')],
        ],
      ]);
      return;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity instanceof FieldableEntityInterface || !$entity->access('view')) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'error' => [$this->t('Unable to load the selected entity.')],
        ],
      ]);
      return;
    }

    if (!$entity->hasField($field_name)) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'error' => [$this->t('The selected entity does not have the chosen field.')],
        ],
      ]);
      return;
    }

    $items = $entity->get($field_name);
    if ($items->isEmpty()) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'warning' => [$this->t('The selected field has no data.')],
        ],
      ]);
      return;
    }

    $data = $form_state->getValue('data');
    if ($data === NULL) {
      $data = $this->entity->get('data');
    }

    $temp_entity = Formatter::create([
      'id'          => '__preview__',
      'label'       => $form_state->getValue('label') ?? $this->entity->label(),
      'type'        => $form_state->getValue('type') ?? $this->entity->get('type'),
      'field_types' => (array) ($form_state->getValue('field_types') ?? $this->entity->get('field_types')),
      'data'        => $data,
    ]);

    $formatter_type = $temp_entity->getFormatterType();
    if (!$formatter_type) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'error' => [$this->t('Unable to create formatter preview.')],
        ],
      ]);
      return;
    }

    try {
      $langcode = $entity->language()->getId();
      $elements = $formatter_type->viewElements($items, $langcode);
    }
    catch (\Exception $e) {
      $form_state->set('preview_output', [
        '#theme'        => 'status_messages',
        '#message_list' => [
          'error' => [$this->t('Error rendering preview: @message', ['@message' => $e->getMessage()])],
        ],
      ]);
      return;
    }

    $settings = $form_state->getValue(['preview', 'debug']) ?? [];
    $toggle = !empty($form_state->getValue(['preview', 'toggle']));
    $output = $this->buildPreviewOutput($elements, $items, $entity_type_id, $bundle, $field_name, $entity, $settings, $toggle, $formatter_type);

    $form_state->set('preview_output', $output);
  }

  /**
   * Builds the preview output render array.
   *
   * @param array $elements
   *   The formatter render array.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param array $settings
   *   The preview settings values.
   * @param bool $toggle
   *   Whether to show full field theming.
   * @param \Drupal\custom_formatters\FormatterTypeInterface $formatter_type
   *   The formatter type plugin.
   *
   * @return array
   *   The preview output render array.
   */
  protected function buildPreviewOutput(array $elements, FieldItemListInterface $items, string $entity_type_id, string $bundle, string $field_name, FieldableEntityInterface $entity, array $settings, bool $toggle, FormatterTypeInterface $formatter_type): array {
    $output = [];

    if ($toggle && !empty($elements)) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      $field_definition = $field_definitions[$field_name] ?? NULL;

      if ($field_definition) {
        $field_storage = $field_definition->getFieldStorageDefinition();
        $field_build = [
          '#theme'                 => 'field',
          '#title'                 => $field_definition->getLabel(),
          '#label_display'         => 'above',
          '#view_mode'             => '_custom',
          '#language'              => $items->getLangcode(),
          '#field_name'            => $field_name,
          '#field_type'            => $field_storage->getType(),
          '#field_translatable'    => $field_storage->isTranslatable(),
          '#entity_type'           => $entity_type_id,
          '#bundle'                => $bundle,
          '#object'                => $entity,
          '#formatter'             => 'custom_formatters_preview',
          '#is_multiple'           => $field_storage->isMultiple(),
          '#third_party_settings'  => [],
        ];

        foreach ($elements as $key => $element) {
          if (is_int($key)) {
            $field_build[$key] = $element;
          }
        }

        if (!isset($field_build[0])) {
          $field_build[0] = $elements;
        }

        $rendered_html = (string) $this->renderer->renderRoot($field_build);
      }
      else {
        $rendered_html = (string) $this->renderer->renderRoot($elements);
      }
    }
    else {
      $rendered_html = (string) $this->renderer->renderRoot($elements);
    }

    $output['preview'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['formatter-preview-output']],
      'content'     => [
        '#markup' => $rendered_html,
      ],
    ];

    // Devel must be installed for debug checkboxes to appear. When enabled,
    // its dumper service produces styled output matching dpm()/kpr(). For
    // engines with multiple template variables (Twig, HTML+Token), the
    // output dumps all available variables bundled together.
    if (!empty($settings['debug_variables'])) {
      $debug_data = $items->getValue();

      if ($formatter_type->getPluginId() === 'twig') {
        $debug_data = [
          'items' => $items,
          'langcode' => $items->getLangcode(),
          'entity' => $entity,
        ];
      }

      if ($formatter_type->getPluginId() === 'html_token') {
        $debug_data = $entity;
      }

      $output['debug_variables'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['formatter-preview-debug']],
        'dump' => $this->develDumper->exportAsRenderable($debug_data),
      ];
    }

    if (!empty($settings['debug_html'])) {
      $output['debug_html'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['formatter-preview-debug']],
        'dump' => $this->develDumper->exportAsRenderable($rendered_html),
      ];
    }

    return $output;
  }

  /**
   * Returns entity type options for preview.
   *
   * @return array
   *   Entity type labels keyed by entity type ID.
   */
  protected function getPreviewEntityTypes(): array {
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements('Drupal\Core\Entity\ContentEntityInterface') && $entity_type->hasKey('bundle')) {
        $options[$entity_type->id()] = $entity_type->getLabel();
      }
    }
    return $options;
  }

  /**
   * Returns bundle options for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   Bundle labels keyed by bundle ID.
   */
  protected function getPreviewBundles(string $entity_type_id): array {
    $options = [];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach ($bundle_info as $bundle => $info) {
      $options[$bundle] = $info['label'];
    }
    return $options;
  }

  /**
   * Returns field options filtered by formatter field types.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param array $field_types
   *   The allowed field type IDs.
   *
   * @return array
   *   Field labels keyed by field name.
   */
  protected function getPreviewFields(string $entity_type_id, string $bundle, array $field_types): array {
    $options = [];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($field_definitions as $field_name => $definition) {
      if ($definition instanceof FieldConfigInterface && in_array($definition->getType(), $field_types, TRUE)) {
        $options[$field_name] = $definition->getLabel();
      }
    }
    return $options;
  }

  /**
   * Returns entity options with data in a specific field.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   Entity labels keyed by entity ID, limited to 50.
   */
  protected function getPreviewEntities(string $entity_type_id, string $bundle, string $field_name): array {
    $options = [];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->range(0, 50);

    if ($bundle_key = $entity_type->getKey('bundle')) {
      $query->condition($bundle_key, $bundle);
    }

    // Filter to entities with non-empty field data.
    $query->exists($field_name);

    $ids = $query->execute();
    if (empty($ids)) {
      return $options;
    }

    $entities = $storage->loadMultiple($ids);
    foreach ($entities as $id => $entity) {
      if ($entity->access('view')) {
        $options[$id] = $entity->label() ?: (string) $id;
      }
    }

    return $options;
  }

  /**
   * Returns the settings form for any available third party integrations.
   *
   * @return array
   *   A renderable form array of extras settings.
   */
  public function getFormatterExtrasForm() {
    $form = [];

    $definitions = $this->formatterExtrasManager->getDefinitions();
    if (is_array($definitions) && !empty($definitions)) {
      foreach ($definitions as $definition) {
        $extras_form = $this->formatterExtrasManager->invoke($definition['id'], 'settingsForm', $this->entity);

        if (is_array($extras_form) && !empty($extras_form)) {
          $form[$definition['id']] = $extras_form;

          $form[$definition['id']]['#type'] = 'details';
          $form[$definition['id']]['#title'] = $definition['label'];
          $form[$definition['id']]['#description'] = $definition['description'];
          $form[$definition['id']]['#group'] = 'additional_settings';
        }
      }
    }

    return $form;
  }

  /**
   * Ajax callback for form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The ajax form element.
   */
  public function formAjax(array $form, FormStateInterface $form_state) {
    return $form['plugin'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['save_and_edit'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Save & Edit'),
      '#submit' => ['::submitForm', '::saveAndEdit'],
      '#weight' => 10,
    ];
    return $actions;
  }

  /**
   * Submit handler for "Save & Edit" button.
   */
  public function saveAndEdit(array $form, FormStateInterface $form_state) {
    $this->save($form, $form_state);
    $form_state->setIgnoreDestination(TRUE);
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $formatter_type = $this->entity->getFormatterType();
    if ($formatter_type !== FALSE) {
      $formatter_type->submitForm($form, $form_state);
    }

    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();

    // Invoke all third party integrations save method.
    $this->formatterExtrasManager->invokeAll('settingsSave', $entity, $form, $form_state);

    $status = $entity->save();

    // Clear cached formatters.
    // @todo Tag custom formatters.
    $this->fieldFormatterManager->clearCachedDefinitions();

    if ($is_new) {
      $this->messenger()->addStatus($this->t('Added formatter %formatter.', ['%formatter' => $entity->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated formatter %formatter.', ['%formatter' => $entity->label()]));
    }
    $form_state->setRedirectUrl(new Url('entity.formatter.collection'));

    return $status;
  }

  /**
   * Returns a list of dependent entities.
   *
   * @param array $entities
   *   The dependent entities.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered list of dependent entities.
   */
  protected function getDependentEntitiesList(array $entities = []) {
    $list = [];
    foreach ($entities as $entity) {
      $entity_type_id = $entity->getEntityTypeId();
      if (!isset($list[$entity_type_id])) {
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        // Store the ID and label to sort the entity types and entities later.
        $label = $entity_type->getLabel();
        $list[$entity_type_id] = [
          '#theme' => 'item_list',
          '#title' => $label,
          '#items' => [],
        ];
      }
      $list[$entity_type_id]['#items'][$entity->id()] = $entity->label() ?: $entity->id();
    }
    return $this->renderer->render($list);
  }

  /**
   * Returns an array of available field types.
   *
   * @todo Allow formatter type plugin to modify this list.
   *
   * @return array
   *   Array of field types grouped by their providers.
   */
  protected function getFieldTypes() {
    $options = [];

    $field_types = $this->fieldTypeManager->getDefinitions();
    $this->moduleHandler->alter('custom_formatters_fields', $field_types);

    ksort($field_types);
    foreach ($field_types as $field_type) {
      $options[$field_type['provider']][$field_type['id']] = (string) $field_type['label'];
    }
    ksort($options);

    return $options;
  }

}
