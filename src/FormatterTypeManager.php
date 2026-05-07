<?php

declare(strict_types=1);

/**
 * @file
 * Plugin manager for formatter type plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages formatter type plugin definitions.
 */
class FormatterTypeManager extends DefaultPluginManager {

  /**
   * An array of engine options.
   *
   * @var array
   */
  protected $options;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/CustomFormatters/FormatterType', $namespaces, $module_handler, NULL, '\Drupal\custom_formatters\Annotation\FormatterType');
    // @todo Add alter hook here?
    $this->setCacheBackend($cache_backend, 'custom_formatters_formatter_type_plugins');
  }

}
