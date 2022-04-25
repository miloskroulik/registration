<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    if (empty($values['entity_type_id'])) {
      throw new EntityStorageException('Missing entity type ID for registration');
    }
    if (empty($values['entity_id'])) {
      throw new EntityStorageException('Missing entity ID for registration');
    }
    return parent::doCreate($values);
  }


}
