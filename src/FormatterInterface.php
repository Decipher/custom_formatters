<?php

declare(strict_types=1);

/**
 * @file
 * Interface for the Formatter config entity.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for the Formatter config entity.
 */
interface FormatterInterface extends ConfigEntityInterface {

  /**
   * Return the formatter type plugin.
   *
   * @return \Drupal\custom_formatters\FormatterTypeInterface|false
   *   The formatter type plugin or FALSE if no plugin found.
   */
  public function getFormatterType();

  /**
   * Get all the dependent entities for this formatter.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The dependent entities.
   */
  public function getDependentEntities();

}
