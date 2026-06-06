<?php

declare(strict_types=1);

namespace Drupal\custom_formatters\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\custom_formatters\FormatterSettingInterface;

/**
 * Defines the FormatterSetting content entity.
 *
 * @ContentEntityType(
 *   id = "formatter_setting",
 *   label = @Translation("Formatter setting"),
 *   handlers = {
 *     "access" = "Drupal\custom_formatters\FormatterSettingAccessControlHandler",
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\custom_formatters\Form\FormatterSettingForm",
 *       "edit" = "Drupal\custom_formatters\Form\FormatterSettingForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer custom formatters",
 *   base_table = "formatter_setting",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "bundle" = "formatter"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/formatters/manage/{formatter}/settings/{formatter_setting}/edit",
 *     "delete-form" = "/admin/structure/formatters/manage/{formatter}/settings/{formatter_setting}/delete",
 *   },
 *   bundle_entity_type = "formatter",
 *   field_ui_base_route = "entity.formatter.edit_form",
 * )
 */
class FormatterSetting extends ContentEntityBase implements FormatterSettingInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel): array {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['formatter'] = $this->bundle();
    return $uri_route_parameters;
  }

}
