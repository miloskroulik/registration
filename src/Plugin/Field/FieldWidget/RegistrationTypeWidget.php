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
 * Plugin implementation of the 'registration_type' widget.
 *
 * @FieldWidget(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   field_types = {
 *     "registration"
 *   }
 * )
 */
class RegistrationTypeWidget extends WidgetBase {

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
      'hide_register_tab' => FALSE,
      'status' => 0,
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
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['hide_register_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Register Tab'),
      '#description' => t('Hide the tab on the content displaying the registration form. The form can still be embedded or linked to by changing the field display settings.'),
      '#default_value' => (bool) $this->getSetting('hide_register_tab'),
    ];
    $element['default_registration_settings'] = [
      '#type' => 'item',
      '#markup' => $this->t('<strong>Default Registration Settings:</strong>'),
    ];
    $element['capacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Capacity'),
      '#description' => $this->t('The maximum number of registrants. Leave at 0 for no limit.'),
      '#min' => 0,
      '#max' => 99999,
      '#required' => TRUE,
      '#default_value' => $this->getSetting('capacity'),
    ];
    $element['send_reminder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send Reminder'),
      '#description' => $this->t('If checked, a reminder will be sent to registrants on the following date.'),
      '#default_value' => (bool) $this->getSetting('send_reminder'),
    ];
    $default_value = '';
    $default_format = filter_default_format();
    $template = $this->getSetting('reminder_template');
    if (!empty($template)) {
      $default_value = $template['value'];
      $default_format = $template['format'];
    }
    $element['reminder_template'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Reminder Email Template'),
      '#default_value' => $default_value,
      '#format' => $default_format,
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $entity_type = 'commerce_product_variation';
      $element['token_tree_container']['token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          $entity_type,
          'registration',
        ],
        '#global_types' => FALSE,
      ];
    }
    $element['maximum_spaces'] = [
      '#type' => 'number',
      '#title' => $this->t('Spaces allowed'),
      '#min' => 0,
      '#max' => 9999,
      '#required' => TRUE,
      '#description' => $this->t('The maximum number of spaces allowed for each registrations. For no limit, use 0. (Default is 1)'),
      '#default_value' => $this->getSetting('maximum_spaces'),
    ];
    $element['multiple_registrations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple registrations'),
      '#description' => $this->t('If selected, each person can create multiple registrations for this event.'),
      '#default_value' => $this->getSetting('multiple_registrations'),
    ];
    $element['from_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From Address'),
      '#description' => $this->t('From email address to use for confirmations, reminders, and broadcast emails.'),
      '#default_value' => $this->getSetting('from_address'),
    ];
    $element['confirmation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation Message'),
      '#description' => $this->t('The message to display when someone registers. Leave blank for none.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getSetting('confirmation'),
    ];
    $element['confirmation_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation redirect path'),
      '#description' => $this->t('Optional path to redirect to when someone registers. Leave blank to redirect to the registration itself if the user has permission or the host entity if they do not.'),
      '#size' => 60,
      '#maxlength' => 120,
      '#default_value' => $this->getSetting('confirmation_redirect'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('hide_register_tab')) {
      $summary[] = $this->t('Hide the Register tab');
    }
    $capacity = $this->getSetting('capacity');
    if ($capacity == 0) {
      $capacity = $this->t('No limit');
    }
    $summary[] = $this->t('Capacity: @capacity', ['@capacity' => $capacity]);
    if ($this->getSetting('send_reminder')) {
      $summary[] = $this->t('Send reminders');
    }
    $template = $this->getSetting('reminder_template');
    if (!empty($template) && !empty($template['value'])) {
      $summary[] = $this->t('Reminder template is set');
    }
    $maximum_spaces = $this->getSetting('maximum_spaces');
    if ($maximum_spaces == 0) {
      $maximum_spaces = $this->t('No limit');
    }
    $summary[] = $this->t('Maximum spaces: @max', ['@max' => $maximum_spaces]);
    if ($this->getSetting('multiple_registrations')) {
      $summary[] = $this->t('Allow multiple registrations per user');
    }
    $summary[] = $this->t('From address: @address', ['@address' => $this->getSetting('from_address')]);
    $summary[] = $this->t('Confirmation: @message', ['@message' => $this->getSetting('confirmation')]);
    if ($this->getSetting('confirmation_redirect')) {
      $summary[] = $this->t('Confirmation redirect: @redirect', ['@redirect' => $this->getSetting('confirmation_redirect')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $bundle = $bundle_info[$entity_bundle]['label'];

    $default_value = $items[$delta]->get('registration_type')->getValue();
    $element['registration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#options' => $this->getRegistrationTypeOptions(),
      '#default_value' => $default_value,
      '#description' => $this->t('Select what type of registrations should be enabled for this @type. Depending on the display settings, it will appear as either string, registration link, or form.', [
        '@type' => $bundle,
      ]),
    ];
  
    return $element;
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
