<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\node\Entity\Node;
use Drupal\registration\Entity\Registration;

/**
 * Tests the Host Entity class.
 *
 * @coversDefaultClass \Drupal\registration\HostEntity
 *
 * @group registration
 */
class HostEntityTest extends RegistrationKernelTestBase {

  /**
   * @covers ::bundle
   * @covers ::getEntity
   * @covers ::getEntityTypeId
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
   * @covers ::isUserRegistered
   * @covers ::isBeforeOpen
   * @covers ::isAfterClose
   */
  public function testHostEntity() {
    $node = Node::create([
      'type' => 'event',
      'title' => 'My event',
      'event_registration' => 'conference',
    ]);
    $node->save();
    $node = $this->reloadEntity($node);

    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
      'anon_mail' => 'test@example.com',
    ]);
    $registration->save();
    $host_entity = $registration->getHostEntity();

    $this->assertEquals($node->bundle(), $host_entity->bundle());
    $this->assertEquals($node, $host_entity->getEntity());
    $this->assertEquals($node->getEntityTypeId(), $host_entity->getEntityTypeId());
    $this->assertEquals($node->id(), $host_entity->id());
    $this->assertFalse($host_entity->isNew());
    $this->assertEquals('My event', $host_entity->label());

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

    $this->assertTrue($host_entity->isEmailRegistered('test@example.com'));
    $this->assertFalse($host_entity->isEmailRegistered('test2@example.com'));

    $user = $this->createUser([], ['administer registration']);
    $this->assertFalse($host_entity->isUserRegistered($user));
    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
      'user_uid' => $user->id(),
    ]);
    $registration->save();
    $this->assertTrue($host_entity->isUserRegistered($user));

    // Out of room.
    $this->assertFalse($host_entity->isEnabledForRegistration());

    // Add more capacity.
    $settings = $host_entity->getSettings();
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Before open and after close.
    $this->assertFalse($host_entity->isBeforeOpen());
    $this->assertFalse($host_entity->isAfterClose());
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isBeforeOpen());
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $this->assertTrue($host_entity->isAfterClose());
    $this->assertFalse($host_entity->isEnabledForRegistration());

    $settings->set('open', NULL);
    $settings->set('close', NULL);
    $settings->save();
    $this->assertTrue($host_entity->isEnabledForRegistration());

    $settings->set('status', FALSE);
    $settings->save();
    $this->assertFalse($host_entity->isEnabledForRegistration());
  }

}
