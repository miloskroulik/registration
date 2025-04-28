<?php

namespace Drupal\registration_confirmation\EventSubscriber;

use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\registration\Event\RegistrationEvent;
use Drupal\registration\Event\RegistrationEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a registration event subscriber.
 */
class RegistrationEventSubscriber implements EventSubscriberInterface {

  /**
   * The action plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new RegistrationEventSubscriber.
   *
   * @param \Drupal\Core\Action\ActionManager $action_manager
   *   The action manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ActionManager $action_manager, LoggerInterface $logger) {
    $this->actionManager = $action_manager;
    $this->logger = $logger;
  }

  /**
   * Process update.
   *
   * @param \Drupal\registration\Event\RegistrationEvent $event
   *   The registration event.
   */
  public function onUpdate(RegistrationEvent $event) {
    $registration = $event->getRegistration();
    $from_state = isset($registration->original) ? $registration->original->getState()->id() : NULL;
    $to_state = $registration->getState()->id();
    // Send a confirmation email for newly completed registrations if this is
    // enabled for the registration type.
    if (($from_state !== $to_state) && $registration->isComplete()) {
      $registration_type = $registration->getType();
      $confirmation_types = ['registrant', 'admin'];
      foreach ($confirmation_types as $confirmation_type) {
        $this->executeMailAction($registration_type, $registration, $confirmation_type);
      }
    }
  }

  /**
   * @param \Drupal\registration\Entity\RegistrationTypeInterface $registration_type
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function executeMailAction(RegistrationTypeInterface $registration_type, RegistrationInterface $registration, $confirmation_type): void {
    if ($registration_type->getThirdPartySetting('registration_confirmation', "{$confirmation_type}_email_enable")) {
      $configuration['recipient'] = $confirmation_type === 'registrant' ? $registration->getRegistrantEmail() : $registration_type->getThirdPartySetting('registration_confirmation', "admin_email_address");
      // Override subject and message if admin should just receive copy of registrant email.
      if ($confirmation_type === 'admin' && $registration_type->getThirdPartySetting('registration_confirmation', 'send_registrant_confirmation_copy')) {
        $confirmation_type = 'registrant';
      }
      $configuration['subject'] = $registration_type->getThirdPartySetting('registration_confirmation', "{$confirmation_type}_email_subject");
      $configuration['message'] = $registration_type->getThirdPartySetting('registration_confirmation', "{$confirmation_type}_email_message");
      $configuration['mail_tag'] = 'registration_confirmation';
      $configuration['log_message'] = FALSE;
      $action = $this->actionManager->createInstance('registration_send_email_action');
      $action->setConfiguration($configuration);
      if ($action->execute($registration)) {
        $this->logger->info('Sent registration confirmation email to %recipient', [
          '%recipient' => $configuration['recipient'],
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RegistrationEvents::REGISTRATION_INSERT => 'onUpdate',
      RegistrationEvents::REGISTRATION_UPDATE => 'onUpdate',
    ];
  }

}
