<?php

namespace Drupal\registration\Mail;

use Drupal\Core\Entity\EntityInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;

/**
 * Defines the interface for the registration mailer service.
 */
interface RegistrationMailerInterface {

  /**
   * Gets the list of email addresses to send reminders and broadcast emails to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param array $data
   *   (optional)
   *   Contextual data with information about the usage of the list.
   *   This will be passed to events triggered by this method.
   *   For example, values submitted from the broadcast email form
   *   will be included here when that form is requesting the list.
   *
   *   To filter on certain states, pass an array of states, for example:
   *   $data['states'] = [
   *     'complete',
   *     'held',
   *   ]];
   *
   *   To indicate the list will be used in a test, pass the following:
   *   $data['test'] = TRUE;
   *   This will result in a single email address for the logged in user,
   *   referencing a synthetic (unsaved) registration generated for test use.
   *   To replace this test data with your own, use an event subscriber to
   *   modify the list.
   *
   *   Other data needed by your event subcribers can be set into this
   *   array, and it will be passed to your event handlers.
   *
   * @return array
   *   An associative array indexed by email address:
   *   [email_address => $registration_entity]
   *   If a user has registered for an event more than once, the registration
   *   entity will be replaced with an array of registration entities instead.
   *   The registration_entity can be NULL; this may occur if an event handler
   *   adds an email address to the list, but does not have a registration to
   *   include.  In this case the email will be sent to the specified recipient,
   *   but tokens related to registrations will be removed from the message
   *   instead of being replaced.
   */
  public function getEmailRecipientList(EntityInterface $host_entity, array $data = []): array;

  /**
   * Replaces tokens in a string and puts the result into a render element.
   *
   * Modifies the render element with bubbleable metadata and #markup set.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration entity.
   * @param string $input
   *   The input string with tokens.
   */
  public function replaceTokens(array &$element, EntityInterface $host_entity, RegistrationSettings $settings, RegistrationInterface $registration, string $input);

  /**
   * Sends email to registrations associated with a given host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param array $data
   *   (optional) Data as documented for function getEmailRecipientList().
   */
  public function sendMail(EntityInterface $host_entity, array $data = []);

}
