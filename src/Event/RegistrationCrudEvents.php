<?php

namespace Drupal\registration\Event;

final class RegistrationCrudEvents {

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
   * Fired before the registration is saved.
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

  /**
   * Name of the event fired after loadingregistration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_LOAD = 'registration.registration_settings.load';

  /**
   * Name of the event fired after creating new registration settings.
   *
   * Fired before the registration setting is saved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_CREATE = 'registration.registration_settings.create';

  /**
   * Name of the event fired before saving registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_PRESAVE = 'registration.registration_settings.presave';

  /**
   * Name of the event fired after saving new registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_INSERT = 'registration.registration_settings.insert';

  /**
   * Name of the event fired after saving existing registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_UPDATE = 'registration.registration_settings.update';

  /**
   * Name of the event fired before deleting egistration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_PREDELETE = 'registration.registration_settings.predelete';

  /**
   * Name of the event fired after deleting registration settings.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationSettingsEvent
   */
  const REGISTRATION_SETTINGS_DELETE = 'registration.registration_settings.delete';

}
