<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_form' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_form",
 *   label = @Translation("Registration form"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationFormFormatter extends FormatterBase {

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationFormFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFormBuilder = $container->get('entity.form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $cache_entities = [];
    if ($entity = $items->getEntity()) {
      /** @var \Drupal\registration\HostEntityInterface $host_entity */
      $host_entity = $this->entityTypeManager
        ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
        ->createHostEntity($entity, $langcode);
      $settings = $host_entity->getSettings();
      $cache_entities[] = $settings;
      if (isset($items[0])) {
        if ($id = $items[0]->getValue()['registration_type']) {
          $registration_type = $this->entityTypeManager->getStorage('registration_type')->load($id);
          if ($registration_type) {
            $cache_entities[] = $registration_type;
            if ($host_entity->isEnabledForRegistration()) {
              $registration = $this->entityTypeManager->getStorage('registration')->create([
                'entity_type_id' => $host_entity->getEntityTypeId(),
                'entity_id' => $host_entity->id(),
                'type' => $registration_type->id(),
              ]);
              // Add the host entity to the form state.
              $elements[] = $this->entityFormBuilder->getForm($registration, 'register', [
                'host_entity' => $host_entity,
              ]);
            }
          }
        }
      }
      $host_entity->addCacheableDependencies(
        $elements,
        $cache_entities
      );
    }
    return $elements;
  }

}
