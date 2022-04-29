<?php

namespace Drupal\registration\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Registration routes.
 *
 * @see \Drupal\registration\Plugin\Derivative\RegistrationLocalTask
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a RouteSubscriber object.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
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

}
