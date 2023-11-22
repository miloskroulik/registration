<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\Core\Database\Database;
use Drupal\registration_waitlist\RegistrationWaitListManagerInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests the RegistrationWaitListManager class.
 *
 * @coversDefaultClass \Drupal\registration_waitlist\RegistrationWaitListManager
 *
 * @group registration
 */
class RegistrationWaitListManagerTest extends RegistrationWaitListKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The registration wait list manager.
   *
   * @var \Drupal\registration_waitlist\RegistrationWaitListManagerInterface
   */
  protected RegistrationWaitListManagerInterface $waitListManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->waitListManager = $this->container->get('registration_waitlist.manager');
  }

  /**
   * @covers ::autoFill
   */
  public function testRegistrationWaitListManager() {
    $node = $this->createAndSaveNode();
    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($node);
    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);

    // Allow multiple registrations per user.
    $settings->set('multiple_registrations', TRUE);
    $settings->save();

    // Fill standard capacity.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 5);
    $registration->save();
    $this->assertFalse($host_entity->hasRoomOffWaitList());
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());

    // Add registrations to the wait list.
    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 2);
    $registration->save();
    $this->assertEquals(2, $host_entity->getWaitListSpacesReserved());

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 4);
    $registration->save();
    $this->assertEquals(6, $host_entity->getWaitListSpacesReserved());

    $registration = $this->createRegistration($node);
    $registration->set('author_uid', 1);
    $registration->set('count', 3);
    $registration->save();
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Increase capacity. Autofill is not enabled.
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Decrease capacity and enable autofill.
    $settings->set('capacity', 5);
    $settings->set('registration_waitlist_autofill', TRUE);
    $settings->set('registration_waitlist_autofill_state', 'complete');
    $settings->save();
    $this->assertEquals(5, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(9, $host_entity->getWaitListSpacesReserved());

    // Increase capacity. Autofill is enabled and fills the available spots.
    $settings->set('capacity', 10);
    $settings->save();
    $this->assertEquals(10, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(4, $host_entity->getWaitListSpacesReserved());
    // Two registrations were autofilled.
    $this->assertTrue($this->loggedRegistrationCountMatches(2));

    // Delete a registration. Autofill is enabled and fills the available spots.
    $registration = $this->entityTypeManager->getStorage('registration')->load(1);
    $registration->delete();
    $this->assertEquals(9, $host_entity->getActiveSpacesReserved());
    $this->assertEquals(0, $host_entity->getWaitListSpacesReserved());
    // One registration was autofilled.
    $this->assertTrue($this->loggedRegistrationCountMatches(1));
  }

  /**
   * Determines if the autofill registration count matches a given count.
   *
   * @param int $count
   *   The count to check.
   *
   * @return bool
   *   TRUE if the autofill registration count matches, FALSE otherwise.
   */
  protected function loggedRegistrationCountMatches(int $count): bool {
    $message = \Drupal::translation()->formatPlural($count, 'Automatically filled 1 registration from the wait list.', 'Automatically filled @count registrations from the wait list.');
    $database = Database::getConnection();
    $query = $database->select('watchdog')
      ->condition('message', $message);
    $query->addExpression('count(wid)', 'registrations');

    $rows = $query->execute()->fetchField();
    $rows = empty($rows) ? 0 : $rows;
    return ($rows == 1);
  }

}
