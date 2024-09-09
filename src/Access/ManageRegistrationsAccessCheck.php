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
    $entity = NULL;
    $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);

    // If the request has an entity with its registration field set,
    // then allow access if the user has the appropriate permission.
    if ($host_entity) {
      if ($type = $host_entity->getRegistrationTypeBundle()) {
        if ($entity = $host_entity->getEntity()) {
          // Check the global administrative permissions.
          $result = AccessResult::allowedIfHasPermissions($account, [
            "administer registration",
            "administer $type registration",
            "administer $type registration settings",
          ], 'OR');

          // Check the global manage permissions.
          $entity_type_id = $entity->getEntityTypeId();
          $route_permissions = [];
          switch ($route_match->getRouteName()) {
            // Manage sending registrant emails.
            case "entity.$entity_type_id.registration.broadcast":
              $route_permissions[] = "manage $type registration broadcast";
              break;

            // Manage registration settings.
            case "entity.$entity_type_id.registration.registration_settings":
              $route_permissions[] = "manage $type registration settings";
              break;

            // Manage registrations. This is always checked below.
            default:
          }

          $manage_result = AccessResult::allowedIfHasPermissions($account, array_merge($route_permissions, ["manage $type registration"]), 'AND');
          $result = $result->orIf($manage_result);

          // Skip checking host access if simpler permissions give access.
          if ($result->isNeutral()) {
            $host_result = $host_entity->getEntity()->access('update', $account, TRUE);
            // A forbidden value from the host access shouldn't cascade upwards
            // to make the result forbidden as that might interfere with other
            // route access checks.
            if ($host_result->isForbidden()) {
              $host_result = AccessResult::neutral()->addCacheableDependency($host_result);
            }

            $administer_own_result = AccessResult::allowedIfHasPermissions($account, [
              "administer own $type registration",
              "administer own $type registration settings",
            ], 'OR')
              ->andIf($host_result);
            $result = $result->orIf($administer_own_result);

            $manage_own_result = AccessResult::allowedIfHasPermissions($account, array_merge($route_permissions, ["manage own $type registration"]), 'AND')
              ->andIf($host_result);
            $result = $result->orIf($manage_own_result);
          }

          // The registration type is specified on the host entity, so all
          // these access checks depend on that as they use type-specific
          // permissions.
          return $result->addCacheableDependency($entity);
        }
      }
    }

    // No entity available, or its registration field is set to disable
    // registrations. Return neutral so other modules can have a say in
    // whether registration is allowed. Most likely no other module will
    // allow the registration, so this will disable the route. This would
    // in turn hide the Manage Registrations tab for the host entity.
    $access_result = AccessResult::neutral();
    if ($host_entity && ($entity = $host_entity->getEntity())) {
      // Recalculate this result if the relevant entities are updated.
      $access_result->addCacheableDependency($entity);
    }
    return $access_result;
  }

}
