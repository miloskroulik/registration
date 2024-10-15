<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationTypeInterface;

/**
 * Defines the interface for the host entity.
 *
 * This is a pseudo-entity wrapper around a real entity. It provides a
 * mechanism for extending the functionality of content entities without
 * having to override the content entity base class.
 */
interface HostEntityInterface extends AccessibleInterface {

  /**
   * Gets the bundle of the wrapped entity.
   *
   * This is a machine name, e.g., "event".
   *
   * @return string
   *   The bundle of the wrapped entity. Defaults to the entity type ID if the
   *   entity type does not make use of different bundles.
   */
  public function bundle(): string;

  /**
   * Gets the wrapped real entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The wrapped real entity.
   */
  public function getEntity(): EntityInterface;

  /**
   * Gets the ID of the type of the wrapped entity.
   *
   * This is a machine name, e.g., "node".
   *
   * @return string
   *   The entity type ID of the wrapped entity.
   */
  public function getEntityTypeId(): string;

  /**
   * Gets the entity type label of the type of the wrapped entity.
   *
   * If the entity type has bundles, the bundle label is returned instead.
   *
   * @return string
   *   The host entity type or bundle label, for example "Event".
   */
  public function getEntityTypeLabel(): string;

  /**
   * Gets the identifier of the wrapped entity.
   *
   * @return string|int|null
   *   The entity identifier, or NULL if the object does not yet have an
   *   identifier.
   */
  public function id(): string|int|NULL;

  /**
   * Determines whether the wrapped entity is new.
   *
   * Usually an entity is new if no ID exists for it yet. However, entities may
   * be enforced to be new with existing IDs too.
   *
   * @return bool
   *   TRUE if the entity is new, or FALSE if the entity has already been saved.
   *
   * @see \Drupal\Core\Entity\EntityInterface::enforceIsNew()
   */
  public function isNew(): bool;

  /**
   * Gets the label of the wrapped entity.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The label of the wrapped entity, or NULL if there is no label defined.
   */
  public function label(): string|TranslatableMarkup|NULL;

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
   * Gets the reserved spaces in active registrations.
   *
   * Includes active and held states.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return int
   *   The total number of reserved spaces for active registrations.
   */
  public function getActiveSpacesReserved(?RegistrationInterface $registration = NULL): int;

  /**
   * Gets the spaces remaining.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the spaces
   *   reserved when calculating the spaces remaining.
   *
   * @return int|null
   *   The number of spaces remaining, or NULL if the capacity is unlimited (0).
   */
  public function getSpacesRemaining(?RegistrationInterface $registration = NULL): ?int;

  /**
   * Gets the default registration settings.
   *
   * @param string|null $langcode
   *   (optional) The language for the settings field.
   *   If not set, the host entity language is used.
   *
   * @return array
   *   The default registration settings for.
   */
  public function getDefaultSettings(?string $langcode = NULL): array;

  /**
   * Gets the total number of registrations.
   *
   * Note that this is the number of registrations, not the spaces reserved.
   *
   * @return int
   *   The count of registrations (any status).
   */
  public function getRegistrationCount(): int;

  /**
   * Gets the definition of the registration field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition, if available.
   */
  public function getRegistrationField(): ?FieldDefinitionInterface;

  /**
   * Gets the list of registrations.
   *
   * @param array $states
   *   (optional) An array of state IDs to filter on.
   *   For example: ['complete', 'held'].
   * @param string|null $langcode
   *   (optional) The language code to filter on.
   *   If no language code is provided, the host entity language is used.
   *
   * @return \Drupal\registration\Entity\Registration[]
   *   The list of registrations.
   */
  public function getRegistrationList(array $states = [], ?string $langcode = NULL): array;

