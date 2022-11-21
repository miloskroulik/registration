<?php

namespace Drupal\registration\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Define the RegistrationCancelForm class.
 */
class RegistrationCancelForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel registration @id?', [
      '@id' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    return $entity->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Yes, I want to cancel the registration');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('No, I do not want to cancel the registration');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $violations = $this->entity->validate();
    $violations->filterByFieldAccess($this->currentUser());
    foreach ($violations as $violation) {
      $field_name[] = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setErrorByName($field_name, $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if ($state = $this->getCanceledState()) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $this->entity */
      $this->entity->set('state', $state);
      $this->entity->save();
      $this->messenger()->addMessage($this->t('The registration has been canceled.'));
    }
  }

  /**
   * Gets the ID of the canceled state in the registration workflow.
   *
   * @return string|null
   *   The ID of the canceled state, if available.
   */
  protected function getCanceledState(): ?string {
    /** @var \Drupal\registration\Entity\RegistrationInterface $this->entity */
    if ($workflow = $this->entity->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isCanceled()) {
          return $state->id();
        }
      }
    }

    return NULL;
  }

}
