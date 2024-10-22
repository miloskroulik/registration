<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration_waitlist\Event\RegistrationWaitListEvents;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the registration wait list manager service.
 */
class RegistrationWaitListManager implements RegistrationWaitListManagerInterface {

  use StringTranslationTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Creates a RegistrationWaitListManager object.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, LoggerInterface $logger) {
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function autoFill(HostEntityInterface $host_entity) {
    $spaces_to_fill = $host_entity->getSpacesRemaining();
    if ($spaces_to_fill && $host_entity->isConfiguredForRegistration() && $host_entity->isEnabledForRegistration()) {
      if ($new_state = $host_entity->getSetting('registration_waitlist_autofill_state')) {
        $count = 0;
        $spaces_filled = 0;
        $wait_listed_registrations = $host_entity->getRegistrationList(['waitlist']);
        foreach ($wait_listed_registrations as $registration) {
          if ($host_entity->hasRoomOffWaitList($registration->getSpacesReserved())) {
            $event = new RegistrationEvent($registration);
            $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_PREAUTOFILL);
            $registration->set('state', $new_state);
            $registration->save();
            $this->eventDispatcher->dispatch($event, RegistrationWaitListEvents::REGISTRATION_WAITLIST_AUTOFILL);
            $count++;
            $spaces_filled += $registration->getSpacesReserved();
          }

          // Stop filling when there is no room left. This is checked even if
          // the registration was not updated, since other processes could add
          // to standard capacity while this loop is executing.
          if (!$host_entity->getSpacesRemaining()) {
            break;
          }
        }

        if ($count) {
          if ($spaces_filled == 1) {
            $this->logger->info($this->formatPlural($count, 'Automatically filled 1 registration from the wait list.', 'Automatically filled @count registrations from the wait list.'));

          }
          else {
            $this->logger->info($this->formatPlural($count, 'Automatically filled 1 registration and @spaces_filled spaces from the wait list.', 'Automatically filled @count registrations and @spaces_filled spaces from the wait list.', [
              '@spaces_filled' => $spaces_filled,
            ]));
          }
        }
      }
    }
  }

}
