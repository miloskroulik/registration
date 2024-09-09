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
      // The most global permissions don't depend on anything about the
      // registration or host.
      $result = AccessResult::allowedIfHasPermissions($account, [
        "administer registration",
        "$operation any registration",
      ], 'OR');

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
    $any_result = AccessResult::allowedIfHasPermissions($account, [
      "administer {$entity->bundle()} registration",
      "$operation any {$entity->bundle()} registration",
    ], 'OR');

    if ($any_result->isAllowed()) {
      return $any_result;
    }

    // The default result.
    $result = AccessResult::neutral();

    // The "host" permission grants access if the user can edit the host entity.
    if (($host_entity = $entity->getHostEntity()) && $host_entity->getEntity()) {
      $host_result = $host_entity->getEntity()->access('update', $account, TRUE);
      // A forbidden value from the host access shouldn't cascade upwards to
      // make the registration result forbidden as that would override
      // registrant's access to their own registration.
      if ($host_result->isForbidden()) {
        $host_result = AccessResult::neutral()->addCacheableDependency($host_result);
      }
      $host_result = AccessResult::allowedIfHasPermission($account, "$operation host registration")
        // Merge the cacheability of the host entity access check via "andIf".
        ->andIf($host_result);
      // The cacheable metadata of the host entity update operation matters
      // even if it does not give permission, because it might have.
      $result = $result->orIf($host_result);
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
