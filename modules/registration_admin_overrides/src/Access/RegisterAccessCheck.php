<?php

namespace Drupal\registration_admin_overrides\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Access\RegisterAccessCheck as BaseRegisterAccessCheck;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;

/**
 * Checks access for the Register route.
 *
 * The Register route displays the Register form, which allows
 * site visitors to create new registrations by registering
 * for events or appropriately configured entity types.
 *
 * This is the same as the checker in Registration core, except
 * it allows an administrator with the appropriate permissions
 * to register even if registration is disabled for regular users.
 *
 * @see \Drupal\registration\Access\RegisterAccessCheck
 */
class RegisterAccessCheck extends BaseRegisterAccessCheck {

  /**
   * The registration override checker.
   *
   * @var \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface
   */
  protected RegistrationOverrideCheckerInterface $overrideChecker;

  /**
   * RegisterAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface $override_checker
   *   The override checker.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager, RegistrationOverrideCheckerInterface $override_checker) {
    parent::__construct($entity_type_manager, $registration_manager);
    $this->overrideChecker = $override_checker;
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
    // If the core checker allows access there is no need to override.
    $access_result = parent::access($account, $route_match);
    if ($access_result->isAllowed()) {
      return $access_result;
    }

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
      if ($status || $this->overrideChecker->accountCanOverride($host_entity, $account, 'status')) {
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
