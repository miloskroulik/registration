<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Tests\registration\Traits\NodeCreationTrait;

/**
 * Tests the registration_link formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationLinkFormatter
 *
 * @group registration
 */
class RegistrationLinkFormatterTest extends FormatterTestBase {

  use NodeCreationTrait;

  /**
   * @covers ::render
   */
  public function testRegistrationLinkFormatter() {
    $node = $this->createAndSaveNode();

    // Default settings.
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
    ]);
    $output = $this->renderPlain($build);
    $this->assertEquals('<a href="/node/1/register">Conference</a>', $output);

    // Custom link label.
    $build = $node->get('event_registration')->view([
      'type' => 'registration_link',
      'label' => 'hidden',
      'settings' => [
        'label' => 'Register now',
      ],
    ]);
    $output = $this->renderPlain($build);
    $this->assertEquals('<a href="/node/1/register">Register now</a>', $output);
  }

}
