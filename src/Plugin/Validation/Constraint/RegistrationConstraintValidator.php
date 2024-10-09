<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the RegistrationConstraint constraint.
 */
class RegistrationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RegistrationConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationConstraintValidator {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($registration, Constraint $constraint) {
    if ($registration instanceof RegistrationInterface) {
      // Ensure there is a host entity configured for registration.
      $host_entity = $registration->getHostEntity();
      if (!$host_entity) {
        $this->context
          ->buildViolation($constraint->noHostEntityMessage)
          ->addViolation();
        return;
      }

      if (!$host_entity->isConfiguredForRegistration()) {
        $this->context
          ->buildViolation($constraint->disabledMessage, [
            '%label' => $host_entity->label(),
          ])
          ->addViolation();
        return;
      }

      $settings = $host_entity->getSettings();

      // Skip certain checks if the host entity is considered enabled for
      // registration. This allows event subscribers to override constraint
      // checking. If the host entity is not enabled, then do the checks so
      // a specific error message can be given.
      $spaces = $registration->getSpacesReserved();
      $errors = [];
      $host_enabled = $host_entity->isEnabledForRegistration($spaces, $registration, $errors);

      if (!$host_enabled) {
        // Check the main status setting for new registrations. Allow an
        // administrator to edit registrations even when the main setting is
        // disabled.
        $admin = $registration->access('administer', $this->currentUser);

        if ($registration->isNew() || !$admin) {
          $enabled = (bool) $settings->getSetting('status');
          if (!$enabled && isset($errors['status'])) {
            $this->context
              ->buildViolation($constraint->disabledMessage, [
                '%label' => $host_entity->label(),
              ])
              ->addViolation();
            return;
          }
        }

        // Check maximum allowed spaces per registration.
        $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
        if ($maximum_spaces && ($spaces > $maximum_spaces) && isset($errors['maximum_spaces'])) {
          $this->context
            ->buildViolation($constraint->tooManySpacesMessage)
            ->setParameter('@count', $maximum_spaces)
            ->setPlural($maximum_spaces)
            ->atPath('count')
            ->addViolation();
        }

        // Check against capacity unless the registration is canceled.
        if (!$registration->isCanceled() && !$host_entity->hasRoom($spaces, $registration)) {
          if (isset($errors['capacity'])) {
            if ($spaces > 1) {
              $this->context
                ->buildViolation($constraint->noRoomMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->atPath('count')
                ->addViolation();
            }
            else {
              $this->context
                ->buildViolation($constraint->noRoomMessage, [
                  '%label' => $host_entity->label(),
                ])
                ->addViolation();
            }
          }
        }

        // Check against open and close dates for new registrations.
        if ($registration->isNew()) {
          $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
          $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);

          $registration_date = new DrupalDateTime('now', $storage_timezone);

          // Check open date.
          $open = $settings->getSetting('open');
          if ($open) {
            $open = DrupalDateTime::createFromFormat($storage_format, $open, $storage_timezone);
          }
          if ($open && ($registration_date < $open) && isset($errors['open'])) {
            $this->context
              ->buildViolation($constraint->notOpenYetMessage, [
                '%label' => $host_entity->label(),
              ])
              ->addViolation();
          }

          // Check close date.
          $close = $settings->getSetting('close');
          if ($close) {
            $close = DrupalDateTime::createFromFormat($storage_format, $close, $storage_timezone);
          }
          if ($close && ($registration_date >= $close) && isset($errors['close'])) {
            $this->context
              ->buildViolation($constraint->closedMessage, [
                '%label' => $host_entity->label(),
              ])
              ->addViolation();
          }
        }

        // If there were no violations so far, then the host entity may have
        // been disabled for registration by an event subscriber. Add a generic
        // violation to cover this case. However allow administrators to edit
        // existing registrations.
        if ($registration->isNew() || !$admin) {
          if (empty(count($this->context->getViolations()))) {
            $this->context
              ->buildViolation($constraint->disabledMessage, [
                '%label' => $host_entity->label(),
              ])
              ->addViolation();
            return;
          }
        }
      }

      // Check if already registered when multiple registrations per person are
      // not allowed.
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
                ->addViolation();
            }
          }

          // Check the user account.
          elseif ($user = $registration->getUser()) {
            if ($host_entity->isRegistrant($user)) {
              if ($user->id() == $this->currentUser->id()) {
                $this->context
                  ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                  ->addViolation();
              }
              else {
                $this->context
                  ->buildViolation($constraint->userAlreadyRegisteredMessage, [
                    '%user' => $user->getDisplayName(),
                  ])
                  ->atPath('user_uid')
                  ->addViolation();
              }
            }
          }

          // The user logged in is registering.
          else {
            if ($host_entity->isRegistrant($this->currentUser)) {
              $this->context
                ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                ->addViolation();
            }
          }
        }
        else {
          // For an existing registration, compare the updated values against
          // the original registration saved in the database.
          $storage = $this->entityTypeManager->getStorage('registration');
          $original = $storage->loadUnchanged($registration->id());

          // Check email address.
          if ($registration->getAnonymousEmail() != $original->getAnonymousEmail()) {
            if ($email = $registration->getAnonymousEmail()) {
              if ($host_entity->isRegistrant(NULL, $email)) {
                $this->context
                  ->buildViolation($constraint->emailAlreadyRegisteredMessage, [
                    '%mail' => $email,
                  ])
                  ->atPath('anon_mail')
                  ->addViolation();
              }
            }
          }

          // Check the user account.
          if ($registration->getUserId() != $original->getUserId()) {
            if ($user = $registration->getUser()) {
              if ($host_entity->isRegistrant($user)) {
                if ($user->id() == $this->currentUser->id()) {
                  $this->context
                    ->buildViolation($constraint->youAreAlreadyRegisteredMessage)
                    ->addViolation();
                }
                else {
                  $this->context
                    ->buildViolation($constraint->userAlreadyRegisteredMessage, [
                      '%user' => $user->getDisplayName(),
                    ])
                    ->atPath('user_uid')
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
