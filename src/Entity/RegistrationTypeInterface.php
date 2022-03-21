<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the interface for registration types.
 */
interface RegistrationTypeInterface extends ConfigEntityInterface {

  /**
   * Gets the workflow ID for the registration type.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId(): string;

  /**
   * Gets the workflow entity for the registration type.
   *
   * @return \Drupal\workflows\WorkflowInterface|null
   *   The workflow entity, or NULL if none exists yet.
   */
  public function getWorkflow(): WorkflowInterface|null;

  /**
   * Sets the workflow ID for the registration type.
   *
   * @param string $workflow_id
   *   The workflow ID.
   */
  public function setWorkflowId(string $workflow_id);

  /**
   * Gets the default state for the registration type.
   *
   * @return string
   *   The default state.
   */
  public function getDefaultState(): string;

  /**
   * Sets the default state for the registration type.
   *
   * @param string $state
   *   The state.
   *
   * @return $this
   */
  public function setDefaultState(string $state): RegistrationTypeInterface;

  /**
   * Gets the amount of time before holds on registrations of the type expire.
   *
   * @return int
   *   The time in hours.
   */
  public function getHeldExpirationTime(): int;

  /**
   * Sets the amount of time before holds on registrations of the type expire.
   *
   * @param int $time
   *   The time in hours.
   *
   * @return $this
   */
  public function setHeldExpirationTime(int $time): RegistrationTypeInterface;

  /**
   * Gets the new state when holds on registrations of the type expire.
   *
   * @return string
   *   The new state.
   */
  public function getHeldExpirationState(): string;

  /**
   * Sets the new state when holds on registrations of the type expire.
   *
   * @param string $state
   *   The new state.
   *
   * @return $this
   */
  public function setHeldExpirationState(string $state): RegistrationTypeInterface;

}
