<?php

namespace Drupal\registration\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    if ($route = $collection->get('entity.registration.edit_form')) {
      $route->setOption('_admin_route', FALSE);
    }
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route = $this->registrationManager->getRoute($entity_type, 'broadcast')) {
        $collection->add("entity.$entity_type_id.broadcast", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'manage')) {
        $collection->add("entity.$entity_type_id.manage_registrations", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'register')) {
        $collection->add("entity.$entity_type_id.register", $route);
      }
      if ($route = $this->registrationManager->getRoute($entity_type, 'settings')) {
        $collection->add("entity.$entity_type_id.registration_settings", $route);
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
