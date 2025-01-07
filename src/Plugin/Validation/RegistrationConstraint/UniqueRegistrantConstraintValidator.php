<?php

namespace Drupal\registration\Plugin\Validation\RegistrationConstraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueRegistrant constraint.
 *
 * @phpcs:disable Drupal.Semantics.FunctionT.NotLiteralString
 */
class UniqueRegistrantConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user service.
   */
  protected AccountProxy $currentUser;

  /**
   * Constructs a new UniqueRegistrantConstraintValidator.
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
  public static function create(ContainerInterface $container): UniqueRegistrantConstraintValidator {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    /** @var UniqueRegistrantConstraint $constraint */
    if ($registration instanceof RegistrationInterface) {
      $host_entity = $registration->getHostEntity();
      if ($settings = $host_entity?->getSettings()) {
        $allow_multiple = $settings->getSetting('multiple_registrations');
        if (!$allow_multiple) {

          // Multiple registrations per person are not allowed.
          if ($registration->isNew()) {

            // Check the email address when registering an anonymous user.
            if ($email = $registration->getAnonymousEmail()) {
              if ($host_entity->isRegistrant(NULL, $email)) {
                $this->context
                  ->buildViolation($constraint->emailAlreadyRegisteredMessage, [
                    '%mail' => $email,
                  ])
                  ->atPath('anon_mail')
                  ->setCode($constraint->emailAlreadyRegisteredCode)
                  ->setCause(t($constraint->emailAlreadyRegisteredCause))
                  ->addViolation();
              }
            }

            // Check the user account.
            elseif ($user = $registration->getUser()) {
              if ($host_entity->isRegistrant($user)) {
                // Add a violation. The choice of message depends on the
                // current user, so add a cache context based on the user.
                $this->context->getCacheableMetadata()->addCacheContexts(['user']);

                if ($user->id() == $this->currentUser->id()) {
                  $this->context
                    ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                    ->setCode($constraint->youAreAlreadyRegisteredCode)
                    ->setCause(t($constraint->youAreAlreadyRegisteredCause))
                    ->addViolation();
                }
                else {
                  $this->context
                    ->buildViolation($constraint->userAlreadyRegisteredMessage, [
                      '%user' => $user->getDisplayName(),
                    ])
                    ->atPath('user_uid')
                    ->setCode($constraint->userAlreadyRegisteredCode)
                    ->setCause(t($constraint->userAlreadyRegisteredCause))
                    ->addViolation();
                }
              }
            }

            // The user logged in is registering.
            else {
              // Whether a violation is added or not depends on the logged in
              // user, so add a cache context based on the user.
              $this->context->getCacheableMetadata()->addCacheContexts(['user']);

              if ($host_entity->isRegistrant($this->currentUser)) {
                $this->context
                  ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                  ->setCode($constraint->youAreAlreadyRegisteredCode)
                  ->setCause(t($constraint->youAreAlreadyRegisteredCause))
                  ->addViolation();
              }
            }
          }
          else {
            /** @var \Drupal\registration\Entity\RegistrationInterface $original */
            $original = $this->context->getOriginal();

            // Check email address.
            if ($registration->getAnonymousEmail() != $original->getAnonymousEmail()) {
              if ($email = $registration->getAnonymousEmail()) {
                if ($host_entity->isRegistrant(NULL, $email)) {
                  $this->context
                    ->buildViolation($constraint->emailAlreadyRegisteredMessage, [
                      '%mail' => $email,
                    ])
                    ->atPath('anon_mail')
                    ->setCode($constraint->emailAlreadyRegisteredCode)
                    ->setCause(($constraint->emailAlreadyRegisteredCause))
                    ->addViolation();
                }
              }
            }

            // Check the user account.
            if ($registration->getUserId() != $original->getUserId()) {
              if ($user = $registration->getUser()) {
                if ($host_entity->isRegistrant($user)) {
                  // Add a violation. The choice of message depends on the
                  // current user, so add a cache context based on the user.
                  $this->context->getCacheableMetadata()->addCacheContexts(['user']);

                  if ($user->id() == $this->currentUser->id()) {
                    $this->context
                      ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                      ->setCode($constraint->youAreAlreadyRegisteredCode)
                      ->setCause(t($constraint->youAreAlreadyRegisteredCause))
                      ->addViolation();
                  }
                  else {
                    $this->context
                      ->buildViolation($constraint->userAlreadyRegisteredMessage, [
                        '%user' => $user->getDisplayName(),
                      ])
                      ->atPath('user_uid')
                      ->setCode($constraint->userAlreadyRegisteredCode)
                      ->setCause(t($constraint->userAlreadyRegisteredCause))
                      ->addViolation();
                  }
                }
              }
            }
          }
        }
      }
    }
  }

}
