<?php

namespace Drupal\registration_admin_overrides\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\HostEntityInterface;
use Drupal\registration_admin_overrides\RegistrationOverrideCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration validation event subscriber.
 */
class RegistrationValidationEventSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   */
  protected AccountProxy $currentUser;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The registration override checker.
   */
  protected RegistrationOverrideCheckerInterface $overrideChecker;

  /**
   * Constructs a new RegistrationValidationEventSubscriber.
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
   * Alters a validation result.
   *
   * @param \Drupal\registration\Event\RegistrationDataAlterEvent $event
   *   The registration data alter event.
   */
  public function alterValidationResult(RegistrationDataAlterEvent $event): void {
    $validation_result = $event->getData();
    $context = $event->getContext();

    $pipeline_id = $context['pipeline_id'];
    $host_entity = $context['host_entity'];
    $registration = $context['registration'];

    // Remove violations if the current user can override them.
    if ($host_entity instanceof HostEntityInterface) {
      if ($settings = $host_entity->getSettings()) {
        if ($registration_type = $host_entity->getRegistrationType()) {
          $validation_result->getCacheableMetadata()
            // The override checker inspects the host entity settings.
            ->addCacheableDependency($settings)
            // The override checker inspects the registration type.
            ->addCacheableDependency($registration_type)
            // The override checker inspects user permissions.
            ->addCacheContexts(['user.permissions']);

          // Check each setting that can be overridden.
          $settings = [
            'status',
            'maximum_spaces',
            'capacity',
            'open',
            'close',
          ];
          foreach ($settings as $setting) {
            if ($this->canOverride($host_entity, $registration, $setting)) {
              $validation_result->removeViolationWithCode($setting);

              // Special handling for wait list capacity.
              if ($setting == 'capacity') {
                if ($this->moduleHandler->moduleExists('registration_waitlist')) {
                  $validation_result->removeViolationWithCode('waitlist_capacity');
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run this subscriber after other validation result subscribers.
    return [
      RegistrationEvents::REGISTRATION_ALTER_VALIDATION_RESULT => ['alterValidationResult', -100],
    ];
  }

  /**
   * Determines if the current user can override a given registration setting.
   *
   * @param \Drupal\registration\HostEntityInterface|null $host_entity
   *   The host entity, if available.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   The registration, if available.
   * @param string $setting
   *   The name of the setting.
   *
   * @return bool
   *   TRUE if the user can override the setting, FALSE otherwise.
   */
  protected function canOverride(?HostEntityInterface $host_entity, ?RegistrationInterface $registration, string $setting): bool {
    return $this->overrideChecker->accountCanOverride($host_entity, $this->currentUser, $setting, $registration);
  }

}
