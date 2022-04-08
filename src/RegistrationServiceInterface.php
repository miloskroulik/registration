<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the interface for the registration service.
 */
interface RegistrationServiceInterface {

  /**
   * Gets the base route name for an entity type.
   *
   * This is typically a canonical route, or an edit-form route as a fallback.
   * For example, 'entity.node.canonical'.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string
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
   * Gets the route for the Manage Registration task for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   *
   * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
   */
  public function getManageRoute(EntityTypeInterface $entity_type): ?Route;

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
