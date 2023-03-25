<?php

namespace Drupal\Tests\registration\Kernel\Event;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the data alter events.
 *
 * @coversDefaultClass \Drupal\registration\Event\RegistrationDataAlterEvent
 *
 * @group registration
 */
class RegistrationDataAlterEventTest extends EventTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::getContext
   * @covers ::getData
   * @covers ::setData
   */
  public function testRegistrationDataAlterEvent() {
    /* @see \Drupal\registration_test_event\EventSubscriber\RegistrationDataAlterEventSubscriber */

    // Start out with a capacity of 5 from the registration_test module.
    // The following registration results in 1 space reserved, one recipient
    // and 4 spaces remaining. However, the event subscriber does the following:
    // - adds 1 email to the recipient list.
    // - adds 1 to the registration count.
    // - sets the active spaces reserved to 3.
    // - subtracts 1 from the spaces remaining.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $recipients = $this->container->get('registration.notifier')->getRecipientList($host_entity);
    $this->assertCount(2, $recipients);
    $this->assertEquals(2, $host_entity->getRegistrationCount());
    $this->assertEquals(3, $host_entity->getActiveSpacesReserved());
    // Capacity of 5 minus 3 spaces minus 1 from the subscriber.
    $this->assertEquals(1, $host_entity->getSpacesRemaining());

    // The subscriber disables registration for node 2.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $violations = $registration->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('Registration for <em class="placeholder">My event</em> is disabled.', (string) $violations[0]->getMessage());

    // Node 3 is enabled.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $violations = $registration->validate();
    $this->assertCount(0, $violations);

    // The subscriber enables registration for node 4, even if it otherwise
    // wouldn't be.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 6);
    $violations = $registration->validate();
    $this->assertCount(0, $violations);

    // Node 5 is enabled but the count is too high and capacity would be
    // exceeded.
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 6);
    $violations = $registration->validate();
    $this->assertCount(2, $violations);
    $this->assertEquals('You may not register for more than 2 spaces.', (string) $violations[0]->getMessage());
    $this->assertEquals('Sorry, unable to register for <em class="placeholder">My event</em> due to: insufficient spaces remaining.', (string) $violations[1]->getMessage());
  }

}
