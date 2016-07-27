<?php

namespace Drupal\custom_formatters;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface FormatterInterface.
 */
interface FormatterInterface extends ConfigEntityInterface {

  /**
   * Return the formatter type plugin.
   *
   * @return FormatterTypeInterface|bool
   *   The formatter type plugin or FALSE if no plugin found.
   */
  public function getFormatterType();

  /**
   * Check if the supplied formatter is currently in use.
   *
   * @return bool
   *   The boolean value of Custom formatters active status.
   */
  public function isActive();

}
