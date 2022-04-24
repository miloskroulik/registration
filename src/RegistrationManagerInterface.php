<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the interface for the registration manager service.
 */
interface RegistrationManagerInterface {

  /**
   * Adds cache information to a render array for a given host entity.
   *
   * This allows it to rebuild for different users and when settings change.
   * Used by registration forms and registration related field formatters.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\Core\Entity\EntityInterface[] $other_entities
   *   (optional) Other entities that should be added as dependencies.
   */
  public function addCacheableDependencies(array &$build, EntityInterface $host_entity, array $other_entities = []);

  /**
   * Gets the total number of active registrations for the given host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return int
   *   The count of active registrations.
   */
  public function getActiveRegistrationCount(EntityInterface $host_entity, RegistrationSettings $settings, RegistrationInterface $registration = NULL): int;

  /**
   * Gets the base route name for an entity type.
   *
   * This is typically a canonical route, or an edit-form route as a fallback.
   * For example, 'entity.node.canonical'.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base route name, if available.
   */
  public function getBaseRouteName(EntityTypeInterface $entity_type): ?string;

  /**
   * Gets the first upcasted entity object from a parameter bag.
   *
   * This function should typically be used for requests with a single object.
   *
   * @param \Symfony\Component\HttpFoundation\ParameterBag $parameters
   *   The parameter bag from a request object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL if not found.
   *
   * @see https://www.drupal.org/docs/8/api/routing-system/parameters-in-routes/using-parameters-in-routes
   */
  public function getEntityFromParameters(ParameterBag $parameters): ?EntityInterface;

  /**
   * Gets a setting from registration fields associated with an entity type.
   *
   * Retrieved from the form display containing the registration field widget.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $key
   *   The setting name, for example "hide_register_tab".
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getFieldConfigSetting(EntityTypeInterface $entity_type, string $key): mixed;

  /**
   * Gets the value of a setting from a registration field widget.
   *
   * The value is retrieved from the form display containing the widget.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition for a registration field.
   * @param string $key
   *   The setting name, for example "hide_register_tab".
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getFieldWidgetSetting(EntityTypeInterface $entity_type, FieldDefinitionInterface $field, string $key): mixed;

  /**
   * Determines who can register when a registration is added or edited.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   *
   * @return array
   *   An array keyed by registrant constants.
   *
   * @see \Drupal\registration\Entity\RegistrationInterface for the constants.
   */
  public function getRegistrantOptions(RegistrationInterface $registration, RegistrationSettings $settings): array;

  /**
   * Gets the total number of registrations for the given host entity.
   *
   * Note that this is the number of registrations, not the spaces reserved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   *
   * @return int
   *   The count of registrations (any status).
   */
  public function getRegistrationCount(EntityInterface $host_entity, RegistrationSettings $settings): int;

  /**
   * Gets the entity types that have bundles with registration fields.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity type definitions indexed by machine name.
   */
  public function getRegistrationEnabledEntityTypes(): array;

  /**
   * Gets the definition of the registration field for a host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition, if available.
   */
  public function getRegistrationField(EntityInterface $host_entity): ?FieldDefinitionInterface;

  /**
   * Gets the value of a registration setting for a host entity.
   *
   * If the host entity does not have registration settings yet, a default
   * value from the field configuration instance is returned.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity, for example a node instance.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   * @param string $key
   *   The setting name, for example "status", "reminder date" etc.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getRegistrationSetting(EntityInterface $host_entity, RegistrationSettings $settings, string $key): mixed;

  /**
   * Gets the registration type for a host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return \Drupal\registration\Entity\RegistrationTypeInterface|null
   *   The registration type, if available.
   */
  public function getRegistrationType(EntityInterface $host_entity): ?RegistrationTypeInterface;

  /**
   * Gets the value of the registration field for a host entity.
   *
   * This is a Registration Type bundle machine name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return string|null
   *   The bundle, if available.
   */
  public function getRegistrationTypeBundle(EntityInterface $host_entity): ?string;

  /**
   * Gets a registration related route for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $route_id
   *   The route id: one of 'broadcast', 'manage', 'register' or 'settings'.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  public function getRoute(EntityTypeInterface $entity_type, string $route_id): ?Route;

  /**
   * Gets the registration settings entity for a given host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings|null
   *   The settings entity. A new entity is created and saved if needed.
   */
  public function getSettingsForHost(EntityInterface $host_entity): ?RegistrationSettings;

  /**
   * Determines if an entity type has a bundle with a registration field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if the entity type has a bundle with a registration field.
   */
  public function hasRegistrationField(EntityTypeInterface $entity_type): bool;

  /**
   * Determines if a host entity has spaces remaining.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity, for example a node instance.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   * @param int $spaces
   *   (optional) The number of spaces. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   *
   * @return bool
   *   TRUE if there are spaces remaining, FALSE otherwise.
   */
  public function hasRoom(EntityInterface $host_entity, RegistrationSettings $settings, int $spaces = 1, RegistrationInterface $registration = NULL): bool;

  /**
   * Determines whether new registrations are allowed for a host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity, for example a node instance.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   * @param int $spaces
   *   (optional) The number of spaces. Defaults to 1.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to exclude from the count.
   * @param array $errors
   *   (optional) If set, any error messages are set into this array.
   *
   * @return bool
   *   TRUE if new registrations are allowed, FALSE otherwise.
   */
  public function isEnabledForRegistration(EntityInterface $host_entity, RegistrationSettings $settings, int $spaces = 1, RegistrationInterface $registration = NULL, array &$errors = []): bool;

  /**
   * Determine whether an email address is registered for a given host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param string $email
   *   The email address to check.
   *
   * @return bool
   *   TRUE if the email address has already registered for the host entity.
   */
  public function isEmailRegistered(EntityInterface $host_entity, string $email): bool;

  /**
   * Determine whether a given user is registered for a given host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE if the user has already registered for the host entity.
   */
  public function isUserRegistered(EntityInterface $host_entity, AccountInterface $account): bool;

}
