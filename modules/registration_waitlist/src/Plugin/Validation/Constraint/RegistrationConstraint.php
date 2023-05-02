<?php

namespace Drupal\registration_waitlist\Plugin\Validation\Constraint;

use Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraint as BaseRegistrationConstraint;

/**
 * Extends the registration constraint..
 */
class RegistrationConstraint extends BaseRegistrationConstraint {

  /**
   * Would exceed wait list capacity.
   *
   * @var string
   */
  public string $noRoomOnWaitListMessage = "Sorry, unable to register for %label because the wait list is full.";

}
