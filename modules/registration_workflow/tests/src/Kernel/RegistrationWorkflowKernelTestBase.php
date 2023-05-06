<?php

namespace Drupal\Tests\registration_workflow\Kernel;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;

/**
 * Provides a base class for Registration Workflow kernel tests.
 */
abstract class RegistrationWorkflowKernelTestBase extends RegistrationKernelTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'registration_waitlist',
    'registration_workflow',
  ];

}
