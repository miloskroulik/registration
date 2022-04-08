<?php

namespace Drupal\registration\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\RegistrationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for entity types with attached registration fields.
 */
class RegistrationLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration service.
   *
   * @var \Drupal\registration\RegistrationServiceInterface
   */
  protected RegistrationServiceInterface $registration;

  /**
   * Creates a RegistrationLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\registration\RegistrationServiceInterface $registration_service
   *   The registration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RegistrationServiceInterface $registration_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->registration = $registration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): RegistrationLocalTask {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('registration.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->registration->getManageRoute($entity_type)) {
        $this->derivatives["$entity_type_id.manage_registrations"] = [
          'route_name' => "entity.$entity_type_id.manage_registrations",
          'title' => $this->t('Manage Registrations'),
          'base_route' => $this->registration->getBaseRouteName($entity_type),
          'weight' => 50,
        ];
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
