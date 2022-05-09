<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\RegistrationHelper;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\workflows\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Register form.
 */
class RegisterForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegisterForm {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->languageManager = $container->get('language_manager');
    $instance->logger = $container->get('registration.logger');
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    // Initialize host entity needed by the form.
    $this->setHostEntity($form_state);

    // Set cache directives so the form rebuilds when needed.
    $this->addCacheableDependencies($form, $form_state);

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();

    // Make sure registration is still allowed.
    $host_entity = $form_state->get('host_entity');
    $settings = $host_entity->getSettings();
    $count = $registration->getSpacesReserved();
    $errors = [];
    if ($registration->isNew() && !$host_entity->isEnabledForRegistration($count, $registration, $errors)) {
      foreach ($errors as $error) {
        $form['notice'][] = [
          '#markup' => $error,
        ];
      }
      return $form;
    }

    // Initialize the form with fields.
    $form = parent::form($form, $form_state);

    // Add the "Who is registering" field.
    $registrant_options = $this->registrationManager->getRegistrantOptions($registration, $settings);
    $default = NULL;
    if (!$registration->isNew()) {
      $default = $registration->getRegistrantType($this->currentUser());
    }
    elseif (count($registrant_options) == 1) {
      $keys = array_keys($registrant_options);
      $default = reset($keys);
    }

