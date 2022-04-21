<?php

namespace Drupal\registration\Plugin\views\field;

use Drupal\Core\Link;
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
   * @{inheritdoc}
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
        return [
          '#markup' => Link::fromTextAndUrl($host_entity->label(), $host_entity->toUrl())->toString(),
        ];
      }
    }

    return NULL;
  }

}
