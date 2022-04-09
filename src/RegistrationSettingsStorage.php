<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function loadSettingsForEntity(EntityInterface $entity) {
    // Look for settings for the given entity.
    $settings = $this->loadByProperties([
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]);

    if (empty($settings)) {
      // Settings entity does not exist yet. Create it.
      $settings_entity = $this->create([
        'entity_type_id' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ]);
    }
    else {
      // The entity exists, return it.
      // There should only be one.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

}
