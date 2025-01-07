<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostHasRoomConstraint constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostHasRoomConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostHasRoomConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if ($host_entity instanceof HostEntityInterface) {
      if (!$host_entity->hasRoom()) {
        $this->context
          ->buildViolation($constraint->noRoomMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->noRoomCode)
          ->setCause(t($constraint->noRoomCause))
          ->addViolation();
      }
    }
  }

}
