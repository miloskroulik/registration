<?php

namespace Drupal\registration\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present the host entity for a registration.
 *
 * @ViewsField("registration_host_entity")
 */
class HostEntity extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity($values);

    if ($registration instanceof RegistrationInterface) {
      if ($host_entity = $registration->getHostEntity()) {
        $entity = $host_entity->getEntity();
        try {
          return [
            '#markup' => Link::fromTextAndUrl($entity->label(), $entity->toUrl())->toString(),
          ];
        }
        catch (\Exception) {
          // The toUrl function can throw an exception if the entity is
          // malformed. Catching the exception allows the listing to render
          // for other rows and host entities.
        }
      }
      // The entity does not exist and was likely deleted. Give some details.
      else {
        return [
          '#markup' => new TranslatableMarkup('@type @id (deleted)', [
            '@type' => $registration->getHostEntityTypeId(),
            '@id' => $registration->getHostEntityId(),
          ]),
        ];
      }
    }

    return NULL;
  }

}
