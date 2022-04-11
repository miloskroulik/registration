<?php

namespace Drupal\registration\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\registration\RegistrationServiceInterface;
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
   * The registration service.
   *
   * @var \Drupal\registration\RegistrationServiceInterface
   */
  protected RegistrationServiceInterface $registration;

  /**
   * Creates a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationServiceInterface $registration_service
   *   The registration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RegistrationServiceInterface $registration_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->registration = $registration_service;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route = $this->registration->getRoute($entity_type, 'broadcast')) {
        $collection->add("entity.$entity_type_id.broadcast", $route);
      }
      if ($route = $this->registration->getRoute($entity_type, 'manage')) {
        $collection->add("entity.$entity_type_id.manage_registrations", $route);
      }
      if ($route = $this->registration->getRoute($entity_type, 'register')) {
        $collection->add("entity.$entity_type_id.register", $route);
      }
      if ($route = $this->registration->getRoute($entity_type, 'settings')) {
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
