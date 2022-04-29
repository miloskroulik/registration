<?php

namespace Drupal\registration\Event;

final class RegistrationAlterEvents {

  /**
   * Alter the registration count for a host entity.
   *
   * This is the number of registrations, not spaces reserved.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_COUNT = 'registration.alter.count';

  /**
   * Alter whether registration is enabled for a host entity.
   *
   * The standard check looks at the status flag, open and close
   * dates, and whether there is still room for new registrations
   * based on the capacity setting.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_ENABLED = 'registration.alter.enabled';

  /**
   * Alter email parameters before an email is sent to a registrant.
   *
   * The data altered is an array of message parameters.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   * @see \Drupal\registration\Mail\RegistrationMailer
   */
  const REGISTRATION_ALTER_MAIL = 'registration.alter.mail';

  /**
   * Alter the list of email recipients before emails are sent.
   *
   * The recipient list is an associative array indexed by email address.
   * See the mailer interface file for a description of this structure.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   * @see \Drupal\registration\Mail\RegistrationMailerInterface
   */
  const REGISTRATION_ALTER_RECIPIENTS = 'registration.alter.recipients';

  /**
   * Alter the registration usage (spaces reserved) for a host entity.
   *
   * @Event
   *
   * @see \Drupal\registration\Event\RegistrationDataAlterEvent
   */
  const REGISTRATION_ALTER_USAGE = 'registration.alter.usage';

}
