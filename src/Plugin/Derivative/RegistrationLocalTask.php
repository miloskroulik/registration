<?php

namespace Drupal\registration\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\RegistrationManagerInterface;
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
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a RegistrationLocalTask object.
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
  public static function create(ContainerInterface $container, $base_plugin_id): RegistrationLocalTask {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('registration.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->registrationManager->getRoute($entity_type, 'manage')) {
        $this->derivatives["$entity_type_id.manage_registrations"] = [
          'route_name' => "entity.$entity_type_id.manage_registrations",
          'title' => $this->t('Manage Registrations'),
          'base_route' => $this->registrationManager->getBaseRouteName($entity_type),
          'weight' => 50,
        ];
        $this->derivatives["$entity_type_id.manage_registrations_sub"] = [
          'route_name' => "entity.$entity_type_id.manage_registrations",
          'title' => $this->t('Registrations'),
          'parent_id' => "registration.entities:$entity_type_id.manage_registrations",
        ];
        $this->derivatives["$entity_type_id.registration_settings"] = [
          'route_name' => "entity.$entity_type_id.registration_settings",
          'title' => $this->t('Settings'),
          'parent_id' => "registration.entities:$entity_type_id.manage_registrations",
          'weight' => 10,
        ];
        $this->derivatives["$entity_type_id.broadcast"] = [
          'route_name' => "entity.$entity_type_id.broadcast",
          'title' => $this->t('Email registrants'),
          'parent_id' => "registration.entities:$entity_type_id.manage_registrations",
          'weight' => 20,
        ];
      }
      if ($this->registrationManager->getRoute($entity_type, 'register')) {
        if (!$this->registrationManager->getFieldConfigSetting($entity_type, 'hide_register_tab')) {
          $this->derivatives["$entity_type_id.register"] = [
            'route_name' => "entity.$entity_type_id.register",
            'title' => $this->t('Register'),
            'base_route' => $this->registrationManager->getBaseRouteName($entity_type),
            'weight' => 50,
          ];
        }
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
