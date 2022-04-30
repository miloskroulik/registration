<?php

namespace Drupal\registration;

use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends RegistrationStorage {

  /**
   * Load the settings entity for a given host entity.
   *
   * Creates one if settings do not exist yet.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  public function loadSettingsForHostEntity(HostEntityInterface $host_entity): RegistrationSettings {
    $values = [
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ];

    // Look for settings for the given host entity.
    $settings = $this->loadByProperties($values);
    if (empty($settings)) {
      // Settings entity does not exist yet. Create it.
      $settings_entity = $this
        ->create($values)
        ->initFromConfig($host_entity);
    }
    else {
      // The entity exists, return it.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

}
