<?php

namespace Drupal\registration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Checks access for the Register route.
 */
class RegisterAccessCheck implements AccessInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

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
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    $this->currentUser = $current_user;
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
    // Initialize.
    $field = NULL;
    $registration_settings_entity = NULL;

    // Retrieve the host entity.
    $entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters());

    // If the request has an entity with its registration field set,
    // and the host entity has the enable registrations setting,
    // then allow access if the user has the appropriate permission.
    if ($entity) {
      $field = $this->registrationManager->getRegistrationField($entity);
      if ($field && !$entity->get($field->getName())->isEmpty()) {
        $storage = $this->entityTypeManager->getStorage('registration_settings');
        $registration_settings_entity = $storage->loadSettingsForEntity($entity);

        $status = (bool) $this->registrationManager->getRegistrationSetting(
          $entity, $registration_settings_entity, 'status');

        if ($status) {
          return AccessResult::allowedIfHasPermissions($account, ['manage registrations'])
            // Recalculate this result if  the relevant entities are updated.
            ->addCacheableDependency($entity)
            ->addCacheableDependency($registration_settings_entity)
            ->addCacheableDependency($field);
        }
      }
    }

    // No entity or its registration field is set to disable registrations.
    // Disable the route. This also hides the local task (tab) for the route.
    $access_result = AccessResult::forbidden();

    // Recalculate this result if  the relevant entities are updated.
    if ($account->id() === $this->currentUser->id()) {
      $access_result->cachePerPermissions();
    }
    if ($entity) {
      $access_result->addCacheableDependency($entity);
    }
    if ($registration_settings_entity) {
      $access_result->addCacheableDependency($registration_settings_entity);
    }
    if ($field) {
      $access_result->addCacheableDependency($field);
    }
    return $access_result;
  }

}
