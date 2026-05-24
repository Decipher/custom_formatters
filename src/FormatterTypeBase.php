<?php

declare(strict_types=1);

/**
 * @file
 * Base class for formatter type plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler) {
    $this->entity = $configuration['entity'];
    $this->moduleHandler = $module_handler;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('module_handler'));
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

    return $form;
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
   * {@inheritdoc}
   */
  public function previewSettingsForm(): array {
    return [];
  }

}
