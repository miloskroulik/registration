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

    $form['notice'] = [
      '#markup' => $this->t('Configuration fields will go here. Add a note explaining why Manage Display is gone.'),
    ];

    return $form;
  }

}
