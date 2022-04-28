<?php

namespace Drupal\registration\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Mail\RegistrationMailerInterface;
use Drupal\registration\RegistrationManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Send reminders.
 *
 * @QueueWorker(
 *  id = "registration.send_reminders",
 *  title = @Translation("Send reminders"),
 *  cron = {"time" = 30}
 * )
 */
class SendReminders extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The registration mailer.
   *
   * @var \Drupal\registration\Mail\RegistrationMailerInterface
   */
  protected RegistrationMailerInterface $registrationMailer;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs a new SendReminders object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\registration\Mail\RegistrationMailerInterface $registration_mailer
   *   The registration mailer.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, RegistrationMailerInterface $registration_mailer, RegistrationManagerInterface $registration_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->registrationMailer = $registration_mailer;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SendReminders {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('registration.logger'),
      $container->get('registration.mailer'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $storage = $this->entityTypeManager->getStorage($data['entity_type_id']);
    $host_entity = $storage->load($data['entity_id']);
    if ($host_entity) {
      $registration_type = $this->registrationManager->getRegistrationType($host_entity);
      $states = $registration_type->getActiveStates();
      $data['states'] = array_keys($states);
      if (empty($states)) {
        $this->logger->error('There are no active registration states configured. For a reminder email to be sent, an active registration state must be specified for the @type registration type.', [
          '@type' => $registration_type->label(),
        ]);
      }
      else {
        // All clear, send email to the registrants.
        $data['subject'] = $this->t('Reminder for @title', [
          '@title' => $host_entity->label(),
        ]);
        $success_count = $this->registrationMailer->sendMail($host_entity, $data);
        if (!$success_count) {
          $this->logger->warning('Reminder email for @title had no recipients.', [
            '@title' => $host_entity->label(),
          ]);
        }
      }

      $settings = $this->registrationManager->getSettingsForHost($host_entity);
      $settings->set('send_reminder', FALSE);
      $settings->save();
    }
  }

}
