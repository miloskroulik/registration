<?php

namespace Drupal\Tests\registration\Kernel\Plugin\Validation\Constraint;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreateTrait;
use Drupal\Tests\registration\Traits\RegistrationCreateTrait;

/**
 * Tests the Registration constraint.
 *
 * @coversDefaultClass \Drupal\registration\Plugin\Validation\Constraint\RegistrationConstraint
 *
 * @group registration
 */
class RegistrationConstraintTest extends RegistrationKernelTestBase {

  use NodeCreateTrait;
  use RegistrationCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);
  }

  /**
   * @covers ::validate
   */
  public function testRegistrationConstraint() {
    // Host entity not configured for registration.
    $node = $this->createNode();
    $node->set('event_registration', NULL);
    $node->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Missing host entity.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('entity_id', 999);
    $violations = $registration->validate();
    $this->assertEquals('Missing host entity.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    // Exceeds maximum spaces.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    // Capacity 5 and max 2 spaces per registration are set in the
    // registraiton_test module.
    $registration->set('count', 5);
    $violations = $registration->validate();
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Add registrations for subsequent assertions.
    $registration->set('count', 2);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->save();
    $registration = $this->createAndSaveRegistration($node);

    // No room.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $violations = $registration->validate();
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('count', 1);
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Before open.
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $registration->set('count', 1);
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is not open yet.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('open', '2020-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // After close.
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is closed.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $settings->set('close', '2220-01-01T00:00:00');
    $settings->save();
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // Remove open and close dates.
    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();

    // Email already registered.
    $registration->set('anon_mail', 'test@example.com');
    $violations = $registration->validate();
    $this->assertEquals('<em class="placeholder">test@example.com</em> is already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $registration->set('anon_mail', 'test2@example.com');
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());

    // User already registered.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', 1);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('user_uid', 1);
    $violations = $registration->validate();
    $this->assertEquals('You are already registered for this event.', (string) $violations[0]->getMessage());
    $this->assertEquals(1, $violations->count());

    $user = $this->createUser();
    $registration->set('user_uid', $user->id());
    $violations = $registration->validate();
    $this->assertEquals(0, $violations->count());
  }

}
