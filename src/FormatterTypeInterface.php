<?php

declare(strict_types=1);

/**
 * @file
 * Interface for formatter type plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for formatter type plugins.
 */
interface FormatterTypeInterface extends PluginInspectionInterface {

  /**
   * Calculates dependencies and stores them in the dependency property.
   *
   * @return array
   *   A keyed array of dependencies.
   */
  public function calculateDependencies();

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode);

  /**
   * Formatter type plugin settings form submit callback.
   *
   * @param array $form
   *   The Form API array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state interface.
   */
  public function submitForm(array $form, FormStateInterface $form_state): void;

  /**
   * Returns the settings form for the formatter type plugin.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array;

  /**
   * Acts on loaded entities.
   */
  public function postLoad(): void;

  /**
   * Acts on a saved entity before the insert or update hook is invoked.
   */
  public function preSave(): void;

  /**
   * Returns engine-specific preview settings form elements.
   *
   * Allows each engine type to contribute debug options to the preview
   * section, such as variable dumps or raw HTML output.
   *
   * @return array
   *   A form array of preview settings elements.
   */
  public function previewSettingsForm(): array;

}
