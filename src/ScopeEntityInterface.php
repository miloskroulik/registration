<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines the interface for a scope that wraps an entity.
 *
 * This is a pseudo-entity wrapper around a real entity. It provides a
 * mechanism for extending the functionality of content entities without
 * having to override the content entity base class.
 */
interface ScopeEntityInterface extends ScopeInterface {

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
   * Gets the definition of the registration field.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition, if available.
   */
  public function getRegistrationField(): ?FieldDefinitionInterface;

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

}
