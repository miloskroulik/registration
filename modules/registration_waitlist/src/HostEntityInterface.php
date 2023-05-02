<?php

namespace Drupal\registration_waitlist;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface as BaseHostEntityInterface;

/**
 * Extends the interface for the host entity.
 */
interface HostEntityInterface extends BaseHostEntityInterface {

  /**
   * Gets the spaces remaining on the wait list.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the spaces
   *   reserved when calculating the wait list spaces remaining.
   *
   * @return int|null
   *   The spaces remaining on the wait list, if available. Returns NULL if the
   *   wait list is disabled or the wait list capacity is unlimited (0).
   */
  public function getWaitListSpacesRemaining(RegistrationInterface $registration = NULL): ?int;

  /**
   * Gets the reserved spaces for registrations in the wait list.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return int
   *   The total number of reserved spaces for registrations in the wait list.
   */
  public function getWaitListSpacesReserved(RegistrationInterface $registration = NULL): int;

  /**
   * Determines if a host entity has spaces remaining off the wait list.
   *
   * This simply calls the base class implementation of hasRoom.
   *
   * @param int $spaces
   *   (optional) The number of spaces requested. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return bool
   *   TRUE if there are spaces remaining off the wait list, FALSE otherwise.
   */
  public function hasRoomOffWaitList(int $spaces = 1, RegistrationInterface $registration = NULL): bool;

  /**
   * Determines if a host entity has spaces remaining on its wait list.
   *
   * @param int $spaces
   *   (optional) The number of spaces requested. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return bool
   *   TRUE if there are spaces remaining on the wait list, FALSE otherwise.
   */
  public function hasRoomOnWaitList(int $spaces = 1, RegistrationInterface $registration = NULL): bool;

  /**
   * Determines whether the wait list is enabled.
   *
   * Always returns FALSE unless the registration_waitlist submodule is
   * installed, and the wait list registration setting is enabled for
   * the host entity.
   *
   * @return bool
   *   TRUE if the wait list is enabled, FALSE otherwise.
   */
  public function isWaitListEnabled(): bool;

}
