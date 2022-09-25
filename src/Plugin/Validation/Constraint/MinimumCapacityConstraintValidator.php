<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MinimumCapacityConstraint constraint.
 */
class MinimumCapacityConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the MinimumCapacityConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MinimumCapacityConstraintValidator {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($settings, Constraint $constraint) {
    if (($settings instanceof RegistrationSettings) && $settings->getSetting('capacity')) {
      $entity_type_id = $settings->getHostEntityTypeId();
      $entity_id = $settings->getHostEntityId();
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      if ($entity = $storage->load($entity_id)) {
        $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
        $host_entity = $handler->createHostEntity($entity);
        if ($settings->getSetting('capacity') < $host_entity->getActiveSpacesReserved()) {
          $this->context->addViolation($constraint->message, [
            '@type' => $host_entity->getRegistrationTypeBundle(),
            '@capacity' => $host_entity->getActiveSpacesReserved(),
          ]);
        }
      }
    }
  }

}
