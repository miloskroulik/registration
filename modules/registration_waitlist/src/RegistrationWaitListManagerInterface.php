<?php

namespace Drupal\registration_waitlist;

/**
 * Defines the interface for the registration wait list manager service.
 */
interface RegistrationWaitListManagerInterface {

  /**
   * Automatically fills spots in standard capacity from the wait list.
   *
   * @param \Drupal\registration_waitlist\HostEntityInterface $host_entity
   *   The host entity to automatically fill.
   */
  public function autoFill(HostEntityInterface $host_entity);

}
