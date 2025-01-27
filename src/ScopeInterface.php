<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the interface for a registration scope.
 *
 * This provides the context for a registration.
 */
interface ScopeInterface extends AccessibleInterface {

  /**
   * Gets the label of the scope.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The label of the cope, or NULL if there is no label defined.
   */
  public function label(): string|TranslatableMarkup|NULL;

  /**
   * Gets the reserved spaces for this scope.
   *
   * If no states are specified, it defaults to the active and held states.
   *
   * @param array $state_ids
   *   (optional) The id of registration states to include.
   *
   * @return int
   *   The total number of spaces reserved by registrations.
   */
  public function getSpacesReserved(?array $state_ids = []): int;

  /**
   * Gets the close time.
   *
   * This is the earliest close time of all the scopes.
   *
   * @return int|null
   *   The close timestamp, or NULL if not set.
   */
  public function getCloseTime(): ?int;

  /**
   * Gets the open time.
   *
   * This is the latest open time of all the scopes.
   *
   * @return int|null
   *   The open timestamp, or NULL if not set.
   */
  public function getOpenTime(): ?int;

  /**
   * Gets the maximum spaces per registration for this scope.
   *
   * This is the smallest quantity allowed by any scope.
   *
   * @return int|null
   *   The maximum spaces per registration, or NULL if not set.
   */
  public function getMaximumSpaces(): ?int;

  /**
   * Gets the spaces available in this scope.
   *
   * @param string|null $capacity_type
   *   (optional) The name of the setting that contains the capacity.
   *   Defaults to 'capacity'.
   *
   * @return int|null
   *   The number of spaces still available, or NULL if unlimited (0).
   */
  public function getSpacesAvailable(?string $capacity_type): ?int;

  /**
   * Gets the capacity of this scope.
   *
   * This is the number of spaces, regardless of how many are reserved.
   *
   * @param string|null $capacity_type
   *   (optional) The name of the setting that contains the capacity.
   *   Defaults to 'capacity'.
   *
   * @return int|null
   *   The capacity of the scope, or NULL if unlimited.
   */
  public function getCapacity(?string $capacity_type): ?int;

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
   * Gets the list of registrations.
   *
   * @param array $state_ids
   *   (optional) An array of state IDs to filter on.
   *   For example: ['complete', 'held'].
   * @param string|null $langcode
   *   (optional) The language code to filter on.
   *   If no language code is provided, the host entity language is used.
   *
   * @return \Drupal\registration\Entity\Registration[]
   *   The list of registrations.
   */
  public function getRegistrationList(array $state_ids = [], ?string $langcode = NULL): array;

  /**
   * Gets a query of registrations for the host.
   *
   * Conditions are automatically added for the host and for the specified
   * properties. If an account or email are passed, further conditions are
   * added to find any registration that person is a registrant for.
   *
   * @param array $properties
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
  public function getRegistrationQuery(array $properties = [], ?AccountInterface $account = NULL, ?string $email = NULL): QueryInterface;

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
   * Determines whether new registrations are allowed.
   *
   * This checks to make sure registrations are enabled in the settings, and
   * ensures new registrations would occur within the open and close dates if
   * those are set. If those checks pass and the host entity has room for
   * more registrations, then new registrations are allowed.
   *
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\registration\RegistrationValidationResultInterface
   *   Returns a boolean if $return_as_object is FALSE (this is the default),
   *   and otherwise a RegistrationValidationResultInterface object. When an
   *   object is returned, it contains any violations that prevent registration.
   */
  public function isAvailableForRegistration(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface;

  /**
   * Gets the enabled status.
   *
   * This defaults to TRUE, and is FALSE if any scope is disabled.
   *
   * @return bool
   *   The enabled status.
   */
  public function isEnabled(): bool;

  /**
   * Determines whether multiple registrations are allowed for this scope.
   *
   * This defaults to TRUE, and is FALSE if any scope disallows multiple.
   *
   * @return bool
   *   TRUE if multiple registrations are allowed, FALSE otherwise.
   */
  public function isMultipleRegistrationAllowed(): bool;

  /**
   * Determines whether a given user is already registered in certain statuses.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user account of the registrant.
   * @param string|null $email
   *   (optional) The email address of the registrant.
   * @param array $states
   *   (optional) A list of statuses to check. Defaults to active states.
   *
   * @return bool
   *   TRUE if the user registered for the host and is in a certain status.
   */
  public function isRegistrant(?AccountInterface $account = NULL, ?string $email = NULL, array $states = []): bool;

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

  /**
   * Gets the scopes this scope falls within.
   *
   * @return \Drupal\registration\ScopeInterface[]
   *   A array of scopes.
   */
  public function getScopes(): array;

  /**
   * Gets the hosts that fall within this scope.
   *
   * @return \Drupal\registration\HostEntityInterface[]
   *   An array of hosts.
   */
  public function getHosts(): array;

}
