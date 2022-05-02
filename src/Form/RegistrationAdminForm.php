<?php

namespace Drupal\registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Allows the site admin to configure global registration settings.
 */
class RegistrationAdminForm extends ConfigFormBase {

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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('registration.settings')
      ->set('set_and_forget', $form_state->getValue('set_and_forget'))
      ->set('hide_filter', $form_state->getValue('hide_filter'))
      ->set('queue_notifications', $form_state->getValue('queue_notifications'))
      ->save();
  }

}
