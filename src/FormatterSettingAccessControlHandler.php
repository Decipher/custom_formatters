<?php

declare(strict_types=1);

namespace Drupal\custom_formatters;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for the FormatterSetting content entity.
 */
class FormatterSettingAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer custom formatters')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('administer custom formatters')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return parent::checkCreateAccess($account, $context, $entity_bundle);
  }

}
