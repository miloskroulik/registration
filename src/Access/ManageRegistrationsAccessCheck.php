<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the Manage Registrations route.
 */
class ManageRegistrationsAccessCheck implements AccessInterface {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * ManageRegistrationsAccessCheck constructor.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
    $this->registrationManager = $registration_manager;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Run access checks for this route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatch $route_match): AccessResultInterface {
    $entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters());

    // If the request has an entity with its registration field set,
    // then allow access if the user has the appropriate permission.
    if ($entity) {
      if ($type = $this->registrationManager->getRegistrationTypeBundle($entity)) {
        $access =
             $account->hasPermission("administer $type registration")
          || ($account->hasPermission("update own $type registration") && $entity->access('update', $account))
        ;
        return AccessResult::allowedIf($access)
          // Recalculate this result if  the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($entity);
      }
    }

    // No entity or its registration field is set to disable registrations.
    // Disable the route. This also hides the local task (tab) for the route.
    $access_result = AccessResult::forbidden();

    // Recalculate this result if  the relevant entities are updated.
    $access_result->cachePerPermissions();
    if ($entity) {
      $access_result->addCacheableDependency($entity);
    }
    return $access_result;
  }

}
