<?php

namespace Drupal\registration_admin_overrides\Routing;

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
    // Alter requirements for registering.
    foreach ($this->registrationManager->getRegistrationEnabledEntityTypes() as $entity_type_id => $entity_type) {
      if ($route = $collection->get("entity.$entity_type_id.registration.register")) {
        $route->setRequirements(['_register_access_check_with_override' => 'TRUE']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -10];
    return $events;
  }

}
