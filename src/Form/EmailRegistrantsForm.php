<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\Notify\RegistrationMailerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the broadcast to email registrants form.
 */
class EmailRegistrantsForm extends RegistrationFormBase {

  /**
   * The registration mailer.
   *
   * @var \Drupal\registration\Notify\RegistrationMailerInterface
   */
  protected RegistrationMailerInterface $registrationMailer;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->registrationMailer = $container->get('registration.notifier');
    $instance->renderer = $container->get('renderer');
    $instance->token = $container->get('token');
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
    $registrants = $this->registrationMailer->getRecipientList($host_entity);
    $registrant_count = count($registrants);

    $form = [];

    // Only send to registrants with active registrations.
    $registration_type = $host_entity->getRegistrationType();
    $states = $registration_type->getActiveStates();
    if (empty($states)) {
      $message = $this->t('There are no active registration states configured. For email to be sent, an active registration state must be specified for the @type registration type.', [
        '@type' => $registration_type->label(),
      ]);
      $this->messenger()->addError($message);
      return $form;
    }

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

      // Use a sample registration for token replacement.
      $registration = $host_entity->generateSampleRegistration();

      // Replace tokens in Subject.
      $subject = Html::escape($values['subject']);
      $this->replaceTokens($form['subject_preview'], $host_entity, $registration, $subject);

      // Replace tokens in Message.
      $build = [
        '#type' => 'processed_text',
        '#text' => $values['message']['value'],
        '#format' => $values['message']['format'],
      ];
      if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
        $message = $this->renderer->renderInIsolation($build);
      }
      else {
        // @phpstan-ignore-next-line
        $message = $this->renderer->renderPlain($build);
      }
      $this->replaceTokens($form['message_preview'], $host_entity, $registration, $message);

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
      $description = $this->t('Enter the message you want to send to registrants for %label. Tokens are supported, e.g., [node:title].', [
        '%label' => $host_entity->label(),
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Although this same check is done during form build, it is done again
    // since the admin could have changed states after the form was loaded.
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#id'] == 'edit-submit') {
      $host_entity = $this->getHostEntity($form_state);
      $registration_type = $host_entity->getRegistrationType();
      $states = $registration_type->getActiveStates();
      if (empty($states)) {
        $form_state->setError($form, $this->t('There are no active registration states configured. For email to be sent, an active registration state must be specified for the @type registration type.', [
          '@type' => $registration_type->label(),
        ]));
      }
    }
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
      $registration_type = $host_entity->getRegistrationType();
      $states = $registration_type->getActiveStates();
      $values['states'] = array_keys($states);
      $values['mail_tag'] = 'broadcast';
      $success_count = $this->registrationMailer->notify($host_entity, $values);
      $message = $this->formatPlural($success_count,
       'Registration broadcast sent to 1 recipient.',
       'Registration broadcast sent to @count recipients.',
      );
      $this->messenger()->addStatus($message);

      // Redirect to the Manage Registrations tab for the host entity.
      // Build the parameters to the route dynamically since they
      // are only known at run time based on the host entity type.
      // As an example, the route for a commerce product variation
      // includes both the product ID and the product variation ID.
      // We cannot assume the route parameters should only be based
      // on the host entity type and host entity ID, as for nodes.
      $parameters = $this->getRouteMatch()->getRawParameters()->all();

      // Set the redirect URL.
      $entity_type_id = $host_entity->getEntityTypeId();
      $url = Url::fromRoute("entity.$entity_type_id.registration.manage_registrations", $parameters);
      $form_state->setRedirectUrl($url);
    }
    else {
      // Either the Preview or Edit message button was submitted.
      $form_state->setRebuild();
    }
  }

  /**
   * Replaces tokens in a string and puts the result into a render element.
   *
   * Modifies the render element with bubbleable metadata and #markup set.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration entity.
   * @param string $input
   *   The input string with tokens.
   */
  protected function replaceTokens(array &$element, HostEntityInterface $host_entity, RegistrationInterface $registration, string $input) {
    $entities = [
      $host_entity->getEntityTypeId() => $host_entity->getEntity(),
      'registration' => $registration,
      'registration_settings' => $host_entity->getSettings(),
    ];
    $bubbleable_metadata = new BubbleableMetadata();
    $element['#markup'] = $this->token->replace($input, $entities, [], $bubbleable_metadata);
    $bubbleable_metadata->applyTo($element);
  }

}