    // Show a message if there's one option as we're going to hide the field.
    if ((count($registrant_options) == 1) && !$this->currentUser()->isAnonymous()) {
      $registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME] = $this->t('Yourself');
      $message = $this->t('You are registering: %who', ['%who' => current($registrant_options)]);
      $form['who_message'] = [
        '#markup' => '<div class="registration-who-msg">' . $message . '</div>',
        '#weight' => -1,
      ];
    }

    $form['who_is_registering'] = [
      '#type' => 'select',
      '#title' => $this->t('This registration is for:'),
      '#options' => $registrant_options,
      '#default_value' => $default,
      '#required' => TRUE,
      '#access' => (count($registrant_options) > 1),
      '#weight' => -1,
    ];

    // The following checks for empty form fields, since the site admin
    // may have hidden certain fields on the form via the form display.
    // Set the User field visibility and required states.
    if (!empty($form['user_uid'])) {
      $form['user_uid']['#access'] = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER]);
      $form['user_uid']['#states'] = [
        'visible' => [
          ':input[name="who_is_registering"]' => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
      // @see https://www.drupal.org/project/drupal/issues/2855139
      $form['user_uid']['widget'][0]['target_id']['#states'] = [
        'required' => [
          ':input[name="who_is_registering"]' => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER],
        ],
      ];
    }

    // Set the Email field visibility and required states.
    if (!empty($form['anon_mail'])) {
      $anonymous_allowed = isset($registrant_options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON]);
      $form['anon_mail']['#access'] = $anonymous_allowed;
      $form['anon_mail']['#states'] = [
        'visible' => [
          ':input[name="who_is_registering"]' => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
        ],
      ];
      if ((count($registrant_options) == 1) && $anonymous_allowed) {
        $form['anon_mail']['widget'][0]['value']['#required'] = TRUE;
      }
      else {
        // @see https://www.drupal.org/project/drupal/issues/2855139
        $form['anon_mail']['widget'][0]['value']['#states'] = [
          'required' => [
            ':input[name="who_is_registering"]' => ['value' => RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON],
          ],
        ];
      }
    }

    // Update the Spaces field.
    if (!empty($form['count'])) {
      $capacity = $settings->getSetting('capacity');
      $limit = $settings->getSetting('maximum_spaces');
      $remaining = $capacity - $host_entity->getActiveSpacesReserved($registration);
      $max = 99999;

      // Plural format is not needed since the field is hidden
      // unless the user can register for more than one space.
      if ($capacity && $limit) {
        $max = min($limit, $remaining);
        $description = $this->t(
          'The number of spaces you wish to reserve. @spaces_remaining spaces remaining. You may register up to @max spaces.', [
            '@spaces_remaining' => $remaining,
            '@max' => $max,
          ]);
      }
      elseif ($capacity) {
        $max = $remaining;
        $description = $this->t('The number of spaces you wish to reserve. @spaces_remaining spaces remaining.', [
          '@spaces_remaining' => $remaining,
        ]);
      }
      elseif ($limit) {
        $max = $limit;
        $description = $this->t('The number of spaces you wish to reserve. You may register up to @max spaces.', [
          '@max' => $limit,
        ]);
      }
      else {
        $description = $this->t('The number of spaces you wish to reserve.');
      }

      // Hide the element unless the user can register for more than one space.
      $form['count']['#access'] = ($max > 1);

      // @see https://www.drupal.org/project/drupal/issues/2855139
      $form['count']['widget'][0]['value']['#description'] = $description;
      $form['count']['widget'][0]['value']['#default_value'] = $registration->getSpacesReserved();
      $form['count']['widget'][0]['value']['#max'] = $max;
    }

    // Update the Status field.
    if (!empty($form['state'])) {
      $registration_type = $registration->getType();
      $current_state = $registration->getState();
      $states = $registration_type->getStatesToShowOnForm($current_state, !$registration->isNew());

      $type = $registration_type->id();
      $form['state']['#access'] = !empty($states) && $this->currentUser()->hasPermission("edit $type registration state");
      $form['state']['widget'][0]['#options'] = array_map([
        State::class,
        'labelCallback',
      ], $states);
      $form['state']['widget'][0]['#default_value'] = $registration->getState()->id();
    }

    // Update the created field.
    $admin_theme = $this->currentUser()->hasPermission('view the administration theme');
    if (!empty($form['created'])) {
      // Hide for new registrations or non-admins.
      if ($registration->isNew() || !$admin_theme) {
        $form['created']['#access'] = FALSE;
      }
    }

    // If an admin is editing an existing registration use the advanced form.
    if (!$registration->isNew() && $admin_theme) {
      $this->useAdvancedForm($form);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $host_entity = $form_state->get('host_entity');
    $settings = $host_entity->getSettings();

    // Spaces to reserve.
    $spaces = 1;
    if ($form_state->hasValue('count')) {
      $spaces = $form_state->getValue('count')[0]['value'];
    }

    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();

    // Test status on new registrations.
    if ($registration->isNew()) {
      $errors = [];
      if (!$host_entity->isEnabledForRegistration($spaces, $registration, $errors)) {
        foreach ($errors as $error) {
          $form_state->setError($form, $error);
        }
      }
    }
    // Only check capacity for existing registrations that are active.
    elseif ($registration->isActive()) {
      if (!$host_entity->hasRoom($spaces, $registration)) {
        $form_state->setError($form, $this->t('Sorry, unable to register for %label due to: insufficient spaces remaining.', [
          '%label' => $$host_entity->label(),
        ]));
      }
    }

    // Validate according to who is registering.
    $allow_multiple = $settings->getSetting('multiple_registrations');
    switch ($form_state->getValue('who_is_registering')) {
      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON:
        if ($form_state->hasValue('anon_mail')) {
          $email = $form_state->getValue('anon_mail')[0]['value'];
          if (!$allow_multiple && $registration->isNew()) {
            if ($host_entity->isEmailRegistered($email)) {
              $form_state->setError($form['anon_mail'], $this->t('%mail is already registered for this event.', [
                '%mail' => $email,
              ]));
            }
          }
        }
        else {
          // The site admin may need to add the email field to the form display.
          $form_state->setError($form, $this->t('Email address is required.'));
        }
        break;

      case RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME:
        if (!$allow_multiple && $registration->isNew()) {
          if ($host_entity->isUserRegistered($this->currentUser())) {
            $form_state->setError($form, $this->t('You are already registered for this event.'));
          }
        }
        break;

      case RegistrationInterface:: REGISTRATION_REGISTRANT_TYPE_USER:
        if ($form_state->hasValue('user_uid')) {
          $uid = $form_state->getValue('user_uid')[0]['target_id'];
          /** @var \Drupal\user\UserInterface $user */
          $user = $this->entityTypeManager->getStorage('user')->load($uid);
          if ($user) {
            if (!$allow_multiple && $registration->isNew()) {
              if ($host_entity->isUserRegistered($user)) {
                $form_state->setError($form['user_uid'],
                  $this->t('%user is already registered for this event.', [
                    '%user' => $user->getDisplayName(),
                  ]));
              }
            }
          }
          elseif ($this->currentUser()->hasPermission('access user profiles')) {
            // The user may have been deleted just before saving this
            // registration.
            $form_state->setError($form['user_uid'], $this->t('The selected user is no longer available.'));
          }
          else {
            // General failure. Possible permissions issue.
            $form_state->setError($form['user_uid'], $this->t('Registration Failed.'));
          }
        }
        else {
          // The site admin may need to add the user field to the form display.
          $form_state->setError($form, $this->t('User name is required.'));
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $host_entity = $form_state->get('host_entity');

    // Set the user when self-registering.
    if ($form_state->getValue('who_is_registering') == RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME) {
      $registration->set('user_uid', $this->currentUser()->id());
    }

    // Save the registration.
    $return = $registration->save();

    // Log it.
    if ($user = $registration->getUser()) {
      $this->logger->info('@name registered for %label (ID #@id).', [
        '@name' => $user->getDisplayName(),
        '%label' => $host_entity->label(),
        '@id' => $registration->id(),
      ]);
    }
    else {
      $this->logger->info('@email registered for %label (ID #@id).', [
        '@email' => $registration->getEmail(),
        '%label' => $host_entity->label(),
        '@id' => $registration->id(),
      ]);
    }

    // Confirmation message.
    $settings = $host_entity->getSettings();
    $confirmation = $settings->getSetting('confirmation');
    if (!$confirmation) {
      $confirmation = $this->t('The registration was saved.');
    }
    $this->messenger()->addStatus($confirmation);

    // Redirect.
    $redirect = $settings->getSetting('confirmation_redirect');
    if ($redirect) {
      // Custom redirect in the settings.
      // Check for external first.
      if (UrlHelper::isExternal($redirect)) {
        // To be considered external, the URL helper checks
        // for dangerous protocols, so the redirect must be safe.
        // Sanitize and use it.
        $redirect = Html::escape($redirect);
        $response = new TrustedRedirectResponse($redirect);
        $form_state->setResponse($response);
      }
      else {
        // Potentially unsafe URL. Try for an internal redirect.
        $redirect = UrlHelper::stripDangerousProtocols($redirect);
        $redirect = Html::escape($redirect);
        $form_state->setRedirectUrl(Url::fromUserInput($redirect));
      }
    }
    else {
      // No redirect in the settings.
      if ($registration->access('view', $this->currentUser())) {
        // User has permission to view their registration. Redirect to the
        // registration page. Must be explicit about language here, otherwise
        // would redirect to a page in the wrong language.
        $form_state->setRedirectUrl($registration->toUrl('canonical', [
          'language' => $this->languageManager->getCurrentLanguage(),
        ]));
      }
      else {
        // Fallback to redirecting to the host entity.
        // The user should have permission to view the host
        // entity, otherwise it is unlikely they would be
        // able to reach the register page for that entity.
        $form_state->setRedirectUrl($host_entity->getEntity()->toUrl());
      }
    }

    // Must return the result from the entity save.
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $entity = $route_match->getParameter($entity_type_id);
    }
    else {
      $values = [];
      // Fetch initial values from the host entity.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
      $values['entity_type_id'] = $host_entity->getEntityTypeId();
      $values['entity_id'] = $host_entity->id();
      if ($bundle_key = $entity_type->getKey('bundle')) {
        $values[$bundle_key] = $host_entity->getRegistrationTypeBundle();
      }
      // Set the current language to record which language was used to register.
      // This is better than using the content language since a translation
      // might not be available for the host entity yet.
      $values['langcode'] = $this->languageManager->getCurrentLanguage()->getId();

      $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    }

    return $entity;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of supported Form API action elements keyed by name.
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = [];

    $host_entity = $form_state->get('host_entity');
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $count = $registration->getSpacesReserved();
    if (!$registration->isNew() || $host_entity->isEnabledForRegistration($count, $registration)) {
      // Override the button label for the Save button.
      $actions = parent::actions($form, $form_state);
      $actions['submit']['#value'] = $this->t('Save Registration');

      // Ensure language is taken into account for multilingual.
      RegistrationHelper::applyInterfaceLanguageToLinks($actions);

      // Add a Cancel link for new registrations.
      if ($registration->isNew()) {
        $actions['cancel'] = [
          '#type' => 'link',
          '#title' => $this->t('Cancel'),
          '#url' => $host_entity->getEntity()->toUrl(),
          '#weight' => 20,
        ];
      }
    }

    return $actions;
  }

  /**
   * Adds cache directives to the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function addCacheableDependencies(array &$form, FormStateInterface $form_state) {
    $host_entity = $form_state->get('host_entity');
    $settings = $form_state->get('settings');

    $host_entity->addCacheableDependencies($form, [$settings]);
  }

  /**
   * Ensure the host entity is set.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function setHostEntity(FormStateInterface $form_state) {
    $host_entity = $form_state->get('host_entity');
    if (!isset($host_entity)) {
      $parameters = $this->getRouteMatch()->getParameters();
      $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
      if ($host_entity instanceof RegistrationInterface) {
        // Editing a registration. Get the host entity from the registration.
        $host_entity = $host_entity->getHostEntity();
      }
      $form_state->set('host_entity', $host_entity);
    }
  }

  /**
   * Modify the form to use the Advanced interface.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *   The current state of the form.
   */
  protected function useAdvancedForm(array &$form): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->entity;

    $form['#tree'] = TRUE;
    $form['#theme'] = ['registration_form'];
    $form['#attached']['library'][] = 'registration/form';
    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $registration->getChangedTime(),
    ];
    $form['state']['#group'] = 'footer';

    $last_saved = $this->t('Not saved yet');
    if (!$registration->isNew()) {
      $last_saved = $this->dateFormatter->format($registration->getChangedTime(), 'short');
    }
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $registration->getState()->label(),
        '#access' => !$registration->isNew(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
      ],
      'changed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['entity-meta__last-saved', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Last saved') . '</h4> ' . $last_saved,
      ],
      'author' => [
        '#type' => 'item',
        '#access' => $registration->getAuthorDisplayName(),
        '#wrapper_attributes' => [
          'class' => ['author', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Author') . '</h4> ' . $registration->getAuthorDisplayName(),
      ],
    ];
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['registration-form-author'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
    ];
    if (isset($form['author_uid'])) {
      $form['author_uid']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    return $form;
  }

}
