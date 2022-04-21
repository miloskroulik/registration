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
    // Initialize.
    $field = NULL;
    $settings = NULL;

    // Retrieve the host entity.
    $entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters());

    // If the request has an entity with its registration field set,
    // and the host entity has the enable registrations setting,
    // then allow access if the user has the appropriate permission.
    if ($entity && ($field = $this->registrationManager->getRegistrationField($entity))) {
      if ($type = $this->registrationManager->getRegistrationTypeBundle($entity)) {
        $storage = $this->entityTypeManager->getStorage('registration_settings');
        $settings = $storage->loadSettingsForEntity($entity);

        $status = (bool) $this->registrationManager->getRegistrationSetting($entity, $settings, 'status');

        if ($status) {
          return AccessResult::allowedIfHasPermissions($account, ["create $type registration"])
            // Recalculate this result if the relevant entities are updated.
            ->addCacheableDependency($entity)
            ->addCacheableDependency($settings)
            ->addCacheableDependency($field);
        }
      }
    }

    // No entity or its registration field is set to disable registrations.
    // Disable the route. This also hides the local task (tab) for the route.
    $access_result = AccessResult::forbidden();

    // Recalculate this result if the relevant entities are updated.
    $access_result->cachePerPermissions();
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
