<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_type' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationTypeFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationTypeFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
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
      if (isset($items, $items[0])) {
        if ($id = $items[0]->getValue()['registration_type']) {
          $registration_type = $this->entityTypeManager
            ->getStorage('registration_type')
            ->load($id);
          if ($registration_type) {
            $cache_entities[] = $registration_type;
            $elements[] = [
              '#markup' => $registration_type->label(),
            ];
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
