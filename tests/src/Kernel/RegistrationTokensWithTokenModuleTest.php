<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\Utility\Token;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration tokens with the token module enabled.
 *
 * @group registration
 */
class RegistrationTokensWithTokenModuleTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The token service.
   */
  protected Token $tokenService;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    $this->tokenService = $this->container->get('token');
  }

  /**
   * Tests registration tokens with fields added by the token module.
   */
  public function testRegistrationTokensWithFields() {
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.org');
    $registration->save();

    // Simple tokens.
    $test_data = [
      '[registration:id]' => '1',
      '[registration:registration_id:value]' => '1',
      '[registration:count]' => '1',
      '[registration:count:value]' => '1',
      '[registration:label]' => 'Registration #1 for My event',
      '[registration:mail]' => 'test@example.org',
      '[registration:mail:value]' => 'test@example.org',
      '[registration:state]' => 'Pending',
      '[registration:type]' => 'conference',
      '[registration:type-name]' => 'Conference',
    ];

    $token_data = [
      'registration' => $registration,
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }

    // Chained host entity tokens.
    $test_data = [
      '[registration:entity]' => 'My event',
      '[registration:entity:nid]' => '1',
      '[registration:entity:title]' => 'My event',
      '[registration:entity:type]' => 'event',
      '[registration:entity:type-name]' => 'Event',
    ];

    foreach ($test_data as $token => $expected_value) {
      $token_replaced = $this->tokenService->replace($token, $token_data);
      $this->assertEquals($expected_value, $token_replaced);
    }
  }

}
