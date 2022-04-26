<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Mail\RegistrationMailerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the broadcast to email registrants form.
 */
class EmailRegistrantsForm extends RegistrationFormBase {

  /**
   * The registration mailer.
   *
   * @var \Drupal\registration\Mail\RegistrationMailerInterface
   */
  protected RegistrationMailerInterface $registrationMailer;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->registrationMailer = $container->get('registration.mailer');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'email_registrants';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Setup.
    $host_entity = $this->getHostEntity($form_state);
    $settings = $this->getSettings($form_state);
    $registrants =  $this->registrationMailer->getEmailRecipientList($host_entity, $settings);
    $registrant_count =  count($registrants);

    $form = [];

    // If no registrants yet then take an early exit.
    if ($registrant_count == 0) {
      $form['notice'] = [
        '#markup' => $this->t('There are no registrants for %name', [
          '%name' => $host_entity->label(),
        ]),
      ];
      return $form;
    }

    // Check if doing a preview or not.
    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement() ?? ['#id' => 'edit-submit'];
    $preview = ($triggering_element['#id'] == 'edit-preview');

    if ($preview) {
      // In preview mode, display subject and message with tokens replaced
      // so the user can see what the resulting subject and message will be.
      $form['subject_preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Subject'),
      ];
      $form['message_preview'] = [
        '#type' => 'item',
        '#title' => $this->t('Message'),
      ];

      // Use a sample registration.
      $registration = $this->registrationManager->generateSampleRegistration($host_entity);

      // Replace tokens in Subject.
      $subject = Html::escape($values['subject']);
      $this->registrationMailer->replaceTokens($form['subject_preview'], $host_entity, $settings, $registration, $subject);

      // Replace tokens in Message.
      $build = [
        '#type' => 'processed_text',
        '#text' => $values['message']['value'],
        '#format' => $values['message']['format'],
      ];
      $message = $this->renderer->render($build);
      $this->registrationMailer->replaceTokens($form['message_preview'], $host_entity, $settings, $registration, $message);

      // Hidden fields for the next submit.
      $form['subject'] = [
        '#type' => 'hidden',
        '#value' => $values['subject'],
      ];
      $form['message'] = [
        '#type' => 'hidden',
        '#value' => $values['message'],
      ];
    }
    else {
      // Not in preview mode, do a standard form build.
      $form['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#required' => TRUE,
        '#default_value' => $values['subject'] ?? '',
      ];
      $description = $this->formatPlural($registrant_count,
       'Enter the message you want to send to 1 registrant. Tokens are supported if the module is enabled, E.g., [node:title].',
       'Enter the message you want to send to @count registrants. Tokens are supported if the module is enabled, E.g., [node:title].', [
      ]);
      $form['message'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Message'),
        '#required' => TRUE,
        '#description' => $description,
        '#default_value' => $values['message']['value'] ?? '',
        '#format' => $values['message']['format'] ?? filter_default_format(),
      ];
      if ($this->moduleHandler->moduleExists('token')) {
        $form['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [
            $host_entity->getEntityTypeId(),
            'registration',
            'registration_settings',
          ],
          '#global_types' => FALSE,
          '#weight' => 10,
        ];
      }
    }

    // Send button that will kick off the emails.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    if ($preview) {
      // In preview mode already, provide a button to re-edit the message.
      $form['actions']['message'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit message'),
        '#button_type' => 'secondary',
      ];
    }
    else {
      // Not in preview mode, provide a button to do a preview.
      $form['actions']['preview'] = [
        '#type' => 'submit',
        '#value' => $this->t('Preview'),
        '#button_type' => 'secondary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#id'] == 'edit-submit') {
      // The Send button was submitted. Fire off the emails.
      $host_entity = $this->getHostEntity($form_state);
      $settings = $this->getSettings($form_state);
      $this->registrationMailer->sendMail($host_entity, $settings, $values);
      $this->messenger()->addStatus($this->t('The emails have been sent.'));

      $entity_id = $host_entity->id();
      $entity_type_id = $host_entity->getEntityTypeId();

      $url = Url::fromRoute("entity.$entity_type_id.manage_registrations", [
        $entity_type_id => $entity_id,
      ]);
      $form_state->setRedirectUrl($url);
    }
    else {
      // Either the Preview or Edit message button was submitted.
      $form_state->setRebuild();
    }
  }

  /**
   * Gets the settings for a host entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings.
   */
  protected function getSettings(FormStateInterface $form_state): RegistrationSettings {
    $settings = $form_state->get('settings');
    if (!$settings) {
      $host_entity = $this->getHostEntity($form_state);
      $settings = $this->registrationManager->getSettingsForHost($host_entity);
      $form_state->set('settings', $settings);
    }
    return $settings;
  }

}
