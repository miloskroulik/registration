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
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $host_entity = $entity->getHostEntity();

    // Some operations require a host entity configured for registration.
    if (in_array($operation, ['update', 'administer'])) {
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
      // The most global permissions don't depend on anything about the
      // registration or host.
      $permissions = ["administer registration"];
      if ($operation !== 'administer') {
        $permissions[] = "$operation any registration";
      }
      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      if ($result->isNeutral()) {
        $result = $this->checkEntityUserPermissions($entity, $operation, $account);
        // All of these checks depend on the registration type, host or
        // registrant.
        $result->addCacheableDependency($entity);
      }
    }

    return $result;
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
    if ($operation === 'administer') {
      return $this->checkEntityUserPermissionsForAdministerOperation($entity, $account);
    }

    $result = AccessResult::allowedIfHasPermissions($account, [
      "administer {$entity->bundle()} registration",
      "$operation any {$entity->bundle()} registration",
    ], 'OR');

    // The "host" permission grants access if the user can edit the host entity.
    if (($result->isNeutral()) && ($host_entity = $entity->getHostEntity())) {
      $result = $host_entity->access($operation . ' registrations', $account, TRUE);
    }

    // The own results cache per user so they're less performant, and only
    // matter if a less granular permissions-based approach has not given
    // access.
    if ($result->isNeutral()) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
      if ($account->id() && ($account->id() == $entity->getUserId())) {
        $own_result = AccessResult::allowedIfHasPermissions($account, [
          "administer own {$entity->bundle()} registration",
          "$operation own registration",
          "$operation own {$entity->bundle()} registration",
        ], 'OR')
          // The "own" permission is based on the current user's ID, so the
          // result must be cached per user.
          ->cachePerUser();
        // Even a neutral overall result should be cached per user, as it might
        // have been allowed based on the account being the registrant.
        $result = $result->orIf($own_result);
      }
    }

    return $result;
  }

  /**
   * Checks the entity permissions for the 'administer' operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkEntityUserPermissionsForAdministerOperation(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, "administer {$entity->bundle()} registration");
    if ($result->isNeutral() && ($host_entity = $entity->getHostEntity())) {
      $result = $host_entity->access('administer registrations', $account, TRUE)->orIf($result);
    }
    if ($result->isNeutral()) {
      if ($account->id() && ($account->id() == $entity->getUserId())) {
        // The "own" permission is based on the current user's ID, so the
        // result must be cached per user.
        $result = AccessResult::allowedIfHasPermission($account, "administer own {$entity->bundle()} registration")
          ->cachePerUser()
          ->orIf($result);
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultReasonInterface|AccessResult|AccessResultInterface {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($result->isNeutral()) {
      $permissions = [
        $this->entityType->getAdminPermission() ?: 'administer registration',
        'create registration',
      ];
      if ($entity_bundle) {
        $permissions[] = 'create ' . $entity_bundle . ' registration self';
        $permissions[] = 'create ' . $entity_bundle . ' registration other users';
        $permissions[] = 'create ' . $entity_bundle . ' registration other anonymous';
      }

      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return $result;
  }

}
