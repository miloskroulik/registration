<?php

namespace Drupal\Tests\registration\Kernel\Event;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base test for kernel event tests.
 */
abstract class EventTestBase extends RegistrationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['registration_test_event'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

}
