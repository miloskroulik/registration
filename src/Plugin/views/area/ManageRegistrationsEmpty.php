<?php

namespace Drupal\registration\Plugin\views\area;

use Drupal;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Defines an area used when there are no registrations to manage.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_registrations_empty")
 */
class ManageRegistrationsEmpty extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if (!$empty || !empty($this->options['empty'])) {
      $route_match = Drupal::routeMatch();
      $registration_manager = Drupal::service('registration.manager');
      if ($host_entity = $registration_manager->getEntityFromParameters($route_match->getParameters())) {
        return [
          '#markup' => $this->t('There are no registrants for %name', [
            '%name' => $host_entity->label(),
        ])];
      }
    }
    return [];
  }

}
