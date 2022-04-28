<?php

namespace Drupal\registration\Event;

final class RegistrationAlterEvents {

  /**
   * Alter the registration count for a host entity
   *
   * This is the number of registrations, not spaces reserved..
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_COUNT = 'registration.alter.count';

  /**
   * Alter the registration usage (spaces reserved) for a host entity.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_USAGE = 'registration.alter.usage';

  /**
   * Alter specific settings such as the status (enabled) setting.
   *
   * These are listed in the order they appear on the Settings form.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   * @see \Drupal\registration\Form\RegistrationSettingsForm
   */
  const REGISTRATION_ALTER_SETTING_STATUS        = 'registration.alter.setting.status';
  const REGISTRATION_ALTER_SETTING_CAPACITY      = 'registration.alter.setting.capacity';
  const REGISTRATION_ALTER_SETTING_OPEN          = 'registration.alter.setting.open';
  const REGISTRATION_ALTER_SETTING_CLOSE         = 'registration.alter.setting.close';
  const REGISTRATION_ALTER_SETTING_SEND_REMINDER = 'registration.alter.setting.send_reminder';
  const REGISTRATION_ALTER_SETTING_REMINDER_DATE = 'registration.alter.setting.reminder_date';
  const REGISTRATION_ALTER_SETTING_TEMPLATE      = 'registration.alter.setting.reminder_template';
  const REGISTRATION_ALTER_SETTING_MAX_SPACES    = 'registration.alter.setting.maximum_spaces';
  const REGISTRATION_ALTER_SETTING_MULTIPLE_REGS = 'registration.alter.setting.multiple_registrations';
  const REGISTRATION_ALTER_SETTING_FROM_ADDRESS  = 'registration.alter.setting.from_address';
  const REGISTRATION_ALTER_SETTING_CONFIRMATION  = 'registration.alter.setting.confirmation';
  const REGISTRATION_ALTER_SETTING_REDIRECT      = 'registration.alter.setting.confirmation_redirect';

}
