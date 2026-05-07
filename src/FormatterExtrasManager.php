<?php

declare(strict_types=1);

/**
 * @file
 * Plugin manager for formatter extras plugins.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages formatter extras plugin definitions and invocation.
 */
class FormatterExtrasManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/CustomFormatters/FormatterExtras', $namespaces, $module_handler, NULL, '\Drupal\custom_formatters\Annotation\FormatterExtras');
    // @todo Add alter hook here?
    $this->setCacheBackend($cache_backend, 'custom_formatters_formatter_extras_plugins');
  }

  /**
   * Passes alterable variables to specific methods on all extras plugins.
   *
   * @param string $method
   *   The base method name (without "Alter" suffix).
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   * @param mixed $data
   *   The primary data to be altered.
   * @param mixed $context1
   *   Optional first context parameter.
   * @param mixed $context2
   *   Optional second context parameter.
   */
  public function alter(string $method, FormatterInterface $entity, mixed &$data, mixed &$context1 = NULL, mixed &$context2 = NULL): void {
    $method = $method . "Alter";
    $definitions = $this->getDefinitions();

    if (is_array($definitions) && !empty($definitions)) {
      foreach ($definitions as $definition) {
        $extra = $this->createInstance($definition['id'], ['entity' => $entity]);
        if (method_exists($extra, $method)) {
          $extra->{$method}($data, $context1, $context2);
        }
      }
    }
  }

  /**
   * Invokes a method on a specific extras plugin.
   *
   * @param string $plugin_id
   *   The extras plugin ID.
   * @param string $method
   *   The method name to invoke.
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   *
   * @return mixed
   *   The return value of the invoked method, or FALSE if not applicable.
   */
  public function invoke(string $plugin_id, string $method, FormatterInterface $entity): mixed {
    $args = func_get_args();
    array_shift($args);
    array_shift($args);
    array_shift($args);
    $definitions = $this->getDefinitions();

    if (isset($definitions[$plugin_id])) {
      $extra = $this->createInstance($plugin_id, ['entity' => $entity]);
      if (method_exists($extra, $method)) {
        // @phpstan-ignore callable.callable
        return empty($args) ? $extra->{$method}() : call_user_func_array([$extra, $method], $args);
      }
    }

    return FALSE;
  }

  /**
   * Invokes a method on all available extras plugins.
   *
   * @param string $method
   *   The method name to invoke.
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   *
   * @return array
   *   An array of return values keyed by plugin ID.
   */
  public function invokeAll(string $method, FormatterInterface $entity): array {
    $args = func_get_args();
    $definitions = $this->getDefinitions();

    $returns = [];
    if (is_array($definitions) && !empty($definitions)) {
      foreach ($definitions as $definition) {
        // Prepend the plugin ID to the args array so invoke() receives
        // ($plugin_id, $method, $entity, ...$additional_args).
        array_unshift($args, $definition['id']);
        $return = call_user_func_array([get_class($this), 'invoke'], $args);
        if ($return) {
          $returns[$definition['id']] = $return;
        }
      }
    }
    return $returns;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();

    // Ensure Extras configuration dependencies are met.
    if (is_array($definitions)) {
      foreach ($definitions as $definition) {
        if (!$this->validateDependencies($definition)) {
          unset($definitions[$definition['id']]);
        }
      }
    }

    return $definitions;
  }

  /**
   * Validate definition dependencies.
   *
   * @param array $definition
   *   The definition to validate.
   *
   * @return bool
   *   TRUE if dependencies met, else FALSE.
   */
  public function validateDependencies(array $definition) {
    if (empty($definition['dependencies'])) {
      return TRUE;
    }

    foreach ($definition['dependencies'] as $type => $dependencies) {
      if (!empty($dependencies)) {
        switch ($type) {
          case 'module':
            foreach ($dependencies as $dependency) {
              if (!$this->moduleHandler->moduleExists($dependency)) {
                return FALSE;
              }
            }
            break;
        }
      }
    }

    return TRUE;
  }

}
