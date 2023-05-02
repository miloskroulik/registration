<?php

namespace Drupal\registration_waitlist\Plugin\Validation\Constraint;

use Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraintValidator as BaseRegistrationConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Extends validation for the RegistrationConstraint constraint.
 *
 * Provides a wait-list specific error message when validation does not pass
 * because the wait list capacity has been reached.
 */
class RegistrationConstraintValidator extends BaseRegistrationConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    parent::validate($registration, $constraint);
    $violations = $this->context->getViolations();
    foreach ($violations as $offset => $violation) {
      // The standard validator does not have awareness of the wait list,
      // and if the wait list capacity is reached, it outputs a generic error
      // message. Replace that generic message with a specific message in this
      // case.
      if ($violation->getMessageTemplate() == $constraint->disabledMessage) {
        /** @var \Drupal\registration_waitlist\HostEntityInterface $host_entity */
        $host_entity = $registration->getHostEntity();
        $spaces = $registration->getSpacesReserved();
        if ($host_entity->isWaitListEnabled() && !$host_entity->hasRoomOffWaitList()) {
          if (!$host_entity->hasRoomOnWaitList($spaces, $registration)) {
            // Wait list is enabled, there is no room in standard capacity, and
            // no room on the wait list. Replace the generic message.
            $violations->remove($offset);
            if ($spaces > 1) {
              $this->context
                ->buildViolation($constraint->noRoomOnWaitListMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('count')
                ->addViolation();
            }
            else {
              $this->context
                ->buildViolation($constraint->noRoomOnWaitListMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->addViolation();
            }
          }
        }
      }
    }
  }

}
