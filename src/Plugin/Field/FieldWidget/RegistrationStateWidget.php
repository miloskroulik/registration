<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\Entity\RegistrationInterface;

/**
 * Plugin implementation of the 'registration_state_default' widget.
 *
 * @FieldWidget(
 *   id = "registration_state_default",
 *   label = @Translation("Registration state"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class RegistrationStateWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $entity = $items->getEntity();

    return [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $this->getStateOptions($entity),
      '#default_value' => $entity->getState()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    if ($key_exists) {
      // Assign the values and remove the empty ones.
      $items->setValue($values);
      $items->filterEmptyItems();

      // Put delta mapping in $form_state, so that flagErrors() can use it.
      $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
        unset($item->_original_delta, $item->_weight);
      }
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'state' && $field_definition->getTargetEntityTypeId() === 'registration';
  }

  /**
   * Gets the available registration state options.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $entity
   *   The registration entity.
   *
   * @return array
   *   The states as an options array of labels keyed by ID.
   */
  protected function getStateOptions(RegistrationInterface $entity): array {
    $options = [];
    $workflow = $entity->type->entity->getWorkflow();
    $states = $workflow ? $workflow->getTypePlugin()->getStates() : [];
    foreach ($states as $id => $state) {
      $options[$id] = $state->label();
    }
    return $options;
  }

}
