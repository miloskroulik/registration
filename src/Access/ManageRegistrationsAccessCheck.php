<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\RegistrationServiceInterface;

/**
 * Checks access for the Manage Registrations task.
 */
class ManageRegistrationsAccessCheck implements AccessInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The registration service.
   *
   * @var \Drupal\registration\RegistrationServiceInterface
   */
  protected RegistrationServiceInterface $registration;

  /**
   * ManageRegistrationsAccessCheck constructor.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   * @param \Drupal\registration\RegistrationServiceInterface $registration_service
   *   The registration service.
   */
  public function __construct(AccountProxy $current_user, RegistrationServiceInterface $registration_service) {
    $this->currentUser = $current_user;
    $this->registration = $registration_service;
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
    $entity = $this->registration->getEntityFromParameters($route_match->getParameters());

    // If the request has an entity with its registration field set,
    // then allow access if the user has the appropriate permission.
    if ($entity) {
      $field = $this->registration->getRegistrationField($entity);
      if ($field && !$entity->get($field->getName())->isEmpty()) {
        return AccessResult::allowedIfHasPermissions($account, ['manage registrations'])->addCacheableDependency($entity);
      }
    }

    // No entity or its registration field is set to disable registrations.
    // Disable the route. This also hides the local task (tab) for the route.
    $access_result = AccessResult::forbidden();

    // Ensure proper caching. Otherwise the task will be hidden until the
    // next cache clear even if the entity is changed to enable registrations.
    if ($account->id() === $this->currentUser->id()) {
      $access_result->cachePerUser();
    }
    if ($entity) {
      $access_result->addCacheableDependency($entity);
    }
    return $access_result;
  }

}
