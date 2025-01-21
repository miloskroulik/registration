<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostIsEnabled constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostIsEnabledConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostIsEnabledConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if ($host_entity instanceof HostEntityInterface) {
      if (!$host_entity->isEnabled()) {
        $this->context
          ->buildViolation($constraint->disabledMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->disabledCode)
          ->setCause(t($constraint->disabledCause))
          ->addViolation();
      }
    }
  }

}
