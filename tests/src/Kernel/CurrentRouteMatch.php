<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Routing\CurrentRouteMatch as BaseCurrentRouteMatch;
use Drupal\Core\Routing\RouteMatch;

/**
 * Overrides the current_route_match service for testing.
 */
class CurrentRouteMatch extends BaseCurrentRouteMatch {

  /**
   * Returns the route match for the current request.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The current route match object.
   */
  public function getCurrentRouteMatch() {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $entity_type = $node->getEntityType();
    $registration_manager = \Drupal::service('registration.manager');
    $route = $registration_manager->getRoute($entity_type, 'register');
    $route_name = $registration_manager->getBaseRouteName($entity_type) . '.register';
    return new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);
  }

}
