<?php

namespace Drupal\registration\Plugin\views\area;

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
      $route_match = \Drupal::routeMatch();
      $registration_manager = \Drupal::service('registration.manager');
      if ($entity = $registration_manager->getEntityFromParameters($route_match->getParameters())) {
        return [
          '#markup' => $this->t('There are no registrants for %name', [
            '%name' => $entity->label(),
          ]),
        ];
      }
    }
    return [];
  }

}
