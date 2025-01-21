<?php

namespace Drupal\registration;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;

/**
 * Defines the interface for the host entity.
 *
 * This is a pseudo-entity wrapper around a real entity. It provides a
 * mechanism for extending the functionality of content entities without
 * having to override the content entity base class.
 */
interface HostEntityInterface extends ScopeEntityInterface {

  /**
   * Gets the reserved spaces in active registrations.
   *
   * Includes active and held states.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return int
   *   The total number of reserved spaces for active registrations.
   *
   * @deprecated in registration:3.4.0 and is removed from registration:4.0.0.
   *   Use getSpacesReserved() instead.
   * @see https://www.drupal.org/node/xxxx
   */
  public function getActiveSpacesReserved(?RegistrationInterface $registration = NULL): int;


  /**
   * Adds cache information to a render array.
   *
   * @param array $build
   *   The render array to modify.
   * @param \Drupal\Core\Entity\EntityInterface[] $other_entities
   *   (optional) Other entities that should be added as dependencies.
   */
  public function addCacheableDependencies(array &$build, array $other_entities = []);

  /**
   * Creates a new registration.
   *
   * @param bool $save
   *   Whether the new entity should be saved after being created.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The new registration.
   */
  public function createRegistration(bool $save = FALSE): RegistrationInterface;

  /**
   * Generates a sample registration for use in tests and email preview.
   *
   * Saving is optional but not recommended since it contains sample data.
   *
   * @param bool $save
   *   Whether the new entity should be saved after being generated.
   *
   * @return \Drupal\registration\Entity\RegistrationInterface
   *   The generated registration.
   */
  public function generateSampleRegistration(bool $save = FALSE): RegistrationInterface;

  /**
   * Gets the registration type.
   *
   * @return \Drupal\registration\Entity\RegistrationTypeInterface|null
   *   The registration type, if available.
   */
  public function getRegistrationType(): ?RegistrationTypeInterface;

  /**
   * Gets the value of the registration type field.
   *
   * This is a Registration Type bundle machine name.
   *
   * @return string|null
   *   The bundle, if available.
   */
  public function getRegistrationTypeBundle(): ?string;

  /**
   * Gets the spaces remaining.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the spaces
   *   reserved when calculating the spaces remaining.
   *
   * @return int|null
   *   The number of spaces remaining, or NULL if the capacity is unlimited (0).
   *
   * @deprecated in registration:3.4.0 and is removed from registration:4.0.0.
   *   Use getSpacesAvailable() instead.
   * @see https://www.drupal.org/node/xxxx
   */
  public function getSpacesRemaining(?RegistrationInterface $registration = NULL): ?int;

  /**
   * Determines whether a host entity is configured for registration.
   *
   * A host entity is configured for registration if it has a registration
   * field, and the field value is set to the name of a registration type.
   *
   * @return bool
   *   TRUE if configured, FALSE otherwise.
   */
  public function isConfiguredForRegistration(): bool;

  /**
   * Determines if an existing registration can be edited by a given account.
   *
   * This checks to make sure registrations are enabled in the settings, and
   * it is not after the close date if one is set, when a regular user account
   * attempts to edit an existing registration.
   *
   * This method always returns TRUE, or a valid result object, for accounts
   * that have administrative access to the registration, even if registration
   * is disabled in the settings, or it is after the close date.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration to check.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The account. Defaults to the logged-in user if not set.
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\registration\RegistrationValidationResultInterface
   *   Returns a boolean if $return_as_object is FALSE (this is the default),
   *   and otherwise a RegistrationValidationResultInterface object. When an
   *   object is returned, it contains any violations that prevent editing.
   */
  public function isEditableRegistration(RegistrationInterface $registration, ?AccountInterface $account = NULL, bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface;

  /**
   * Validates an object.
   *
   * If the object is a registration, checks all aspects of the registration
   * against the settings.
   *
   * @param mixed $value
   *   The value to validate, e.g. an entity or other object. This is most often
   *   a registration entity, but can be any value or object relevant to
   *   registrations. If the value is not a registration entity, the calling
   *   application must provide an event subscriber that provides the
   *   validation.
   *
   * @return \Drupal\registration\RegistrationValidationResultInterface
   *   The result of the validation check.
   */
  public function validate(mixed $value): RegistrationValidationResultInterface;

}
