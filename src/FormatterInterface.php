<?php

namespace Drupal\custom_formatters;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Contains interface FormatterInterface.
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
   * Get all the dependent entities for this formatter.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The dependent entities.
   */
  public function getDependentEntities();

}
