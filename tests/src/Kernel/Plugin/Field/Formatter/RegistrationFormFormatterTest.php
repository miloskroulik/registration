<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Field\Formatter;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Tests\registration\Kernel\CurrentRouteMatch;
use Drupal\Tests\registration\Traits\NodeCreationTrait;

/**
 * Tests the registration_form formatter.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Field\FieldFormatter\RegistrationFormFormatter
 *
 * @group registration
 */
class RegistrationFormFormatterTest extends FormatterTestBase implements ServiceModifierInterface {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Set up an override that returns the route for node/1. The route is
    // needed during form rendering.
    $service_definition = $container->getDefinition('current_route_match');
    $service_definition->setClass(CurrentRouteMatch::class);
  }

  /**
   * @covers ::render
   */
  public function testRegistrationFormFormatter() {
    $node = $this->createAndSaveNode();
    $build = $node->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
    ]);
    $output = $this->renderPlain($build);
    $this->assertStringContainsString('<form class="registration-conference-register-form', $output);

    // Disable registration.
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();

    $build = $node->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
    ]);
    $output = $this->renderPlain($build);
    $this->assertEmpty($output);

    $build = $node->get('event_registration')->view([
      'type' => 'registration_form',
      'label' => 'hidden',
      'settings' => [
        'show_reason' => TRUE,
      ],
    ]);
    $output = $this->renderPlain($build);
    $this->assertEquals('Registration is not available: Not open yet.', $output);
  }

}
