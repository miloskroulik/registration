<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationSettingsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration settings event subscriber.
 */
class RegistrationSettingsEventSubscriber implements EventSubscriberInterface {

  /**
   * Processes load of a registration settings entity.
   *
   * @param \Drupal\registration\Event\RegistrationSettingsEvent $event
   *   The registration form event.
   */
  public function onSettingsLoad(RegistrationSettingsEvent $event) {
    $settings = $event->getSettings();
    // Ensure wait list capacity is initialized. Although the base field
    // definition has a default value of zero defined, if the module is
    // enabled with settings already stored in the database, the default
    // will never get loaded. Set the default here if needed, since the
    // field is conditionally required on the registration settings form,
    // and it will fail validation otherwise.
    // @see registration_waitlist_entity_base_field_info()
    if (is_null($settings->getSetting('registration_waitlist_capacity'))) {
      $settings->set('registration_waitlist_capacity', 0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_SETTINGS_LOAD => 'onSettingsLoad',
    ];
  }

}
