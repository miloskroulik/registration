<?php

namespace Drupal\registration\Entity;

/**
 * Defines the interface for retrieving host entity keys.
 */
interface HostEntityKeysInterface {

  /**
   * Gets the entity ID of the host entity.
   *
   * @return int
   *   The host entity ID.
   */
  public function getHostEntityId(): int|string|NULL;

  /**
   * Gets the entity type ID of the host entity.
   *
   * @return string
   *   The host entity type ID, for example "node".
   */
  public function getHostEntityTypeId(): string;

}
