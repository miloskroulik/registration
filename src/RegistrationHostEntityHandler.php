<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the "host_entity" handler class for registrations.
 *
 * Using a handler allows other modules to override the host
 * entity functions and integrate with third party data sources.
 *
 * @see \Drupal\registration\Entity\Registration
 */
class RegistrationHostEntityHandler extends EntityHandlerBase {

  /**
   * Creates a host entity object given a real entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The real entity.
   *
   * @return \Drupal\registration\HostEntityInterface
   *   The host entity. This is a wrapper and not a real entity.
   */
  public function createHostEntity(EntityInterface $entity): HostEntityInterface {
    return new HostEntity($entity);
  }

}
