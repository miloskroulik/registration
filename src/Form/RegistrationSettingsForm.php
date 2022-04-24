<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the registration settings form.
 */
class RegistrationSettingsForm extends FormBase {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The host entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $hostEntity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a RegistrationLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RegistrationManagerInterface $registration_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationSettingsForm {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'registration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->setHostEntity();
    $this->setEntity();

    $form = [];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Check to enable registrations.'),
      '#default_value' => $this->getRegistrationSetting('status'),
    ];
    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#description' => $this->t('The maximum number of registrants. Leave at 0 for no limit.'),
      '#min' => 0,
      '#max' => 99999,
      '#required' => TRUE,
      '#default_value' => $this->getRegistrationSetting('capacity'),
    ];

    $form['scheduling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scheduling'),
    ];
    $date = $this->getRegistrationSetting('open');
    $default_value = $date ? DrupalDateTime::createFromTimestamp(strtotime($date)) : '';
    $form['scheduling']['open'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Open Date'),
      '#description' => $this->t('When to automatically open registrations. (This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]),
      '#default_value' => $default_value,
    ];
    $date = $this->getRegistrationSetting('close');
    $default_value = $date ? DrupalDateTime::createFromTimestamp(strtotime($date)) : '';
    $form['scheduling']['close'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Close Date'),
      '#description' => $this->t('When to automatically close registrations. (This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]),
      '#default_value' => $default_value,
    ];

    // Reminders.
    $form['reminder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reminder'),
    ];
    $form['reminder']['send_reminder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send Reminder'),
      '#description' => $this->t('If checked, a reminder will be sent to registrants on the following date.'),
      '#default_value' => (bool) $this->getRegistrationSetting('send_reminder'),
    ];
    $date = $this->getRegistrationSetting('reminder_date');
    $default_value = $date ? DrupalDateTime::createFromTimestamp(strtotime($date)) : '';
    $form['reminder']['reminder_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Reminder Date'),
      '#description' => $this->t('When to send reminders. (This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]),
      '#default_value' => $default_value,
    ];
    $default_value = '';
    $default_format = filter_default_format();
    $template = $this->getRegistrationSetting('reminder_template');
    if (!empty($template)) {
      if (is_string($template)) {
        $template = unserialize($template);
      }
      $default_value = $template['value'];
      $default_format = $template['format'];
    }
    $form['reminder']['reminder_template'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Reminder Email Template'),
      '#default_value' => $default_value,
      '#format' => $default_format,
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['reminder']['reminder_template']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          $this->getHostEntity()->getEntityTypeId(),
          'registration',
        ],
        '#global_types' => FALSE,
      ];
    }

    // Additional settings.
    $form['settings'] = [
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => $this->t('Additional settings'),
    ];
    $form['settings']['maximum_spaces'] = [
      '#type' => 'number',
      '#title' => $this->t('Spaces allowed'),
      '#min' => 0,
      '#max' => 9999,
      '#required' => TRUE,
      '#description' => $this->t('The maximum number of spaces allowed for each registrations. For no limit, use 0. (Default is 1)'),
      '#default_value' => $this->getRegistrationSetting('maximum_spaces'),
    ];
    $form['settings']['multiple_registrations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple registrations'),
      '#description' => $this->t('If selected, each person can create multiple registrations for this event.'),
      '#default_value' => $this->getRegistrationSetting('multiple_registrations'),
    ];
    $form['settings']['from_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Address'),
      '#description' => $this->t('From email address to use for confirmations, reminders, and broadcast emails.'),
      '#default_value' => $this->getRegistrationSetting('from_address'),
      '#required' => TRUE,
    ];
    $form['settings']['confirmation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation Message'),
      '#description' => $this->t('The message to display when someone registers. Leave blank for none.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getRegistrationSetting('confirmation'),
    ];
    $form['settings']['confirmation_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation redirect path'),
      '#description' => $this->t('Optional path to redirect to when someone registers. Leave blank to redirect to the registration itself if the user has permission or the host entity if they do not.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getRegistrationSetting('confirmation_redirect'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // If sending a reminder, ensure date and template are set.
    if ($values['send_reminder'] && empty($values['reminder_date'])) {
      $form_state->setError(
        $form['reminder']['reminder_date'], $this->t('If sending a reminder, provide a date and template.'));
    }
    if ($values['send_reminder'] && empty($values['reminder_template']['value'])) {
      $form_state->setError(
        $form['reminder']['reminder_template'], $this->t('If sending a reminder, provide a date and template.'));
    }

    // Ensure reminder date is not in the past when "send_reminder" is TRUE:
    if ($values['send_reminder'] && !empty($values['reminder_date'])) {
      if ($values['reminder_date'] instanceof DrupalDateTime) {
        if (strtotime($values['reminder_date']) <= time()) {
          $form_state->setError($form['reminder']['reminder_date'], $this->t('Reminder must be in the future.'));
        }
      }
    }

    // If a redirect is set must either be external or start with a slash.
    if ($redirect = $values['settings']['confirmation_redirect']) {
      if (!UrlHelper::isExternal($redirect) && ($redirect[0] != '/')) {
        $form_state->setError($form['settings']['confirmation_redirect'], $this->t('Confirmation redirect path must be a valid URL. Internal paths must start with a forward slash.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save values to the settings entity.
    $entity = $this->getEntity();
    $values = $form_state->getValues();
    $fields = [
      'status' => 'int',
      'capacity' => 'int',
      'open' => 'date',
      'close' => 'date',
      'send_reminder' => 'bool',
      'reminder_date' => 'date',
    ];
    foreach ($fields as $field => $type) {
      if (!isset($values[$field])) {
        $entity->set($field, NULL);
      }
      elseif ($values[$field] === '') {
        $entity->set($field, NULL);
      }
      elseif ($type == 'date') {
        // Without \T the Views module cannot filter or sort properly.
        $entity->set($field, $values[$field]->format('Y-m-d\TH:i:s'));
      }
      else {
        $entity->set($field, $values[$field]);
      }
    }

    // Serialize reminder template.
    if (!empty($values['reminder_template']['value'])) {
      $entity->set('reminder_template', serialize($values['reminder_template']));
    }
    else {
      $entity->set('reminder_template', NULL);
    }
    // Serialize additional settings.
    $entity->set('settings', serialize($values['settings']));

    $entity->save();
    $this->messenger()->addStatus($this->t('The settings have been saved.'));
  }

  /**
   * Gets the settings entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The settings entity.
   */
  protected function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Sets the settings entity.
   */
  protected function setEntity() {
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $this->entity = $storage->loadSettingsForEntity($this->getHostEntity());

  }

  /**
   * Gets the host entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The host entity, for example, a node.
   */
  protected function getHostEntity(): EntityInterface {
    return $this->hostEntity;
  }

  /**
   * Sets the host entity.
   */
  protected function setHostEntity() {
    $route_match = $this->getRouteMatch();
    $this->hostEntity = $this->registrationManager->getEntityFromParameters($route_match->getParameters());
  }

  /**
   * Returns the setting value for a given key.
   *
   * @param string $key
   *   The key.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getRegistrationSetting(string $key): mixed {
    return $this->registrationManager->getRegistrationSetting($this->getHostEntity(), $this->getEntity(), $key);
  }

}
