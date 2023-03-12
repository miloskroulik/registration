<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for registration settings.
 */
class RegistrationSettingsAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $account = $this->prepareUser($account);
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $result = $this->checkEntityUserPermissions($entity, $operation, $account);
    }

    // Ensure that access is evaluated again when the entity changes.
    return $result->addCacheableDependency($entity);
  }

  /**
   * Checks the entity operation and bundle permissions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'view label', 'update',
   *   'duplicate' or 'delete'.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkEntityUserPermissions(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\registration\Entity\RegistrationSettings $entity */
    $host_entity = $entity->getHostEntity();
    $type = $host_entity?->getRegistrationTypeBundle();

    if (!$type) {
      // The host entity is not configured for registration.
      $result = AccessResult::neutral();
      if ($host_entity) {
        $result->addCacheableDependency($host_entity->getEntity());
      }
      return $result;
    }

    $result = AccessResult::allowedIfHasPermissions($account, [
      "administer registration",
      "administer $type registration",
    ], 'OR');

    if ($result->isAllowed()) {
      return $result->addCacheableDependency($host_entity->getEntity());
    }

    // Check "own" permission if access not granted yet.
    $entity = $host_entity->getEntity();
    $access = ($account->hasPermission("administer own $type registration") && $entity->access('update', $account));
    return AccessResult::allowedIf($access)
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultReasonInterface|AccessResult|AccessResultInterface {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($result->isNeutral()) {
      $result = AccessResult::allowedIfHasPermission($account, 'administer registration');
    }

    return $result;
  }

}
