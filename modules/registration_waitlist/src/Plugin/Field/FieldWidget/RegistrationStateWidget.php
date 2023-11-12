<?php

namespace Drupal\registration_waitlist\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Plugin\Field\FieldWidget\RegistrationStateWidget as BaseRegistrationStateWidget;

/**
 * Extends the implementation of the 'registration_state_default' widget.
 */
class RegistrationStateWidget extends BaseRegistrationStateWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $entity = $items->getEntity();

    // The state field for a registration.
    if ($entity->getEntityTypeId() == 'registration') {
      return parent::formElement($items, $delta, $element, $form, $form_state);
    }

    /** @var \Drupal\registration\Entity\RegistrationSettings $entity */
    return [
      '#type' => 'select',
      '#title' => $this->t('Autofill state'),
      '#description' => $this->t('The state that wait listed registrations should be placed in when slots become available.'),
      '#options' => $this->getSettingsStateOptions($entity),
      '#default_value' => $this->getSettingsDefaultValue($entity),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return parent::isApplicable($field_definition) || ($field_definition->getName() === 'registration_waitlist_autofill_state' && $field_definition->getTargetEntityTypeId() === 'registration_settings');
  }

  /**
   * Gets the autofill state options for registration settings.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $entity
   *   The registration settings entity.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   */
  protected function getSettingsStateOptions(RegistrationSettings $entity): array {
    $options = [];
    if ($host_entity = $entity->getHostEntity()) {
      if ($registration_type = $host_entity->getRegistrationType()) {
        $workflow = $registration_type->getWorkflow();
        $states = $workflow ? $workflow->getTypePlugin()->getStates() : [];
        foreach ($states as $id => $state) {
          /** @var \Drupal\registration\RegistrationState $state */
          if (!$state->isCanceled() && ($id != 'waitlist')) {
            $options[$id] = $state->label();
          }
        }
      }
    }
    return $options;
  }

  /**
   * Gets the autofill state default value for registration settings.
   *
   * @param \Drupal\registration\Entity\RegistrationSettings $entity
   *   The registration settings entity.
   *
   * @return string|null
   *   The default value, if available.
   */
  protected function getSettingsDefaultValue(RegistrationSettings $entity): ?string {
    $default_value = NULL;

    // Default to the previously set value, if available.
    if (!$entity->get('registration_waitlist_autofill_state')->isEmpty()) {
      $default_value = $entity->get('registration_waitlist_autofill_state')->first()->getValue()['value'];
    }
    // If the value is not available, default to the complete state if that
    // state exists, or the default state otherwise.
    elseif ($host_entity = $entity->getHostEntity()) {
      if ($registration_type = $host_entity->getRegistrationType()) {
        if ($workflow = $registration_type->getWorkflow()->getTypePlugin()) {
          if ($workflow->hasState('complete')) {
            $default_value = 'complete';
          }
          else {
            $default_value = $registration_type->getDefaultState();
          }
        }
      }
    }

    return $default_value;
  }

}
