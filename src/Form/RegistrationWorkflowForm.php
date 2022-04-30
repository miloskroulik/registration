<?php

namespace Drupal\registration\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Plugin\WorkflowTypeConfigureFormBase;
use Drupal\workflows\State;

/**
 * The registration WorkflowType configuration form.
 *
 * @see \Drupal\registration\Plugin\WorkflowType\Registration
 */
class RegistrationWorkflowForm extends WorkflowTypeConfigureFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $workflow_type_configuration = $this->workflowType->getConfiguration();
    $form['workflow_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Workflow Settings'),
      '#open' => TRUE,
    ];
    $form['workflow_settings']['default_registration_state'] = [
      '#title' => $this->t('Default registration state'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => array_map([State::class, 'labelCallback'], $this->workflowType->getStates()),
      '#description' => $this->t('Select the default state for new registration types.'),
      '#default_value' => $workflow_type_configuration['default_registration_state'] ?? 'pending',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->workflowType->getConfiguration();
    $configuration['default_registration_state'] = $form_state->getValue([
      'workflow_settings',
      'default_registration_state',
    ]);
    $this->workflowType->setConfiguration($configuration);
  }

}
