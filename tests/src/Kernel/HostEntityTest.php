<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\HostEntity;
use Drupal\registration\RegistrationHostEntityHandler;

/**
 * Tests the Host Entity class.
 *
 * @coversDefaultClass \Drupal\registration\HostEntity
 *
 * @group registration
 */
class HostEntityTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::bundle
   * @covers ::getEntity
   * @covers ::getEntityTypeId
   * @covers ::getEntityTypeLabel
   * @covers ::id
   * @covers ::isNew
   * @covers ::label
   * @covers ::createRegistration
   * @covers ::generateSampleRegistration
   * @covers ::getActiveSpacesReserved
   * @covers ::getSpacesRemaining
   * @covers ::getDefaultSettings
   * @covers ::getRegistrationCount
   * @covers ::getRegistrationField
   * @covers ::getRegistrationList
   * @covers ::getRegistrationTypeBundle
   * @covers ::hasRoom
   * @covers ::isConfiguredForRegistration
   * @covers ::isEnabledForRegistration
   * @covers ::isEmailRegistered
   * @covers ::isEmailRegisteredInStates
   * @covers ::isUserRegistered
   * @covers ::isUserRegisteredInStates
   * @covers ::isRegistrant
   * @covers ::isBeforeOpen
   * @covers ::isAfterClose
   */
  public function testHostEntity() {
    $node = $this->createAndSaveNode();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->reloadEntity($node);

    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'test@example.com');
    $registration->save();

    $host_entity = $registration->getHostEntity();

    $this->assertEquals($node->bundle(), $host_entity->bundle());
    $this->assertEquals($node, $host_entity->getEntity());
    $this->assertEquals($node->getEntityTypeId(), $host_entity->getEntityTypeId());
    $this->assertEquals('Event', $host_entity->getEntityTypeLabel());
    $this->assertEquals($node->id(), $host_entity->id());
    $this->assertFalse($host_entity->isNew());
    $this->assertEquals('My event', $host_entity->label());
    $this->assertTrue($host_entity->isConfiguredForRegistration());

    $new_registration = $host_entity->createRegistration();
    $this->assertEquals($new_registration->getType()->id(), $host_entity->getRegistrationTypeBundle());
    $this->assertEquals($new_registration->getHostEntity()->getEntityTypeId(), $host_entity->getEntityTypeId());
    $this->assertEquals($new_registration->getHostEntity()->id(), $host_entity->id());

    $sample_registration = $host_entity->generateSampleRegistration();
    $this->assertEquals($sample_registration->getType()->id(), $host_entity->getRegistrationTypeBundle());
    $this->assertEquals($sample_registration->getHostEntity()->getEntityTypeId(), $host_entity->getEntityTypeId());
    $this->assertEquals($sample_registration->getHostEntity()->id(), $host_entity->id());

    // Spaces reserved only counts saved registrations.
    $this->assertEquals(1, $host_entity->getActiveSpacesReserved());
    $new_registration->save();
    $sample_registration->save();
    $this->assertEquals(3, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(2, $host_entity->getSpacesRemaining());

    // Exclude a registration from spaces reserved and remaining.
    $this->assertEquals(2, $host_entity->getActiveSpacesReserved($new_registration));
    $this->assertEquals(3, $host_entity->getSpacesRemaining($new_registration));

    // Count registrations vs. spaces.
    $this->assertEquals(3, $host_entity->getRegistrationCount());
    $new_registration->set('count', 2);
    $new_registration->save();
    $this->assertEquals(4, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(1, $host_entity->getSpacesRemaining());
    $this->assertEquals(3, $host_entity->getRegistrationCount());

    // The default settings are defined in the registration_test module,
    // and were arbitrarily set to a capacity of 5 registrations and
    // a limit of 2 spaces per registration.
    // @see registration_test_entity_base_field_info()
    $settings = $host_entity->getDefaultSettings();
    $this->assertTrue($settings['status']);
    $this->assertEquals(5, $settings['capacity']);
    $this->assertEquals(2, $settings['maximum_spaces']);

    $this->assertEquals('event_registration', $host_entity->getRegistrationField()->getName());
    $registration_list = $host_entity->getRegistrationList();
    $this->assertCount(3, $registration_list);

    // Four spaces are reserved and 1 space is remaining.
    $this->assertTrue($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoom(2));
    // An existing registration with two spaces can be saved with one more.
    $this->assertTrue($host_entity->hasRoom(3, $new_registration));
    // A registration with one space cannot be saved requesting three spaces.
    $this->assertFalse($host_entity->hasRoom(3, $sample_registration));

    // An email address has registered.
    $this->assertTrue($host_entity->isEmailRegistered('test@example.com'));
    $this->assertTrue($host_entity->isRegistrant(NULL, 'test@example.com'));
    // An email address has not registered.
    $this->assertFalse($host_entity->isEmailRegistered('test2@example.com'));
    $this->assertFalse($host_entity->isRegistrant(NULL, 'test2@example.com'));

    // Check email against specific registration states.
    $states = ['held', 'complete'];
    $this->assertFalse($host_entity->isEmailRegisteredInStates('test@example.com', $states));
    $this->assertFalse($host_entity->isRegistrant(NULL, 'test@example.com', $states));
    $states = ['pending'];
    $this->assertTrue($host_entity->isEmailRegisteredInStates('test@example.com', $states));
    $this->assertTrue($host_entity->isRegistrant(NULL, 'test@example.com', $states));
    // Check against empty states.
    $states = [];
    $this->assertFalse($host_entity->isEmailRegisteredInStates('test@example.com', $states));

    // A user has not registered yet.
    $user = $this->createUser(['administer registration']);
    $this->assertFalse($host_entity->isUserRegistered($user));
    $this->assertFalse($host_entity->isRegistrant($user));
    $this->assertFalse($host_entity->isRegistrant(NULL, $user->getEmail()));
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $user->id());
    $registration->save();
    // A user has registered.
    $this->assertTrue($host_entity->isUserRegistered($user));
    $this->assertTrue($host_entity->isRegistrant($user));
    $this->assertTrue($host_entity->isRegistrant(NULL, $user->getEmail()));

    // Check user against specific registration states.
    $states = ['held', 'complete'];
    $this->assertFalse($host_entity->isUserRegisteredInStates($user, $states));
    $this->assertFalse($host_entity->isRegistrant($user, NULL, $states));
    $this->assertFalse($host_entity->isRegistrant(NULL, $user->getEmail(), $states));
    $states = ['pending'];
    $this->assertTrue($host_entity->isUserRegisteredInStates($user, $states));
    $this->assertTrue($host_entity->isRegistrant($user, NULL, $states));
    $this->assertTrue($host_entity->isRegistrant(NULL, $user->getEmail(), $states));
    // Check against empty states.
    $states = [];
    $this->assertFalse($host_entity->isUserRegisteredInStates($user, $states));

    // Out of room.
    $this->assertFalse($host_entity->isEnabledForRegistration());

    // Add more capacity.
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Reached capacity.
    $settings->set('capacity', 5);
    $settings->save();
    $this->assertFalse($host_entity->isEnabledForRegistration());

    // Unlimited capacity.
    $settings->set('capacity', 0);
    $settings->save();
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Before open and after close.
    $this->assertFalse($host_entity->isBeforeOpen());
    $this->assertFalse($host_entity->isAfterClose());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());
    $this->assertFalse($host_entity->isAfterClose());
    $this->assertFalse($host_entity->isEnabledForRegistration());
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertFalse($host_entity->isBeforeOpen());
    $this->assertTrue($host_entity->isAfterClose());
    $this->assertFalse($host_entity->isEnabledForRegistration());

    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Disable registration.
    $settings->set('status', FALSE);
    $settings->save();
    $this->assertFalse($host_entity->isEnabledForRegistration());

    // Not configured for registration.
    $node = $this->createNode();
    $node->set('event_registration', NULL);
    $node->save();
    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $host_entity = $handler->createHostEntity($node);
    $this->assertFalse($host_entity->isConfiguredForRegistration());
  }

  /**
   * Test deprecation of 'host_entity' handler on registration entity.
   *
   * @group legacy
   */
  public function testHostHandlerDeprecation(): void {
    $node = $this->createAndSaveNode();

    $handler = $this->entityTypeManager->getHandler('node', 'registration_host_entity');
    $this->assertInstanceOf(RegistrationHostEntityHandler::class, $handler);
    $host_entity = $handler->createHostEntity($node);
    $this->assertInstanceOf(HostEntity::class, $host_entity);

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $this->assertInstanceOf(RegistrationHostEntityHandler::class, $handler);
    $this->expectDeprecation('Using the host_entity handler of the registration entity type is deprecated in registration:3.1.5 and is removed from registration:4.0.0. Use the registration_host_entity handler for the host entity type instead. See https://www.drupal.org/node/3462126');
    $host_entity = $handler->createHostEntity($node);
    $this->assertInstanceOf(HostEntity::class, $host_entity);
  }

}
