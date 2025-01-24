<?php

namespace Drupal\registration_change_host\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a local task plugin for changing registration host.
 */
class ChangeHostTask extends LocalTaskDefault {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL, ?RegistrationInterface $registration = NULL): ?string {
    if ($registration) {
      $host_type_label = $registration->getHostEntityTypeLabel();
      return (string) $this->t('Change @host_type_label', ['@host_type_label' => $host_type_label]);
    }
    return NULL;
  }

}
