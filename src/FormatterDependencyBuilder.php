<?php

declare(strict_types=1);

/**
 * @file
 * Service for building formatter dependencies and resolving plugin types.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * Service for building formatter dependencies and resolving plugin types.
 */
class FormatterDependencyBuilder {

  /**
   * Constructs a FormatterDependencyBuilder object.
   *
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypeManager
   *   The field type plugin manager.
   * @param \Drupal\custom_formatters\FormatterTypeManager $formatterTypeManager
   *   The formatter type plugin manager.
   * @param \Drupal\custom_formatters\FormatterExtrasManager $formatterExtrasManager
   *   The formatter extras plugin manager.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected FormatterTypeManager $formatterTypeManager,
    protected FormatterExtrasManager $formatterExtrasManager,
    protected ConfigManagerInterface $configManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Calculates dependencies for a formatter entity.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   *
   * @return array<string, string[]>
   *   Dependencies keyed by type.
   */
  public function calculateDependencies(FormatterInterface $entity): array {
    $dependencies = [];

    $field_type_definitions = $this->fieldTypeManager->getDefinitions();
    $field_types = $entity->get('field_types');
    if (is_array($field_types)) {
      foreach ($field_types as $field_type) {
        if (isset($field_type_definitions[$field_type])) {
          $dependencies['module'][] = $field_type_definitions[$field_type]['provider'];
        }
      }
    }

    $formatter_type = $this->getFormatterType($entity);
    if ($formatter_type !== FALSE) {
      $type_deps = $formatter_type->calculateDependencies();
      if (!empty($type_deps) && is_array($type_deps)) {
        foreach ($type_deps as $type => $type_dependencies) {
          if (!empty($type_dependencies) && is_array($type_dependencies)) {
            foreach ($type_dependencies as $name) {
              $dependencies[$type][] = $name;
            }
          }
        }
      }
    }

    $extras = $this->formatterExtrasManager->getDefinitions();
    if (is_array($extras)) {
      foreach ($extras as $extra) {
        if (!$extra['optional']) {
          $dependencies[$extra['provider']][] = 'extra';
        }
      }
    }

    return $dependencies;
  }

  /**
   * Gets the formatter type plugin for an entity.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   *
   * @return \Drupal\custom_formatters\FormatterTypeInterface|false
   *   The formatter type plugin or FALSE if not available.
   */
  public function getFormatterType(FormatterInterface $entity): FormatterTypeInterface|false {
    if (!isset($this->formatterTypeManager->getDefinitions()[$entity->get('type')])) {
      return FALSE;
    }

    $result = $this->formatterTypeManager->createInstance($entity->get('type'), ['entity' => $entity]);
    assert($result instanceof FormatterTypeInterface);

    return $result;
  }

  /**
   * Gets dependent config entities for a formatter.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $entity
   *   The formatter entity.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The dependent entities.
   */
  public function getDependentEntities(FormatterInterface $entity): array {
    return $this->configManager->findConfigEntityDependenciesAsEntities('config', [$entity->getConfigDependencyName()]);
  }

  /**
   * Gets the raw custom formatters settings.
   *
   * @return array
   *   The raw settings data.
   */
  public function getSettings(): array {
    return $this->configFactory->get('custom_formatters.settings')->getRawData();
  }

  /**
   * Gets the formatter entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  public function getEntityStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('formatter');
  }

}
