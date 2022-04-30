<?php

namespace Drupal\registration\Plugin\views\area;

use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Defines a caption area handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("manage_registrations_caption")
 */
class ManageRegistrationsCaption extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if (!$empty || !empty($this->options['empty'])) {
      $route_match = \Drupal::routeMatch();
      $registration_manager = \Drupal::service('registration.manager');
      if ($host_entity = $registration_manager->getEntityFromParameters($route_match->getParameters(), TRUE)) {
        $settings = $host_entity->getSettings();
        $capacity = $settings->getSetting('capacity');
        $spaces = $host_entity->getActiveSpacesReserved();
        if ($capacity) {
          $caption = $this->formatPlural($capacity,
           'List of registrations for %label. @spaces of 1 space is filled.',
           'List of registrations for %label. @spaces of @count spaces are filled.', [
             '%label' => $host_entity->label(),
             '@capacity' => $capacity,
             '@spaces' => $spaces,
           ]);
        }
        else {
          $caption = $this->formatPlural($spaces,
           'List of registrations for %label. 1 space is filled.',
           'List of registrations for %label. @count spaces are filled.', [
             '%label' => $host_entity->label(),
           ]);
        }
        $build = [
          '#markup' => $caption,
        ];
        $host_entity->addCacheableDependencies($build, [$settings]);
        return $build;
      }
    }
    return [];
  }

}
