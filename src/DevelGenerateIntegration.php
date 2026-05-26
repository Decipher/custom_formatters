<?php

declare(strict_types=1);

/**
 * @file
 * Devel Generate integration for preview sample data generation.
 */

namespace Drupal\custom_formatters;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;

/**
 * Provides Devel Generate integration for generating preview sample entities.
 */
class DevelGenerateIntegration {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info service.
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a DevelGenerateIntegration object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Checks whether Devel Generate is available.
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('devel_generate');
  }

  /**
   * Checks whether an entity type is supported for sample generation.
   */
  public function isEntityTypeSupported(string $entity_type_id): bool {
    return in_array($entity_type_id, ['node', 'taxonomy_term', 'user', 'media'], TRUE);
  }

  /**
   * Generates a transient sample entity with populated field data.
   *
   * The entity is created in memory only — it is NOT saved to the database.
   * This matches the D7 behavior where Devel Generate produced temporary
   * objects for preview purposes.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   The generated entity object, or NULL on failure.
   */
  public function generateEntity(string $entity_type_id, string $bundle): ?FieldableEntityInterface {
    if (!$this->isAvailable() || !$this->isEntityTypeSupported($entity_type_id)) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      $values = [];
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $values[$bundle_key] = $bundle;
      }

      switch ($entity_type_id) {
        case 'node':
          $values['title'] = 'Sample ' . $this->getBundleLabel($entity_type_id, $bundle);
          $values['status'] = 1;
          break;

        case 'taxonomy_term':
          $values['name'] = 'Sample ' . $this->getBundleLabel($entity_type_id, $bundle);
          break;

        case 'user':
          $values['name'] = 'sample_user';
          $values['mail'] = 'sample@example.com';
          $values['status'] = 1;
          break;

        case 'media':
          $values['name'] = 'Sample ' . $this->getBundleLabel($entity_type_id, $bundle);
          $values['status'] = 1;
          break;
      }

      $entity = $storage->create($values);
      assert($entity instanceof FieldableEntityInterface);

      foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
        if ($definition instanceof FieldConfigInterface && $entity->hasField($field_name)) {
          $field = $entity->get($field_name);
          if ($field->isEmpty()) {
            try {
              $field->generateSampleItems();
            }
            catch (\Throwable) {
              // Some field types may not support sample generation.
            }
          }
        }
      }

      return $entity;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Returns the human-readable label for a bundle.
   */
  protected function getBundleLabel(string $entity_type_id, string $bundle): string {
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    return $bundle_info[$bundle]['label'] ?? $bundle;
  }

}
