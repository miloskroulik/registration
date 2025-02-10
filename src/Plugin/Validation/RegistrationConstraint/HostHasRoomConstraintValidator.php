<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\registration\HostEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostHasRoomConstraint constraint.
 *
 * This validator adds a cache dependency on the list of registrations,
 * and should be avoided when used as part of access control. Otherwise
 * the access control will need to recalculate too often, making the
 * caching less effective.
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
      // Recheck when registrations are added and deleted.
      // @todo Make this more granular, ideally a list tag per host entity.
      $this->context->getCacheableMetadata()->addCacheTags(['registration_list']);

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
