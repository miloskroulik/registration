<?php

namespace Drupal\registration\Mail;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Event\RegistrationAlterEvents;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines the class for the registration mailer service.
 */
class RegistrationMailer implements RegistrationMailerInterface {

  use StringTranslationTrait;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected ContainerAwareEventDispatcher $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Creates a RegistrationMailer object.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(AccountProxy $current_user, ContainerAwareEventDispatcher $event_dispatcher, LoggerInterface $logger, MailManagerInterface $mail_manager, RegistrationManagerInterface $registration_manager, Renderer $renderer) {
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
    $this->logger = $logger;
    $this->mailManager = $mail_manager;
    $this->registrationManager = $registration_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailRecipientList(EntityInterface $host_entity, array $data = []): array {
    if (!empty($data['test'])) {
      $registrations = [$this->registrationManager->generateSampleRegistration($host_entity)];
    }
    elseif (!empty($data['states'])) {
      $registrations = $this->registrationManager->getRegistrationList($host_entity, $data['states']);
    }
    else {
      $registrations = $this->registrationManager->getRegistrationList($host_entity);
    }

    // The list is built as an associative array, indexed by email address.
    // The value is an array for emails with multiple registrations, or
    // a RegistrationInterface entity if the email has a single registration.
    $recipients = [];
    foreach ($registrations as $registration) {
      $email = $registration->getEmail();
      if (isset($recipients[$email])) {
        if (is_array($recipients[$email])) {
          // Already multiple, append to the list.
          $recipients[$email][] = $registration;
        }
        else {
          // Convert to multiple.
          $previous_registration = $recipients[$email];
          $recipients[$email] = [];
          $recipients[$email][] = $previous_registration;
          $recipients[$email][] = $registration;
        }
      }
      else {
        // Single registration.
        $recipients[$email] = $registration;
      }
    }

    // Allow other modules to alter the recipient list.
    $event = new RegistrationDataAlterEvent($recipients, [
      'host_entity' => $host_entity,
      'settings' => $this->registrationManager->getSettingsForHost($host_entity),
    ]);
    $this->eventDispatcher->dispatch($event, RegistrationAlterEvents::REGISTRATION_ALTER_RECIPIENTS);
    return $event->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function sendMail(EntityInterface $host_entity, array $data = []): int {
    $success_count = 0;
    $settings = $this->registrationManager->getSettingsForHost($host_entity);
    $langcode = $this->currentUser->getPreferredLangcode(TRUE);
    $send = TRUE;

    // Build parameters. These are common to every email sent.
    $params = [];
    $params['subject'] = $data['subject'];
    $params['from'] = $this->registrationManager->getRegistrationSetting($host_entity, $settings, 'from_address');
    $build = [
      '#type' => 'processed_text',
      '#text' => $data['message']['value'],
      '#format' => $data['message']['format'],
    ];
    $params['message'] = $this->renderer->render($build);
    $params['token_entities'] = [
      $host_entity->getEntityTypeId() => $host_entity,
      'registration_settings' => $settings,
    ];

    // Get the recipients and send to each.
    $recipients =  $this->getEmailRecipientList($host_entity, $data);
    foreach ($recipients as $email => $registrations) {
      // Convert singleton to array.
      if (!is_array($registrations)) {
        $registrations = [$registrations];
      }
      // @todo Send via a queue worker if there are very many.
      foreach ($registrations as $registration) {
        // Set registration entity for token replacement if available.
        if ($registration instanceof RegistrationInterface) {
          $params['token_entities']['registration'] = $registration;
        }
        else {
          // Clear what is there from the previous loop iteration.
          unset($params['token_entities']['registration']);
        }

        // Allow other modules to alter the parameters.
        // Token replacement should not be done in the event subscriber
        // because the registration_mail function already handles this.
        $event = new RegistrationDataAlterEvent($params, [
          'host_entity' => $host_entity,
          'settings' => $settings,
          'registration' => $registration,
        ]);
        $this->eventDispatcher->dispatch($event, RegistrationAlterEvents::REGISTRATION_ALTER_MAIL);
        $params = $event->getData();

        // Send the mail and count successes.
        $result = $this->mailManager->mail('registration', 'broadcast', $email, $langcode, $params, NULL, $send);
        if ($result['result'] !== FALSE) {
          $success_count++;
        }
        else {
          $this->logger->error('Failed to send registration broadcast email to %email.', [
            '%email' => $email,
          ]);
        }
      }
    }
    if ($success_count) {
      $this->logger->info('Registration broadcast sent to @count recipients.', [
        '@count' => $success_count,
      ]);
    }
    return $success_count;
  }

}
