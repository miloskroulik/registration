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
    // Retrieve the host entity.
    $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);

    // If the request has a host entity with its registration field set,
    // and the host entity has the "enable registrations" setting checked,
    // then allow access if the user has the appropriate permission. The
    // registration type must also have a workflow defined to allow access.
    $entity = $host_entity?->getEntity();
    $field = $host_entity?->getRegistrationField();
    $bundle = $host_entity?->getRegistrationTypeBundle();
    $settings = $host_entity?->getSettings();
    $registration_type = $host_entity?->getRegistrationType();
    if ($field && $bundle && $settings && $registration_type?->getWorkflow()) {
      $status = (bool) $settings->getSetting('status');
      if ($status) {
        // Registration is enabled for the host entity. Check if the account
        // has create registration permissions for the registration type.
        return $this->entityTypeManager
          ->getAccessControlHandler('registration')
          ->createAccess($bundle, $account, [], TRUE)
          // Recalculate this result if the relevant entities are updated.
          // This is crucial so the Register tab and form can display for
          // some users and host entities, and not for others.
          ->cachePerPermissions()
          ->addCacheableDependency($registration_type)
          ->addCacheableDependency($entity)
          ->addCacheableDependency($settings)
          ->addCacheableDependency($field);
      }
    }

    // No host entity available, or its registration field is disabling
    // registrations. Return neutral so other modules can have a say in
    // whether registration is allowed. Most likely no other module will
    // allow the registration, so this will disable the route. This would
    // in turn hide the Register tab within the host entity local tasks.
    $access_result = AccessResult::neutral();

    // Recalculate this result if the relevant entities are updated.
    $access_result->cachePerPermissions();
    if ($registration_type) {
      $access_result->addCacheableDependency($registration_type);
    }
    if ($entity) {
      $access_result->addCacheableDependency($entity);
    }
    if ($settings) {
      $access_result->addCacheableDependency($settings);
    }
    if ($field) {
      $access_result->addCacheableDependency($field);
    }
    return $access_result;
  }

}
