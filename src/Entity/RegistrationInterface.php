<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the interface for registrations.
 */
interface RegistrationInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * If user has access to create registrations for his/her account.
   */
  const REGISTRATION_REGISTRANT_TYPE_ME = 'registration_registrant_type_me';

  /**
   * If user has access to create registrations for other users.
   */
  const REGISTRATION_REGISTRANT_TYPE_USER = 'registration_registrant_type_user';

  /**
   * If user has access to create registrations for people identified by email.
   */
  const REGISTRATION_REGISTRANT_TYPE_ANON = 'registration_registrant_type_anon';

  /**
   * Gets the display name for the creator of the registration.
   *
   * @return string|null
   *   The author name or NULL for a new registration.
   */
  public function getAuthorDisplayName(): ?string;

  /**
   * Gets the registrant type relative to the given account.
   *
   * @return string|null
   *   The registrant type as a constant, if available.
   */
  public function getRegistrantType(AccountInterface $account): ?string;

  /**
   * Gets the number of spaces reserved by the registration.
   *
   * @return int
   *   The number of spaces.  Defaults to 1 for a new registration.
   */
  public function getSpacesReserved(): int;

  /**
   * Gets the registration type.
   *
   * @return \Drupal\registration\Entity\RegistrationTypeInterface
   *   The workflow.
   */
  public function getType(): RegistrationTypeInterface;

  /**
   * Gets the workflow that the registration is in.
   *
   * @return \Drupal\workflows\WorkflowInterface
   *   The workflow.
   */
  public function getWorkflow(): WorkflowInterface;

  /**
   * Gets the registration state.
   *
   * @return \Drupal\workflows\StateInterface
   *   The registration state.
   */
  public function getState(): StateInterface;

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
   * Determines if a registration is in an active state.
   *
   * @return bool
   *   TRUE if the registration is in an active state.
   */
  public function isActive(): bool;

}
