<?php

declare(strict_types=1);

/**
 * @file
 * Defines the Formatter config entity and its implementations.
 */

namespace Drupal\custom_formatters\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\custom_formatters\FormatterDependencyBuilder;
use Drupal\custom_formatters\FormatterInterface;

/**
 * Defines the formatter entity.
 *
 * @ConfigEntityType(
 *   id = "formatter",
 *   label = @Translation("Formatter"),
 *   handlers = {
 *     "access" = "Drupal\custom_formatters\FormatterAccessControlHandler",
 *     "list_builder" = "Drupal\custom_formatters\FormatterListBuilder",
 *     "form" = {
 *       "default" = "Drupal\custom_formatters\Form\FormatterForm",
 *       "edit" = "Drupal\custom_formatters\Form\FormatterForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "formatter",
 *   admin_permission = "administer custom formatters",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "description",
 *     "field_types",
 *     "data",
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/formatters/manage/{formatter}/delete",
 *     "edit-form" = "/admin/structure/formatters/manage/{formatter}",
 *     "collection" = "/admin/structure/formatters",
 *   }
 * )
 */
class Formatter extends ConfigEntityBase implements FormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = $this->dependencyBuilder()->calculateDependencies($this);
    foreach ($dependencies as $type => $names) {
      foreach ($names as $name) {
        $this->addDependency($type, $name);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatterType() {
    return $this->dependencyBuilder()->getFormatterType($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getDependentEntities() {
    return $this->dependencyBuilder()->getDependentEntities($this);
  }

  /**
   * Gets the formatter dependency builder service.
   *
   * @return \Drupal\custom_formatters\FormatterDependencyBuilder
   *   The dependency builder service.
   */
  private function dependencyBuilder(): FormatterDependencyBuilder {
    // @phpstan-ignore staticMethod.thisObjectConfigEntity
    return \Drupal::service('custom_formatters.dependency_builder');
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    /** @var \Drupal\custom_formatters\FormatterInterface $entity */
    foreach ($entities as $entity) {
      $formatter_type = $entity->getFormatterType();
      if ($formatter_type !== FALSE) {
        $formatter_type->postLoad();
      }
    }
    parent::postLoad($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $field_types = $this->get('field_types');
    if (!is_array($field_types)) {
      $this->set('field_types', !empty($field_types) ? [$field_types] : []);
    }

    parent::preSave($storage);
    $formatter_type = $this->getFormatterType();
    if ($formatter_type !== FALSE) {
      $formatter_type->preSave();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    // An entity was created or updated: invalidate its list cache tags. (An
    // updated entity may start to appear in a listing because it now meets that
    // listing's filtering requirements. A newly created entity may start to
    // appear in listings because it did not exist before).
    /** @var array $tags */
    $tags = $this->getEntityType()->getListCacheTags();
    if ($update) {
      // An existing entity was updated, also invalidate its unique cache tag.
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

}
