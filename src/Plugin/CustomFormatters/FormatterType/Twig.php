<?php

declare(strict_types=1);

/**
 * @file
 * Twig engine plugin for rendering custom Twig templates as formatters.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\FormatterTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Plugin implementation of the Twig type.
 *
 * @FormatterType(
 *   id = "twig",
 *   label = "Twig",
 *   description = "A Twig based editor.",
 * )
 */
class Twig extends FormatterTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getCodeEditorMode(): ?string {
    // html_twig overlays Twig syntax on HTML, giving autoCloseTags and
    // HTML-aware indentation alongside Twig highlighting.
    return 'html_twig';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCodeMirrorExtraSettings(): array {
    return ['autoCloseTags' => TRUE];
  }

  /**
   * The Twig environment service.
   */
  protected Environment $twigService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, EntityFieldManagerInterface $entity_field_manager, Environment $twig_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $entity_field_manager);
    $this->twigService = $twig_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'), $container->get('entity_field.manager'), $container->get('twig'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['data']['#description'] = $this->t('Enter the Twig code that will be evaluated.<br /><br /><strong>Available parameters:</strong><dl><dt><em><a href=":field_item_list_interface" target="_blank">FieldItemListInterface</a></em> {{ items }}</dt><dd>The field values to be rendered.</dd><dt><em>string</em> {{ langcode }}</dt><dd>The language that should be used to render the field.</dd><dt><em><a href=":entity_interface" target="_blank">EntityInterface</a></em> {{ entity }}</dt><dd>The parent entity the field is attached to.</dd><dt><em>string</em> {{ entity_url }}</dt><dd>The parent entity\'s canonical URL, or an empty string if it has none. Computed in PHP because calling <code>entity.toUrl()</code> directly in the template is blocked by Drupal\'s default Twig sandbox.</dd><dt><em>array</em> {{ settings }}</dt><dd>Formatter settings keyed by field machine name. Values are rendered strings from the configured view display. Access with <code>{{ settings.field_name }}</code>.</dd><dt><em>array</em> {{ raw_settings }}</dt><dd>Same fields as <code>settings</code>, but as unformatted plain-text values. Access with <code>{{ raw_settings.field_name }}</code>.</dd></dl>', [
      ':field_item_list_interface' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FieldItemListInterface.php/interface/FieldItemListInterface',
      ':entity_interface' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityInterface.php/interface/EntityInterface',
    ]);

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
        '#title'         => $this->t('Output template variables (items, langcode, entity, settings)'),
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
      'items' => $items,
      'langcode' => $items->getLangcode(),
      'entity' => $entity,
      'settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode, array $settings = []): array {
    $output = '';
    $entity = $items->getEntity();

    // Compute the canonical URL in PHP rather than exposing entity.toUrl()
    // to the template: Drupal's default Twig sandbox policy
    // (TwigSandboxPolicy) does not allow the toUrl() method, so calling it
    // from a template throws Twig\Sandbox\SecurityError.
    $entity_url = '';
    if ($entity->hasLinkTemplate('canonical')) {
      try {
        $entity_url = $entity->toUrl('canonical')->toString();
      }
      catch (\Exception) {
        $entity_url = '';
      }
    }

    try {
      $output = $this->twigService->createTemplate((string) $this->entity->get('data'))->render([
        'items'        => $items,
        'langcode'     => $langcode,
        'entity'       => $entity,
        'entity_url'   => $entity_url,
        'settings'     => $settings,
        'raw_settings' => $settings['_raw'] ?? [],
      ]);
    }
    catch (Error $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return empty($output) ? [] : ['#markup' => $output];
  }

}
