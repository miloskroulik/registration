<?php

namespace Drupal\registration_admin_overrides;

use Drupal\Core\Session\AccountInterface;
use Drupal\registration\HostEntityInterface;

/**
 * Defines the interface for the registration override checker service.
 */
interface RegistrationOverrideCheckerInterface {

  /**
   * Determines if the specified account can override a registration setting.
   *
   * @param \Drupal\registration\HostEntityInterface|null $host_entity
   *   The host entity, if available.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $setting
   *   The name of the registration setting, for example 'capacity'.
   *
   * @return bool
   *   TRUE if the account can override the setting, FALSE otherwise.
   */
  public function accountCanOverride(?HostEntityInterface $host_entity, AccountInterface $account, string $setting): bool;

}
