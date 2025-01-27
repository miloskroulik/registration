<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\registration\HostEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the HostIsOpen constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class HostIsOpenConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * Constructs a new HostIsOpenConstraintValidator.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): HostIsOpenConstraintValidator {
    return new static(
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var HostIsOpenConstraint $constraint */
    $host_entity = $constraint->hostEntity ?? $value;

    if ($host_entity instanceof HostEntityInterface) {
      // Set a cache expiration if applicable.
      if ($max_age = $this->calculateMaxAge($host_entity)) {
        $this->context->getCacheableMetadata()->setCacheMaxAge($max_age);
      }

      // Check open date.
      if ($host_entity->isBeforeOpen()) {
        $this->context
          ->buildViolation($constraint->notOpenYetMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->notOpenYetCode)
          ->setCause(t($constraint->notOpenYetCause))
          ->addViolation();
      }

      // Check close date.
      if ($host_entity->isAfterClose()) {
        $this->context
          ->buildViolation($constraint->closedMessage, [
            '%label' => $host_entity->label(),
          ])
          ->setCode($constraint->closedCode)
          ->setCause(t($constraint->closedCause))
          ->addViolation();
      }
    }
  }

  /**
   * Calculates a max-age based on the host entity open or close dates.
   *
   * If registration for the host entity has closed, or the host entity does
   * not have open or close dates, then NULL is returned.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   *
   * @return int|null
   *   The calculated max age, if available.
   */
  protected function calculateMaxAge(HostEntityInterface $host_entity): ?int {
    $expiration = NULL;

    // Expire this validation result on the open date if one exists and it's in
    // the future.
    if ($host_entity->isBeforeOpen()) {
      $expiration = $host_entity->getOpenDate()->getTimestamp();
    }

    // Expire this validation result on the close date if one exists and it's in
    // the future.
    elseif (($close = $host_entity->getCloseDate()) && !$host_entity->isAfterClose()) {
      $expiration = $close->getTimestamp();
    }

    // If an open or close date in the future was found, calculate the amount
    // of time before the relevant date, and use that as the max age.
    if ($expiration) {
      return $expiration - $this->time->getCurrentTime();
    }

    return NULL;
  }

}
