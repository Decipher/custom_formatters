<?php

declare(strict_types=1);

namespace Drupal\custom_formatters;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\file\FileInterface;

/**
 * Field item list that wraps a file entity for Insert rendering.
 *
 * Extends EntityReferenceFieldItemList (rather than FieldItemList) so that
 * formatters such as ImageFormatter that type-hint against
 * EntityReferenceFieldItemListInterface receive the correct type.
 *
 * In Drupal 11, content entities no longer implement TypedDataInterface,
 * so they cannot be passed as the $parent to FieldItemList. This subclass
 * stores the file entity separately and returns it from getEntity().
 */
class InsertFieldItemList extends EntityReferenceFieldItemList {

  /**
   * The file entity rendered by this field item list.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $entity;

  /**
   * Constructs an InsertFieldItemList.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The field definition.
   * @param string $name
   *   The field name.
   * @param \Drupal\file\FileInterface $file
   *   The file entity to render.
   * @param string $langcode
   *   The language code.
   */
  public function __construct(DataDefinitionInterface $definition, string $name, FileInterface $file, string $langcode) {
    parent::__construct($definition, $name, NULL);
    $this->entity = $file;
    $this->langcode = $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity;
  }

}
