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

    // No permission grants access if the settings entity and host are not
    // properly set up.
    if (!$type) {
      // The host entity is not configured for registration.
      $result = AccessResult::neutral();
      if ($host_entity) {
        $result->addCacheableDependency($host_entity->getEntity());
      }
      return $result;
    }

    // Check administrative permissions.
    $result = AccessResult::allowedIfHasPermissions($account, [
      "administer registration",
      "administer $type registration",
      "administer $type registration settings",
    ], 'OR');

    // Manage permissions require managing registrations and settings.
    $manage_result = AccessResult::allowedIfHasPermissions($account, [
      "manage $type registration",
      "manage $type registration settings",
    ], 'AND');
    $result = $result->orIf($manage_result);

    // Only consider host access if simpler permissions don't allow access.
    if ($result->isNeutral()) {
      $host_result = $host_entity->getEntity()->access('update', $account, TRUE);
      // A forbidden value from the host access shouldn't cascade upwards to
      // make the settings result forbidden as that would override access
      // hooks.
      if ($host_result->isForbidden()) {
        $host_result = AccessResult::neutral()->addCacheableDependency($host_result);
      }

      $administer_own_result = AccessResult::allowedIfHasPermissions($account, [
        "administer own $type registration",
        "administer own $type registration settings",
      ], 'OR')
        ->andIf($host_result);

      $manage_own_result = AccessResult::allowedIfHasPermissions($account, [
        "manage own $type registration",
        "manage $type registration settings",
      ], 'AND')
        ->andIf($host_result);

      $result = $result->orIf($administer_own_result)->orIf($manage_own_result);
    }

    // All of the results depend on the settings entity to specify the host,
    // and the host to specify the registration type.
    return $result->addCacheableDependency($entity)->addCacheableDependency($host_entity->getEntity());
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
