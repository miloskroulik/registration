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
      $settings = $host_entity->getSettings();

      $enabled = (bool) $settings->getSetting('status');
      if (!$enabled) {
        // The host entity is not enabled. Only add a violation if the host
        // is not already disabled for registration by the open or close date,
        // as this would result in redundant messaging.
        $result = $this->context->getResult();
        if (!$result->hasViolationWithCode('open') && !$result->hasViolationWithCode('close')) {
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

}
