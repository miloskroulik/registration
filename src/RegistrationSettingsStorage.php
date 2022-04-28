<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends RegistrationStorage {

  /**
   * Load the settings entity for a given host entity using IDs.
   *
   * Creates one if settings do not exist yet.
   *
   * @param string $host_entity_type_id
   *   The host entity type ID.
   * @param int $host_entity_id
   *   The host entity ID.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  public function loadSettings(string $host_entity_type_id, int $host_entity_id): RegistrationSettings {
    $values = [
      'entity_type_id' => $host_entity_type_id,
      'entity_id' => $host_entity_id,
    ];

    // Look for settings for the given host entity.
    $settings = $this->loadByProperties($values);
    if (empty($settings)) {
      // Settings entity does not exist yet. Create it.
      $settings_entity = $this->create($values);
    }
    else {
      // The entity exists, return it.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

  /**
   * Load the settings entity for a given host entity.
   *
   * Creates one if settings do not exist yet.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  public function loadSettingsForEntity(EntityInterface $host_entity): RegistrationSettings {
    $values = [
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ];

    // Look for settings for the given host entity.
    $settings = $this->loadByProperties($values);
    if (empty($settings)) {
      // Settings entity does not exist yet. Create it.
      $settings_entity = $this->create($values);
    }
    else {
      // The entity exists, return it.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

}
