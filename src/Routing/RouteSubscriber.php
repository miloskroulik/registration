<?php

namespace Drupal\registration\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Registration routes.
 *
 * @see \Drupal\registration\Plugin\Derivative\RegistrationLocalTask
 */
class RouteSubscriber extends RouteSubscriberBase {

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
   * Creates a RouteSubscriber object.
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
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Update the registration canonical route to use the admin theme.
    // Users without permission to use it fall back to the front end theme.
    if ($route = $collection->get('entity.registration.canonical')) {
      $route->setOption('_admin_route', TRUE);
    }

    // Add a cancel form route.
    $entity_type = $this->entityTypeManager->getDefinition('registration');
    if ($route = $this->getCancelFormRoute($entity_type)) {
      $collection->add("entity.registration.cancel", $route);
    }

    // Add routes for managing registrations and registering.
    foreach ($this->registrationManager->getRegistrationEnabledEntityTypes() as $entity_type_id => $entity_type) {
      if ($route = $this->registrationManager->getRoute($entity_type, 'broadcast')) {
        $collection->add("entity.$entity_type_id.registration.broadcast", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'manage')) {
        $collection->add("entity.$entity_type_id.registration.manage_registrations", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'register')) {
        $collection->add("entity.$entity_type_id.registration.register", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'settings')) {
        $collection->add("entity.$entity_type_id.registration.registration_settings", $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes'];
    return $events;
  }

  /**
   * Gets the cancel-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCancelFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('cancel-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('cancel-form'));
      $route
        ->addDefaults([
          '_entity_form' => "$entity_type_id.cancel",
          '_title' => 'Cancel registration',
        ])
        ->setRequirement('_entity_access', "$entity_type_id.cancel")
        ->setRequirement('registration', '\d+')
        ->setOption('_admin_route', TRUE)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
    return NULL;
  }

}
