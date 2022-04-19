<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends SqlContentEntityStorage {

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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function loadSettingsForEntity(EntityInterface $host_entity): RegistrationSettings {
    // Look for settings for the given host entity.
    $settings = $this->loadByProperties([
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
    ]);

    if (empty($settings)) {
      // Settings entity does not exist yet. Create it.
      $settings_entity = $this->create([
        'entity_type_id' => $host_entity->getEntityTypeId(),
        'entity_id' => $host_entity->id(),
      ]);
      $settings_entity->save();
    }
    else {
      // The entity exists, return it.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

}
