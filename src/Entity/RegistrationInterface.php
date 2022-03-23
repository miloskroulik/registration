<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the interface for registrations.
 */
interface RegistrationInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the display name for the creator of the registration.
   *
   * @return string|null
   *   The author name or NULL for a new registration.
   */
  public function getAuthorDisplayName(): string|null;

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

}
