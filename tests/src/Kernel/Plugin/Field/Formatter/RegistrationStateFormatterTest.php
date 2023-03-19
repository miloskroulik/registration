<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreateTrait;
use Drupal\Tests\registration\Traits\RegistrationCreateTrait;

/**
 * Tests the registration_state formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationStateFormatter
 *
 * @group registration
 */
class RegistrationStateFormatterTest extends FormatterTestBase {

  use NodeCreateTrait;
  use RegistrationCreateTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationStateFormatter() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $build = $registration->get('state')->view([
      'type' => 'registration_state',
      'label' => 'hidden',
    ]);
    $output = $this->renderPlain($build);
    $this->assertEquals('Pending', $output);
  }

}
