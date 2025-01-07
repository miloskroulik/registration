<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationIsEditable constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class RegistrationIsEditableConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new RegistrationIsEditableConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   */
  public function __construct(AccountProxy $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationIsEditableConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var RegistrationIsEditableConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      $host_entity = $registration?->getHostEntity();
      $settings = $host_entity?->getSettings();

      // These checks only apply to existing registrations for a regular user.
      if (!$registration->isNew() && $settings) {
        // Check for administrator access.
        $access_result = $registration->access('administer', $constraint->account ?? $this->currentUser, TRUE);
        $admin = $access_result->isAllowed();

        // Add the access result to cacheability.
        $this->context->getCacheableMetadata()->addCacheableDependency($access_result);

        if (!$admin) {
          // Check the main status setting.
          $enabled = (bool) $settings->getSetting('status');
          if (!$enabled) {
            $this->context
              ->buildViolation($constraint->disabledMessage, [
                '%label' => $host_entity->label(),
              ])
              ->setCode($constraint->disabledCode)
              ->setCause(t($constraint->disabledCause))
              ->addViolation();
            return;
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
    }
  }

}
