<?php

namespace Drupal\registration_waitlist;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Defines the class for the registration wait list manager service.
 */
class RegistrationWaitListManager implements RegistrationWaitListManagerInterface {

  use StringTranslationTrait;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Creates a RegistrationWaitListManager object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function autoFill(HostEntityInterface $host_entity) {
    $spaces_to_fill = $host_entity->getSpacesRemaining();
    if ($spaces_to_fill) {
      if ($new_state = $host_entity->getSetting('registration_waitlist_autofill_state')) {
        $count = 0;
        $wait_listed_registrations = $host_entity->getRegistrationList(['waitlist']);
        foreach ($wait_listed_registrations as $registration) {
          if ($host_entity->hasRoom($registration->getSpacesReserved())) {
            $registration->set('state', $new_state);
            $registration->save();
            $count++;
          }

          // Stop filling when there is no room left. This is checked even if
          // the registration was not updated, since other processes could add
          // to standard capacity while this loop is executing.
          if (!$host_entity->getSpacesRemaining()) {
            break;
          }
        }

        if ($count) {
          $this->logger->info($this->formatPlural($count, 'Automatically filled 1 registration from the wait list.', 'Automatically filled @count registrations from the wait list.'));
        }
      }
    }
  }

}
