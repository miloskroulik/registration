<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the Host Entity class.
 *
 * @coversDefaultClass \Drupal\registration\HostEntity
 *
 * @group registration
 */
class RegistrationWaitListHostEntityTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::getWaitListSpacesReserved
   * @covers ::hasRoomOffWaitList
   * @covers ::hasRoomOnWaitList
   * @covers ::isWaitListEnabled
   */
  public function testWaitListHostEntity() {
    $node = $this->createAndSaveNode();

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 5);
    $registration->save();
    $host_entity = $registration->getHostEntity();

    // Wait list is enabled but no spaces taken yet.
    $this->assertTrue($host_entity->isWaitListEnabled());
    $this->assertEquals(0, $host_entity->getWaitListSpacesReserved());

    // There is room somewhere for a registration.
    $this->assertTrue($host_entity->hasRoom());
    // There is no room off the wait list.
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    // There is room on the wait list.
    $this->assertTrue($host_entity->hasRoomOnWaitList());
    $this->assertTrue($host_entity->isEnabledForRegistration());

    // Wait list spaces reserved.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->save();
    $this->assertEquals(3, $host_entity->getWaitListSpacesReserved());

    // Disable the wait list.
    $settings = $host_entity->getSettings();
    $settings->set('registration_waitlist_enable', FALSE);
    $settings->save();
    $this->assertFalse($host_entity->hasRoom());
    $this->assertFalse($host_entity->hasRoomOnWaitList());
    $this->assertFalse($host_entity->isEnabledForRegistration());
  }

}
