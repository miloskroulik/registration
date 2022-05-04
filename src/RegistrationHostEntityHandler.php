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
class RegistrationHostEntityHandler extends EntityHandlerBase implements RegistrationHostEntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function createHostEntity(EntityInterface $entity, string $langcode = NULL): HostEntityInterface {
    return new HostEntity($entity, $langcode);
  }

}
