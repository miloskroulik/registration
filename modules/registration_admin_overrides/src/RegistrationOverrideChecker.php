<?php

namespace Drupal\registration_admin_overrides;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the class for the registration override checker service.
 */
class RegistrationOverrideChecker implements RegistrationOverrideCheckerInterface {

  /**
   * {@inheritdoc}
   */
  public function accountCanOverride(?HostEntityInterface $host_entity, AccountInterface $account, string $setting, ?RegistrationInterface $registration = NULL): bool {
    if ($host_entity) {
      $type = $host_entity->getRegistrationTypeBundle();
      $admin = $account->hasPermission("administer registration") || $account->hasPermission("administer $type registration");
      if ($registration) {
        $admin = $registration->access('administer', $account);
      }
      if ($admin && $account->hasPermission('registration override ' . $setting)) {
        return (bool) $host_entity->getRegistrationType()->getThirdPartySetting('registration_admin_overrides', $setting);
      }
    }
    return FALSE;
  }

}
