<?php

namespace Drupal\registration\Event;

final class RegistrationEvents {

  /**
   * Name of the event fired after loading a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_LOAD = 'registration.registration.load';

  /**
   * Name of the event fired after creating a new registration.
   *
   * Fired before the product is saved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_CREATE = 'registration.registration.create';

  /**
   * Name of the event fired before saving a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_PRESAVE = 'registration.registration.presave';

  /**
   * Name of the event fired after saving a new registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_INSERT = 'registration.registration.insert';

  /**
   * Name of the event fired after saving an existing registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_UPDATE = 'registration.registration.update';

  /**
   * Name of the event fired before deleting a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_PREDELETE = 'registration.registration.predelete';

  /**
   * Name of the event fired after deleting a registration.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationEvent
   */
  const REGISTRATION_DELETE = 'registration.registration.delete';

}
