<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the interface for the registration manager service.
 */
interface RegistrationManagerInterface {

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
   * Gets the definition of the registration field for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition, if available.
   */
  public function getRegistrationField(EntityInterface $entity): ?FieldDefinitionInterface;

  /**
   * Gets the value of a registration setting for a host entity.
   *
   * If the host entity does not have registration settings yet, a default
   * value from the field configuration instance is returned.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity, for example a node instance.
   * @param \Drupal\Core\Entity\EntityInterface $registration_settings_entity
   *   The registration settings entity.
   * @param string $key
   *   The setting name, for example "status", "reminder date" etc.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getRegistrationSetting(EntityInterface $host_entity, EntityInterface $registration_settings_entity, string $key): mixed;

  /**
   * Gets a registration related route for an entity type and key.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $id
   *   The id, for example 'registrations' or 'register'.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  public function getRoute(EntityTypeInterface $entity_type, string $id): ?Route;

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

}
