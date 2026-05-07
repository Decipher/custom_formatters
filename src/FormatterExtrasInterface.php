<?php

declare(strict_types=1);

/**
 * @file
 * Interface for formatter extras plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for formatter extras plugins.
 */
interface FormatterExtrasInterface {

  /**
   * Settings form callback.
   *
   * @return array
   *   The form API array.
   */
  public function settingsForm();

  /**
   * Save callback for settings form.
   *
   * @param array $form
   *   The submitted formatter form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted formatter form state object.
   */
  public function settingsSave(array $form, FormStateInterface $form_state);

}
