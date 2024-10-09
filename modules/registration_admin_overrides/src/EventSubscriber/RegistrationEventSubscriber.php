<?php

namespace Drupal\registration_admin_overrides\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The registration override checker.
   *
   * @var \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface
   */
  protected RegistrationOverrideCheckerInterface $overrideChecker;

  /**
   * Constructs a new RegistrationEventSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface $override_checker
   *   The override checker.
   */
  public function __construct(AccountProxy $current_user, ModuleHandlerInterface $module_handler, RegistrationOverrideCheckerInterface $override_checker) {
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->overrideChecker = $override_checker;
  }

  /**
   * Alters whether a host entity is enabled for registration.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterEnabled(RegistrationDataAlterEvent $event) {
    $enabled = (bool) $event->getData();
    if (!$enabled) {
      $enabled = TRUE;

      // Re-check all the error conditions against the configured overrides.
      // Error messages are added and removed from the error array based on
      // the new checks.
      $context = $event->getContext();

      $host_entity = $context['host_entity'];
      $settings = $context['settings'];
      $spaces = $context['spaces'];
      $registration = $context['registration'];
      $errors = $context['errors'];

      // Check main status.
      $status = (bool) $settings->getSetting('status');
      if (!$status) {
        if ($this->canOverride($context, 'status')) {
          unset($errors['status']);
        }
        else {
          $enabled = FALSE;
        }
      }

      // Check maximum spaces.
      $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
      if ($maximum_spaces && ($spaces > $maximum_spaces)) {
        if ($this->canOverride($context, 'maximum_spaces')) {
          unset($errors['maximum_spaces']);
        }
        else {
          $enabled = FALSE;
          $errors['maximum_spaces'] = $this->formatPlural($maximum_spaces,
            'You may not register for more than 1 space.',
            'You may not register for more than @count spaces.', [
              '@count' => $maximum_spaces,
            ]);
        }
      }

      // Check capacity.
      if (!$host_entity->hasRoom($spaces, $registration)) {
        if ($this->canOverride($context, 'capacity')) {
          unset($errors['capacity']);
        }
        else {
          $enabled = FALSE;
          $errors['capacity'] = $this->t('Sorry, unable to register for %label due to: insufficient spaces remaining.', [
            '%label' => $host_entity->label(),
          ]);
        }
      }

      // Check wait list capacity.
      if ($this->moduleHandler->moduleExists('registration_waitlist')) {
        if (isset($errors['waitlist_capacity'])) {
          if ($this->canOverride($context, 'capacity')) {
            unset($errors['waitlist_capacity']);
          }
        }
      }

      // Check open date.
      if ($host_entity->isBeforeOpen()) {
        if ($this->canOverride($context, 'open')) {
          unset($errors['open']);
        }
        else {
          $enabled = FALSE;
          $errors['open'] = $this->t('Registration for %label is not open yet.', [
            '%label' => $host_entity->label(),
          ]);
        }
      }

      // Check close date.
      if ($host_entity->isAfterClose()) {
        if ($this->canOverride($context, 'close')) {
          unset($errors['close']);
        }
        else {
          $enabled = FALSE;
          $errors['close'] = $this->t('Registration for %label is closed.', [
            '%label' => $host_entity->label(),
          ]);
        }
      }

      // Respect other modules that may have added their own errors.
      if (!empty($errors)) {
        $enabled = FALSE;
      }

      // Update the event.
      $event
        ->setData($enabled)
        ->setErrors($errors);
    }
  }

  /**
   * Alters the state set by a registration wait list presave.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterWaitListState(RegistrationDataAlterEvent $event) {
    $state = (string) $event->getData();
    $context = $event->getContext();

    // If an existing registration is about to be wait listed, see if an
    // override can place the registration in the originally desired active
    // state instead.
    if ($state == 'waitlist') {
      $registration = $context['registration'];
      if (!$registration->isNew() && $registration->getState()->isActive()) {
        if ($this->canOverride($context, 'capacity')) {
          $event->setData($registration->getState()->id());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_ALTER_ENABLED => 'alterEnabled',
      'registration_waitlist.registration.presave' => 'alterWaitListState',
    ];
  }

  /**
   * Determines if the current user can override a given registration setting.
   *
   * @param array $context
   *   The event context.
   * @param string $setting
   *   The name of the setting.
   *
   * @return bool
   *   TRUE if the user can override the setting, FALSE otherwise.
   */
  protected function canOverride(array $context, string $setting): bool {
    return $this->overrideChecker->accountCanOverride($context['host_entity'], $this->currentUser, $setting, $context['registration'] ?? NULL);
  }

}
