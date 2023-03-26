<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for registrations.
 */
class RegistrationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $account = $this->prepareUser($account);

    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $host_entity = $entity->getHostEntity();

    // Update operations require a host entity configured for registration.
    if ($operation == 'update') {
      if (!$host_entity) {
        $result = AccessResult::forbidden("The host entity is missing.");
        return $result->addCacheableDependency($entity);
      }
      if (!$host_entity->isConfiguredForRegistration()) {
        $result = AccessResult::forbidden("The host entity is not configured for registration.");
        if ($host_entity->getEntity()) {
          $result->addCacheableDependency($host_entity->getEntity());
        }
        return $result->addCacheableDependency($entity);
      }
    }

    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $result = $this->checkEntityUserPermissions($entity, $operation, $account);
    }

    // Ensure that access is evaluated again when the host entity changes.
    if ($host_entity && $host_entity->getEntity()) {
      $result->addCacheableDependency($host_entity->getEntity());
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
    // The "any" permission grants access regardless of the entity user.
    $any_result = AccessResult::allowedIfHasPermissions($account, [
      "administer registration",
      "$operation any {$entity->getEntityTypeId()}",
      "$operation any {$entity->bundle()} {$entity->getEntityTypeId()}",
    ], 'OR');

    if ($any_result->isAllowed()) {
      return $any_result;
    }

    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    if ($account->id() && ($account->id() == $entity->getUserId())) {
      $own_result = AccessResult::allowedIfHasPermissions($account, [
        "$operation own {$entity->getEntityTypeId()}",
        "$operation own {$entity->bundle()} {$entity->getEntityTypeId()}",
      ], 'OR');
    }
    else {
      $own_result = AccessResult::neutral()->cachePerPermissions();
    }

    // The "own" permission is based on the current user's ID, so the result
    // must be cached per user.
    return $own_result->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultReasonInterface|AccessResult|AccessResultInterface {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($result->isNeutral()) {
      $permissions = [
        $this->entityType->getAdminPermission() ?: 'administer ' . $this->entityTypeId,
        'create ' . $this->entityTypeId,
      ];
      if ($entity_bundle) {
        $permissions[] = 'create ' . $entity_bundle . ' ' . $this->entityTypeId . ' self';
        $permissions[] = 'create ' . $entity_bundle . ' ' . $this->entityTypeId . ' other users';
        $permissions[] = 'create ' . $entity_bundle . ' ' . $this->entityTypeId . ' other anonymous';
      }

      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return $result;
  }

}
