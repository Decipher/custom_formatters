<?php

declare(strict_types=1);

/**
 * @file
 * Base class for formatter extras plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a base class for formatter extras plugins.
 */
abstract class FormatterExtrasBase extends PluginBase implements FormatterExtrasInterface {

  /**
   * The Formatter entity.
   *
   * @var \Drupal\custom_formatters\FormatterInterface
   */
  protected $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->entity = $configuration['entity'];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSave(array $form, FormStateInterface $form_state) {
  }

}
