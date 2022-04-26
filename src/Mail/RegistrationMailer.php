<?php

namespace Drupal\registration\Mail;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\RegistrationManagerInterface;

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
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * Creates a RegistrationMailer object.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(AccountProxy $current_user, MailManagerInterface $mail_manager, RegistrationManagerInterface $registration_manager, Renderer $renderer, Token $token) {
    $this->currentUser = $current_user;
    $this->mailManager = $mail_manager;
    $this->registrationManager = $registration_manager;
    $this->renderer = $renderer;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmailRecipientList(EntityInterface $host_entity, RegistrationSettings $settings, array $data = []): array {
    if (!empty($data['test'])) {
      $registrations = [$this->registrationManager->generateSampleRegistration($host_entity)];
    }
    elseif (!empty($data['states'])) {
      $registrations = $this->registrationManager->getRegistrationList($host_entity, $settings, $data['states']);
    }
    else {
      $registrations = $this->registrationManager->getRegistrationList($host_entity, $settings);
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

    // @todo Call the event here.
    return $recipients;
  }

  /**
   * {@inheritdoc}
   */
  public function replaceTokens(array &$element, EntityInterface $host_entity, RegistrationSettings $settings, RegistrationInterface $registration, string $input) {
    $entities = [
      $host_entity->getEntityTypeId() => $host_entity,
      'registration' => $registration,
      'registration_settings' => $settings,
    ];
    $bubbleable_metadata = new BubbleableMetadata();
    $element['#markup'] = $this->token->replace($input, $entities, [], $bubbleable_metadata);
    $bubbleable_metadata->applyTo($element);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMail(EntityInterface $host_entity, RegistrationSettings $settings, array $data = []) {
    $langcode = $this->currentUser->getPreferredLangcode();
    $send = TRUE;

    $registrants =  $this->getEmailRecipientList($host_entity, $settings, $data);
    foreach ($registrants as $email => $registrations) {
      // Convert singleton to array.
      if ($registrations instanceof RegistrationInterface) {
        $registrations = [$registrations];
      }
      foreach ($registrations as $registration) {
        $params = [];

        // Subject.
        $build = [];
        $subject = Html::escape($data['subject']);
        $this->replaceTokens($build, $host_entity, $settings, $registration, $subject);
        $params['subject'] = $build['#markup'];

        // Message.
        $build = [
          '#type' => 'processed_text',
          '#text' => $data['message']['value'],
          '#format' => $data['message']['format'],
        ];
        $message = $this->renderer->render($build);
        $build = [];
        $this->replaceTokens($build, $host_entity, $settings, $registration, $message);
        $params['message'] = $build['#markup'];

        $result = $this->mailManager->mail('registration', 'email_registrants', $email, $langcode, $params, NULL, $send);
      }
    }
  }

}
