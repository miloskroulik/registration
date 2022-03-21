<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\registration\RegistrationState;

/**
 * Defines the interface for registrations.
 */
interface RegistrationInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the registration creation timestamp.
   *
   * @return int
   *   The registration creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the registration creation timestamp.
   *
   * @param int $timestamp
   *   The registration creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime(int $timestamp): RegistrationInterface;

  /**
   * Gets the registration state.
   *
   * @return \Drupal\registration\RegistrationState
   *   The registration state.
   */
  public function getState(): RegistrationState;

}