  /**
   * Gets a query of registrations for the host.
   *
   * Conditions are automatically added for the host and for the specified
   * properties. If an account or email are passed, further conditions are
   * added to find any registration that person is a registrant for.
   *
   * @param array|null $properties
   *   (optional) An associative array where the keys are the property names
   *   and the values are the values those properties must have.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account of the registrant.
   * @param string|null $email
   *   (optional) The email address of the registrant.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The registrations query.
   */
  public function getRegistrationQuery(array $properties = [], ?AccountInterface $account = NULL, $email = NULL): QueryInterface;

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
   * Gets a settings value for a given key.
   *
   * @param string $key
   *   The setting name, for example "status", "reminder date" etc.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getSetting(string $key): mixed;

  /**
   * Gets the registration settings entity.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings|null
   *   The settings entity. A new entity is created (but not saved) if needed.
   */
  public function getSettings(): ?RegistrationSettings;

  /**
   * Determines if a host entity has spaces remaining.
   *
   * @param int $spaces
   *   (optional) The number of spaces requested. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return bool
   *   TRUE if there are spaces remaining, FALSE otherwise.
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool;

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
   * Determines whether new registrations are allowed.
   *
   * This checks to make sure registrations are enabled in the settings, and
   * ensures new registrations would occur within the open and close dates if
   * those are set. If those checks pass and the host entity has room for
   * more registrations, then new registrations are allowed.
   *
   * This function should only be called for host entities that are already
   * known to be configured for registration.
   *
   * @param int $spaces
   *   (optional) The number of spaces requested. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   * @param array $errors
   *   (optional) If set, any error messages are set into this array.
   *
   * @return bool
   *   TRUE if new registrations are allowed, FALSE otherwise.
   */
  public function isEnabledForRegistration(int $spaces = 1, ?RegistrationInterface $registration = NULL, array &$errors = []): bool;

  /**
   * Determines whether an email address is already registered.
   *
   * This checks the anonymous email field only. To check if a Drupal
   * user account has registered, use the isUserRegistered function.
   *
   * Only registrations in an active or held state are considered. To check
   * against specific states, use the isEmailRegisteredInStates function.
   *
   * @param string $email
   *   The email address to check.
   *
   * @return bool
   *   TRUE if the email address has already registered for the host entity.
   */
  public function isEmailRegistered(string $email): bool;

  /**
   * Determines whether an email address is registered in certain statuses.
   *
   * This checks the anonymous email field only. To check if a Drupal
   * user account has registered, use the isUserRegisteredInStates function.
   *
   * @param string $email
   *   The email address to check.
   * @param array $states
   *   A list of statuses to check, as state IDs.
   *   If the parameter is empty, FALSE is returned.
   *
   * @return bool
   *   TRUE if the email registered for the host and is in a certain status.
   */
  public function isEmailRegisteredInStates(string $email, array $states): bool;

  /**
   * Determines whether a given user is already registered.
   *
   * Only registrations in an active or held state are considered. To check
   * against specific states, use the isUserRegisteredInStates function.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if the user has already registered for the host entity.
   */
  public function isUserRegistered(AccountInterface $account): bool;

  /**
   * Determines whether a given user is already registered in certain statuses.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param array $states
   *   A list of statuses to check, as state IDs.
   *   If the parameter is an empty array, FALSE is returned.
   *
   * @return bool
   *   TRUE if the user registered for the host and is in a certain status.
   */
  public function isUserRegisteredInStates(AccountInterface $account, array $states): bool;

  /**
   * Determines whether a given user is already registered in certain statuses.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account of the registrant.
   * @param string|null $email
   *   (optional) The email address of the registrant.
   * @param array|null $states
   *   (optional) A list of statuses to check. Defaults to active states.
   *
   * @return bool
   *   TRUE if the user registered for the host and is in a certain status.
   */
  public function isRegistrant(?AccountInterface $account = NULL, $email = NULL, array $states = []): bool;

  /**
   * Determines whether it is currently before the open date.
   *
   * Returns FALSE if an open date is not configured.
   *
   * @return bool
   *   TRUE if it is currently before the open date.
   */
  public function isBeforeOpen(): bool;

  /**
   * Determines whether it is currently after the close date.
   *
   * Returns FALSE if a close date is not configured.
   *
   * @return bool
   *   TRUE if it is currently after the close date.
   */
  public function isAfterClose(): bool;

}
