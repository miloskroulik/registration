<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionListenerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates registration fields.
 */
class RegistrationFieldConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The field definition listener.
   *
   * @var \Drupal\Core\Field\FieldDefinitionListenerInterface
   */
  protected FieldDefinitionListenerInterface $fieldDefinitionListener;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs a new RegistrationFieldConstraintValidator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\FieldDefinitionListenerInterface $field_definition_listener
   *   The entity field manager.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FieldDefinitionListenerInterface $field_definition_listener, RegistrationManagerInterface $registration_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldDefinitionListener = $field_definition_listener;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('field_definition.listener'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value instanceof FieldConfig) {
      $field_config = $value;
      if ($field_config->getType() == 'registration') {
        // A registration field is being added.
        $bundle = $field_config->get('bundle');
        $entity_type_id = $field_config->get('entity_type');
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        if ($this->registrationManager->hasRegistrationField($entity_type, $bundle)) {
          // Prevent adding a second registration field to the same bundle.
          $this->context->addViolation($constraint->disallowedCardinalityMessage);

          // Unfortunately Field UI has already updated the field map,
          // so that needs to be reversed. Otherwise the field is still
          // associated with the entity type and bundle, and any cache
          // rebuilds will throw "non-existent config" errors in the log.
          // Therefore back out the field definition using its listener.
          // @see https://www.drupal.org/project/drupal/issues/2916266
          $this->fieldDefinitionListener->onFieldDefinitionDelete($field_config);

          // Also remove the storage if it no longer has any fields.
          $storage_definition = $field_config->getFieldStorageDefinition();
          if ($storage_definition->isDeletable()) {
            $storage_definition->delete();
          }
        }
      }
    }
    elseif ($value instanceof FieldStorageConfig) {
      $field_config = $value;
      if ($field_config->getType() == 'registration') {
        // Field storage is being created for a registration field.
        $entity_type_id = $field_config->get('entity_type');
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        if ($entity_type_id == 'registration') {
          // Cannot add registration field to itself.
          $this->context->addViolation($constraint->disallowedTargetMessage);
        }
        if (!$entity_type->getKey('id')) {
          // The entity type must have an "id" key, which is standard.
          $this->context->addViolation($constraint->missingIdKeyMessage);
        }
      }
    }
  }

}
