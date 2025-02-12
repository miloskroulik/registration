<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the Register route.
 *
 * The Register route displays the Register form, which allows
 * site visitors to create new registrations by registering
 * for events or appropriately configured entity types.
 */
class RegisterAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * RegisterAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    $validation_result = NULL;

    // Retrieve the host entity.
    $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
    if ($host_entity) {
      $validation_result = $host_entity->isOpenForRegistration(TRUE);
      if ($validation_result->isValid()) {
        // Registration is open for the host entity. Check if the account
        // has create registration permissions for the registration type.
        $bundle = $host_entity->getRegistrationTypeBundle();
        return $this->entityTypeManager
          ->getAccessControlHandler('registration')
          ->createAccess($bundle, $account, [], TRUE)
          // Recalculate this result if the relevant entities are updated.
          // This is crucial so the Register tab and form can display for
          // some users and host entities, and not for others.
          ->cachePerPermissions()
          ->addCacheableDependency($validation_result);
      }
    }

    // No host entity is available, or the host entity is not open for
    // registration. Return neutral so other modules can have a say in
    // whether registration is allowed. Most likely no other module will
    // allow the registration, so this will disable the route. This would
    // in turn hide the Register tab within the host entity local tasks.
    $access_result = AccessResult::neutral();

    // Recalculate this result if the relevant entities are updated.
    $access_result->cachePerPermissions();
    if ($validation_result) {
      $access_result->addCacheableDependency($validation_result);
    }
    return $access_result;
  }

}
