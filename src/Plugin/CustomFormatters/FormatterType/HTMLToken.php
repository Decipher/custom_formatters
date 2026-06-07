<?php

declare(strict_types=1);

/**
 * @file
 * HTML + Token engine plugin for rendering token-replaced HTML as formatters.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\custom_formatters\FormatterTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the HTML + Token Formatter type.
 *
 * @FormatterType(
 *   id = "html_token",
 *   label = "HTML + Token",
 *   description = "A HTML based editor with Token support.",
 * )
 */
class HTMLToken extends FormatterTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getCodeEditorMode(): ?string {
    return 'text/html';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCodeMirrorExtraSettings(): array {
    return ['autoCloseTags' => TRUE];
  }

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The token service.
   */
  protected Token $tokenService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, Token $token_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $entity_field_manager);
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'), $container->get('entity_field.manager'), $container->get('entity_type.manager'), $container->get('token'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    if ($this->moduleHandler->moduleExists('token')) {
      $token_types = [];
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
        if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
          $token_types[] = $entity_type->id();
        }
      }

      $form['tokens'] = [
        '#theme'           => 'token_tree_link',
        '#token_types'     => $token_types,
        '#global_types'    => TRUE,
        '#click_insert'    => TRUE,
        '#recursion_limit' => 3,
      ];
    }
    else {
      $form['tokens'] = [
        '#type'   => 'markup',
        '#markup' => $this->t('Install the <a href=":url">Token</a> module to enable a token browser for this field.', [
          ':url' => 'https://www.drupal.org/project/token',
        ]),
      ];
    }

    // Build the token list for code editor autocomplete. Guarded with Throwable
    // because token hooks may call services absent in some test environments.
    $token_list = [];
    try {
      $token_info = $this->tokenService->getInfo();
      foreach ($token_info['tokens'] as $type => $tokens) {
        foreach (array_keys($tokens) as $name) {
          $token_list[] = "[$type:$name]";
        }
      }
    }
    catch (\Throwable) {
      // Token service unavailable; autocomplete proceeds without suggestions.
    }
    $form['#attached']['drupalSettings']['customFormatters']['tokens'] = $token_list;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function previewSettingsForm(): array {
    $devel_exists = $this->moduleHandler->moduleExists('devel');

    return [
      'debug_variables' => [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Output token context (entity, settings)'),
        '#default_value' => FALSE,
        '#disabled'      => !$devel_exists,
        '#description'   => !$devel_exists ? $this->t('Requires Devel module.') : '',
      ],
      'debug_html' => [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Output raw HTML'),
        '#default_value' => FALSE,
        '#disabled'      => !$devel_exists,
        '#description'   => !$devel_exists ? $this->t('Requires Devel module.') : '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function previewDebugData(FieldItemListInterface $items, FieldableEntityInterface $entity): mixed {
    return [
      'entity' => $entity,
      'settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode, array $settings = []) {
    $element = [];

    $text = $this->entity->get('data');

    // Replace [formatter_setting:field] tokens; the optional :raw modifier
    // substitutes the unformatted value from settings['_raw'].
    if (!empty($settings)) {
      $text = preg_replace_callback('/\[formatter_setting:([a-zA-Z0-9_]+)(:raw)?\]/', function ($matches) use ($settings) {
        $field_name = $matches[1];
        if (!empty($matches[2])) {
          return $settings['_raw'][$field_name] ?? $matches[0];
        }
        return $settings[$field_name] ?? $matches[0];
      }, $text);
    }

    $token_data = [
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    ];

    foreach ($items as $delta => $item) {
      $delta_token_data = $token_data;

      if ($item instanceof EntityReferenceItem && $item->entity) {
        $delta_token_data[$item->entity->getEntityTypeId()] = $item->entity;
      }

      $context = [
        'text'  => $text,
        'item'  => $item,
        'delta' => $delta,
      ];
      $this->moduleHandler
        ->alter('custom_formatters_token_data', $delta_token_data, $context);

      $element[$delta] = [
        '#markup' => $this->tokenService
          ->replace($text, $delta_token_data, ['clear' => TRUE, 'langcode' => $langcode]),
      ];
    }

    return $element;
  }

}
