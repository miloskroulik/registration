<?php

namespace Drupal\registration\Plugin\WorkflowType;

/**
 * Defines the interface for the registration workflow type.
 */
interface RegistrationInterface {

  /**
   * Gets the canceled state for the workflow.
   *
   * @return string|null
   *   The canceled state, if available.
   */
  public function getCanceledState(): ?string;

  /**
   * Determines if the workflow has a canceled state.
   *
   * @return bool
   *   TRUE if the workflow has a canceled state, FALSE otherwise.
   */
  public function hasCanceledState(): bool;

}
