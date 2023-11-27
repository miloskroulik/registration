<?php

namespace Drupal\registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\registration\Entity\RegistrationType;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows the site admin to configure global registration settings.
 */
class RegistrationAdminForm extends ConfigFormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationAdminForm {
    $instance = parent::create($container);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'registration_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['registration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('registration.settings');

    $form['set_and_forget'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set and forget mode'),
      '#default_value' => $config->get('set_and_forget'),
      '#description' => $this->t('Automatically maintains the <strong>Enable</strong> registrations checkbox on the per-entity Settings form based on the open and close dates on the Settings page. This is useful for displaying and removing Register links as soon as registration for a given event opens and closes. Requires a properly configured Cron task that runs at least once an hour. This mode is selected automatically by default, but you can disable it for backwards compatibility with the Drupal 7 version of the module. Sites that enable this may wish to hide the <strong>Enable</strong> field on the Registration settings <a href="/admin/structure/registration-settings/form-display">form display</a>. Note that registrations are still enabled and disabled properly without this, but users may receive messaging such as "Registrations are closed" on the Register page unless the site admin manually unchecks the Enable box on the Settings form at the appropriate time. With this mode set, links to the Register page are automatically removed once the close date is reached for a given event.'),
    ];

    $form['limit_field_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit registration field values by role'),
      '#default_value' => $config->get('limit_field_values'),
      '#description' => $this->t('Adds a new permission "Assign this type to host entity registration fields" per registration type. This allows site builders to vary the allowed registration type values for registration fields by user role. Most sites can leave this option disabled. See <a href="https://www.drupal.org/project/registration/issues/1683116" target="_blank">this issue on Drupal.org</a> for more information. Note that site builders can already restrict the allowed values per host entity bundle using the "Allowed types" registration field setting, however that restriction is global and affects all users equally.'),
    ];

    $form['hide_filter'] = [
      '#type' => 'number',
      '#title' => $this->t('Manage Registrations filter threshold'),
      '#min' => 1,
      '#max' => 999,
      '#default_value' => $config->get('hide_filter'),
      '#description' => $this->t('Hide the Find filter on the Manage Registrations page when there are fewer than this number of registrations for a given event or host entity. The default is 10 and is the recommended value. Set to 1 if you want the filter to always appear.'),
      '#required' => TRUE,
    ];

    $form['queue_notifications'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue notifications threshold'),
      '#min' => 0,
      '#max' => 999,
      '#default_value' => $config->get('queue_notifications'),
      '#description' => $this->t('Queue notifications (e.g., emails) to be sent via Cron instead of interactively from the Email Registrants broadcast form when there are more than this number of registrations for a given event or host entity. The default is 50 and is the recommended value.'),
      '#required' => TRUE,
    ];

    $form['mail_handling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mail handling'),
    ];
    $form['mail_handling']['html_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send email as HTML'),
      '#default_value' => $config->get('html_email'),
      '#description' => $this->t('Adds "text/html; charset=UTF-8; format=flowed; delsp=yes" as a Content-Type header.'),
    ];

    $form['multilingual'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Multilingual'),
      '#access' => $this->languageManager->isMultilingual(),
    ];
    $form['multilingual']['sync_registration_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Synchronize registration settings'),
      '#default_value' => $config->get('sync_registration_settings'),
      '#description' => $this->t('Synchronizes untranslatable field values for registration settings across all language variants. For example, if you set the capacity for an event to 100 using the English version of the registration settings form, the capacity for people registering using a different language would automatically be set to 100 to match. An event subscriber can be used to customize the fields to which this applies. By default, it applies to all fields that are not strings or text, such as numeric and date fields. Most multilingual sites should enable this option, unless there should be different settings depending on the language used during registration.'),
    ];
    $form['multilingual']['sync_registration_settings_all_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Synchronize all fields'),
      '#default_value' => $config->get('sync_registration_settings_all_fields'),
      '#description' => $this->t('Synchronizes all registration settings fields, including translatable fields, across all language variants. This setting is designed for use when there is a single content language, and one or more additional languages are installed for site administration only. Most multilingual sites should leave this option disabled.'),
      '#states' => [
        'visible' => [
          ':input[name="sync_registration_settings"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $original_limit_field_values = $this->config('registration.settings')->get('limit_field_values');

    $this->config('registration.settings')
      ->set('set_and_forget', $form_state->getValue('set_and_forget'))
      ->set('limit_field_values', $form_state->getValue('limit_field_values'))
      ->set('hide_filter', $form_state->getValue('hide_filter'))
      ->set('queue_notifications', $form_state->getValue('queue_notifications'))
      ->set('html_email', $form_state->getValue('html_email'))
      ->set('sync_registration_settings', $form_state->getValue('sync_registration_settings'))
      ->set('sync_registration_settings_all_fields', $form_state->getValue('sync_registration_settings_all_fields'))
      ->save();

    // Check if the "limit_field_values" setting has been newly disabled.
    $limit_field_values = $this->config('registration.settings')->get('limit_field_values');
    if ($original_limit_field_values && !$limit_field_values) {
      $this->removeAssignTypePermissions();
    }
  }

  /**
   * Removes the "assign type" permissions that no longer exist.
   */
  protected function removeAssignTypePermissions() {
    $roles = Role::loadMultiple();
    $types = RegistrationType::loadMultiple();
    foreach ($types as $type => $entity) {
      foreach ($roles as $role) {
        if (!$role->isAdmin() && $role->hasPermission("assign $type registration field")) {
          $role->revokePermission("assign $type registration field");
          $role->save();
        }
      }
    }
  }

}
