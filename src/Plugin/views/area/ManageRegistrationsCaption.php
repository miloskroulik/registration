<?php

namespace Drupal\registration\Plugin\views\area;

use Drupal;
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
      $route_match = Drupal::routeMatch();
      $registration_manager = Drupal::service('registration.manager');
      if ($host_entity = $registration_manager->getEntityFromParameters($route_match->getParameters())) {
        $settings = $registration_manager->getSettingsForHost($host_entity);
        $capacity = $registration_manager->getRegistrationSetting($host_entity, $settings, 'capacity');
        $spaces =  $registration_manager->getActiveSpacesReserved($host_entity);
        if ($capacity) {
          $caption = $this->formatPlural($capacity,
           'List of registrations for %title. @spaces of 1 space is filled.',
           'List of registrations for %title. @spaces of @count spaces are filled.', [
            '%title' => $host_entity->label(),
            '@capacity' => $capacity,
            '@spaces' => $spaces,
          ]);
        }
        else {
          $caption = $this->formatPlural($spaces,
           'List of registrations for %title. 1 space is filled.',
           'List of registrations for %title. @count spaces are filled.', [
            '%title' => $host_entity->label(),
          ]);
        }
        $build = [
          '#markup' => $caption,
        ];
        $registration_manager->addCacheableDependencies($build, $host_entity, [$settings]);
        return $build;
      }
    }
    return [];
  }

}
