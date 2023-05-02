<?php

namespace Drupal\registration_waitlist\EventSubscriber;

use Drupal\registration\Event\RegistrationEvents;
use Drupal\registration\Event\RegistrationFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationFormEventSubscriber implements EventSubscriberInterface {

  /**
   * Alters a registration form.
   *
   * @param \Drupal\registration\Event\RegistrationFormEvent $event
   *   The registration form event.
   */
  public function alterRegisterForm(RegistrationFormEvent $event) {
    $form = $event->getForm();
    $form_state = $event->getFormState();

    if ($host_entity = $form_state->get('host_entity')) {
      if ($registration = $form_state->get('registration')) {
        $spaces = $registration->getSpacesReserved();
        if (!$host_entity->hasRoomOffWaitList($spaces, $registration)) {
          if ($host_entity->isWaitListEnabled()) {
            if ($host_entity->hasRoomOnWaitList($spaces, $registration)) {
              // Hide the Status field since the registration will be placed
              // in the wait list state on save.
              if (isset($form['state'])) {
                $form['state']['#access'] = FALSE;
              }

              // Add a message indicating the registration will be wait listed,
              // if a message was configured.
              if ($host_entity->getSetting('registration_waitlist_message_enable')) {
                if ($message = $host_entity->getSetting('registration_waitlist_message')) {
                  $form['message']['#weight'] = -10;
                  $form['message'][] = [
                    '#markup' => $message,
                  ];
                }
              }

              // Save the updated form to the event.
              $event->setForm($form);
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
    return [
      RegistrationEvents::REGISTRATION_ALTER_REGISTER_FORM => 'alterRegisterForm',
    ];
  }

}
