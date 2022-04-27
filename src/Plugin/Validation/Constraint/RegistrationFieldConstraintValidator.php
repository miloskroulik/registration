<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RegistrationManagerInterface $registration_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
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
          // Must be cardinality 1. Prevent adding a second.
          $this->context->addViolation($constraint->disallowedCardinalityMessage);
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
