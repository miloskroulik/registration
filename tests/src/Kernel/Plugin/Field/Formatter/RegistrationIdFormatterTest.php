<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreateTrait;
use Drupal\Tests\registration\Traits\RegistrationCreateTrait;

/**
 * Tests the registration_id formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationIdFormatter
 *
 * @group registration
 */
class RegistrationIdFormatterTest extends FormatterTestBase {

  use NodeCreateTrait;
  use RegistrationCreateTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationIdFormatter() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $build = $registration->get('registration_id')->view([
      'type' => 'registration_id',
      'label' => 'hidden',
    ]);
    $output = $this->renderPlain($build);
    $this->assertEquals('<a href="/registration/1" hreflang="en">1</a>', $output);
  }

}
