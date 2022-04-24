<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_link' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_link",
 *   label = @Translation("Registration link"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationLinkFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationLinkFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    if (isset($items, $items[0])) {
      $id = $items[0]->getValue()['registration_type'];
      if ($id) {
        $registration_type = $this->entityTypeManager->getStorage('registration_type')->load($id);
        if ($registration_type) {
          if ($host_entity = $items->getEntity()) {
            $entity_type_id = $host_entity->getEntityTypeId();
            $url = Url::fromRoute("entity.$entity_type_id.register", [
              $entity_type_id => $host_entity->id(),
            ]);
            $elements[] = [
              '#markup' => Link::fromTextAndUrl($registration_type->label(), $url)->toString(),
            ];
          }
        }
      }
    }
    return $elements;
  }

}
