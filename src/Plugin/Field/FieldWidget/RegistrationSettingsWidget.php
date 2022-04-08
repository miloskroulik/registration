<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Plugin implementation of the 'registration_settings' widget.
 *
 * @FieldWidget(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   field_types = {
 *     "registration"
 *   }
 * )
 */
class RegistrationSettingsWidget extends WidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The modile handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeBundleInfo = Drupal::service('entity_type.bundle.info');
    $this->entityTypeManager = Drupal::entityTypeManager();
    $this->moduleHandler = Drupal::moduleHandler();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'status' => 0,
      'registration_type' => '',
      'capacity' => 0,
      'open' => '',
      'close' => '',
      'send_reminder' => 0,
      'reminder_date' => '',
      'reminder_template' => '',
      'maximum_spaces' => 1,
      'multiple_registrations' => 0,
      'from_address' => Drupal::service('config.factory')->get('system.site')->get('mail'),
      'confirmation' => 'Registration has been saved.',
      'confirmation_redirect' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['#element_validate'][] = [static::class, 'validateElement'];

    $element['registration_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Registration settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];
    $element['registration_settings'] += $this->buildSettingsForm($items, $delta);

    return $element;
  }

  /**
   * Form validation handler for widget elements.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    // Skip error checking of default values on the field settings form.
    if (!empty($element['#field_parents']) && ($element['#field_parents'][0] == 'default_value_input')) {
      return;
    }

    // Skip error checking if registrations are not enabled.
    $status = $form_state->getValue($element['registration_settings']['status']['#parents']);
    if (!$status) {
      return;
    }

    // If sending a reminder, ensure date and template are set.
    $reminder = $form_state->getValue($element['registration_settings']['reminder']['#parents']);
    if ($reminder['send_reminder']
      && (empty($reminder['reminder_date_container']['reminder_date']) ||
        empty($reminder['reminder_template']['value']))
    ) {
      $form_state->setError($element['registration_settings']['reminder'], t('If sending a reminder, provide a date and template.'));
    }

    // Ensure reminder date is not in the past when "send_reminder" is TRUE:
    if ($reminder['send_reminder'] && !empty($reminder['reminder_date_container']['reminder_date'])) {
      if ($reminder['reminder_date_container']['reminder_date'] instanceof DrupalDateTime) {
        if (strtotime($reminder['reminder_date_container']['reminder_date']) <= time()) {
          $form_state->setError($element['registration_settings']['reminder']['reminder_date_container'], t('Reminder must be in the future.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Flatten the registration settings.
    $new_values = [];
    foreach ($values[0]['registration_settings'] as $key => $data) {
      if ($key == 'settings') {
        $new_values[$key] = $data;
      }
      elseif (is_array($data)) {
        foreach ($data as $sub_key => $sub_data) {
          if ($sub_key == 'reminder_date_container') {
            $new_values['reminder_date'] = $sub_data['reminder_date'];
          }
          else {
            $new_values[$sub_key] = $sub_data;
          }
        }
      }
      else {
        $new_values[$key] = $data;
      }
    }
    // Map arrays, dates and blank strings to the correct primitives.
    return array_map(function ($value) {
      if (is_array($value)) {
        return serialize($value);
      }
      elseif ($value instanceof DrupalDateTime) {
        return $value->format('Y-m-d H:i:s');
      }
      elseif ($value === '') {
        return NULL;
      }
      else {
        return $value;
      }
    }, $new_values);
  }

  /**
   * Build the settings form.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The widget items.
   * @param int $delta
   *   The item index.
   *
   * @return array
   *   The form.
   */
  protected function buildSettingsForm(FieldItemListInterface $items, int $delta): array {
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $bundle = $bundle_info[$entity_bundle]['label'];

    $form = [];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Check to enable registrations.'),
      '#default_value' => $this->getRegistrationSetting($items, 'status'),
    ];
    $form['registration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#options' => $this->getRegistrationTypeOptions(),
      '#default_value' => $this->getRegistrationSetting($items, 'registration_type'),
      '#description' => $this->t('Select what type of registrations should be enabled for this @type. Depending on the display settings, it will appear as either string, registration link, or form.', [
        '@type' => $bundle,
      ]),
    ];
    $form['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#description' => $this->t('The maximum number of registrants. Leave at 0 for no limit.'),
      '#min' => 0,
      '#max' => 99999,
      '#required' => TRUE,
      '#default_value' => $this->getRegistrationSetting($items, 'capacity'),
    ];

    $form['scheduling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scheduling'),
    ];
    $date = $this->getRegistrationSetting($items, 'open');
    $default_value = $date ? DrupalDateTime::createFromTimestamp(strtotime($date)) : '';
    $form['scheduling']['open'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Open Date'),
      '#description' => $this->t('When to automatically open registrations. (This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]),
      '#default_value' => $default_value,
    ];
    $date = $this->getRegistrationSetting($items, 'close');
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
      '#default_value' => (bool) $this->getRegistrationSetting($items, 'send_reminder'),
    ];
    // Container is required for visibility states to work for a checkbox.
    $form['reminder']['reminder_date_container'] = [
      '#type' => 'container',
    ];
    $date = $this->getRegistrationSetting($items, 'reminder_date');
    $default_value = $date ? DrupalDateTime::createFromTimestamp(strtotime($date)) : '';
    $form['reminder']['reminder_date_container']['reminder_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Reminder Date'),
      '#description' => $this->t('When to send reminders. (This uses the @timezone timezone.)', [
        '@timezone' => date_default_timezone_get(),
      ]),
      '#default_value' => $default_value,
    ];
    $default_value = '';
    $default_format = filter_default_format();
    $template = unserialize($this->getRegistrationSetting($items, 'reminder_template'));
    if (!empty($template)) {
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
      // Container is required for visibility states to work for a token tree.
      $form['reminder']['reminder_template']['token_tree_container'] = [
        '#type' => 'container',
        '#weight' => 10,
      ];
      $form['reminder']['reminder_template']['token_tree_container']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          $entity_type,
          'registration',
        ],
        '#global_types' => FALSE,
      ];
    }

    // Visibility states for the reminder fields.
    $field_name = $items->getFieldDefinition()->getName();
    $name = $field_name . '[' . $delta . '][registration_settings][reminder][send_reminder]';
    $form['reminder']['reminder_date_container']['#states'] = [
      'visible' => [
        ':input[name="' . $name . '"]' => ['checked' => TRUE],
      ],
    ];
    $form['reminder']['reminder_template']['#states'] = [
      'visible' => [
        ':input[name="' . $name . '"]' => ['checked' => TRUE],
      ],
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['reminder']['reminder_template']['token_tree_container']['#states'] = [
        'visible' => [
          ':input[name="' . $name . '"]' => ['checked' => TRUE],
        ],
      ];
    }

    // Additional settings.
    $form['settings'] = [
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
      '#default_value' => $this->getRegistrationSetting($items, 'maximum_spaces'),
    ];
    $form['settings']['multiple_registrations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple registrations'),
      '#description' => $this->t('If selected, each person can create multiple registrations for this event.'),
      '#default_value' => $this->getRegistrationSetting($items, 'multiple_registrations'),
    ];
    $form['settings']['from_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Address'),
      '#description' => $this->t('From email address to use for confirmations, reminders, and broadcast emails.'),
      '#default_value' => $this->getRegistrationSetting($items, 'from_address'),
    ];
    $form['settings']['confirmation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation Message'),
      '#description' => $this->t('The message to display when someone registers. Leave blank for none.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getRegistrationSetting($items, 'confirmation'),
    ];
    $form['settings']['confirmation_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation redirect path'),
      '#description' => $this->t('Optional path to redirect to when someone registers. Leave blank to redirect to the registration itself if the user has permission or the host entity if they do not.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getRegistrationSetting($items, 'confirmation_redirect'),
    ];

    // Visibility states for the main settings.
    // Hides settings unless registrations are enabled.
    // These only work for entity forms and not the field settings form.
    // That is be design, we don't want anything hidden on field settings.
    $field_name = $items->getFieldDefinition()->getName();
    $name = $field_name . '[' . $delta . '][registration_settings][status]';

    foreach (Element::children($form) as $key) {
      if ($key != 'status') {
        $form[$key]['#states'] = [
          'visible' => [
            ':input[name="' . $name . '"]' => ['checked' => TRUE],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * Returns the setting value for a given key.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The widget items.
   * @param string $key
   *   The key.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getRegistrationSetting(FieldItemListInterface $items, string $key): mixed {
    $entity = $items->getEntity();
    $field_name = $items->getFieldDefinition()->getName();
    if (!$entity->get($field_name)->isEmpty()) {
      // Entity has settings, return the key value.
      $settings = $entity->get($field_name)->first()->getValue();
      $settings += unserialize($settings['settings']);
      return $settings[$key];
    }

    // Use the configured default for an entity with no settings yet.
    return $this->getSetting($key);
  }

  /**
   * Returns an array of registration type options.
   *
   * @return array
   *   The array keyed by registration type machine name.
   */
  protected function getRegistrationTypeOptions(): array {
    $options = ['' => $this->t('-- Disable Registrations --')];
    $entities = $this->entityTypeManager->getStorage('registration_type')->loadMultiple();
    foreach ($entities as $id => $entity) {
      $options[$id] = $entity->label();
    }
    return $options;
  }

}
