<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\registration\Entity\RegistrationType;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration permissions and access control.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationAccessControlHandler
 *
 * @group registration
 */
class RegistrationAccessTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $registration_type = RegistrationType::create([
      'id' => 'seminar',
      'label' => 'Seminar',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccess() {
    $account = $this->createUser([], ['access registration overview']);

    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();

    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));

    // "Own" permissions.
    $account = $this->createUser([], ['view own registration']);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));

    $account = $this->createUser([], [
      'view own conference registration',
      'update own conference registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));

    $account = $this->createUser([], [
      'view own conference registration',
      'update own conference registration',
      'delete own conference registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));

    // "Own" permissions for the wrong type.
    $account = $this->createUser([], [
      'view own seminar registration',
      'update own seminar registration',
      'delete own seminar registration',
    ]);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));

    // "View any" permission.
    $account = $this->createUser([], ['access content']);
    $registration->set('author_uid', $account->id());
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('view', $account));
    $account = $this->createUser([], ['view any registration']);
    $this->assertTrue($registration->access('view', $account));

    // "Administer" permission.
    $account = $this->createUser([], ['administer registration']);
    $this->assertTrue($registration->access('view', $account));
    $this->assertTrue($registration->access('update', $account));
    $this->assertTrue($registration->access('delete', $account));

    // "Administer types" permission only applies to types.
    $account = $this->createUser([], ['administer registration types']);
    $this->assertFalse($registration->access('view', $account));
    $this->assertFalse($registration->access('update', $account));
    $this->assertFalse($registration->access('delete', $account));
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    $account = $this->createUser([], ['access content']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser([], ['bypass node access']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser([], ['administer registration']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser([], ['create conference registration self']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser([], ['create conference registration other users']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser([], ['create conference registration other anonymous']);
    $this->assertTrue($access_control_handler->createAccess('conference', $account));

    $account = $this->createUser([], ['create seminar registration self']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser([], ['create seminar registration other users']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
    $account = $this->createUser([], ['create seminar registration other anonymous']);
    $this->assertFalse($access_control_handler->createAccess('conference', $account));
  }

  /**
   * Tests delete access for registrations.
   */
  public function testDeleteAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    // Administer registration type only applies to settings, not registrations.
    $account = $this->createUser([], ['administer conference registration']);
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('delete', $account));

    // Delete "own" permission.
    $account = $this->createUser([], ['delete own conference registration']);
    $this->assertFalse($registration->access('delete', $account));
    $registration->set('author_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertFalse($registration->access('delete', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('delete', $account));

    // Delete "any" permission.
    $account = $this->createUser([], ['delete any conference registration']);
    $this->assertTrue($registration->access('delete', $account));
    $account = $this->createUser([], ['delete any seminar registration']);
    $this->assertFalse($registration->access('delete', $account));
  }

  /**
   * Tests update access for registrations.
   */
  public function testUpdateAccess() {
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler('registration');

    // Administer registration type only applies to settings, not registrations.
    $account = $this->createUser([], ['administer conference registration']);
    $node = $this->createAndSaveNode();
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $account->id());
    $registration->save();
    $this->assertFalse($registration->access('update', $account));

    // Update "own" permission.
    $account = $this->createUser([], ['update own conference registration']);
    $this->assertFalse($registration->access('update', $account));
    $registration->set('author_uid', $account->id());
    $registration->save();
    // @see https://www.drupal.org/project/drupal/issues/2834344
    $access_control_handler->resetCache();
    $this->assertFalse($registration->access('update', $account));
    $registration->set('user_uid', $account->id());
    $registration->save();
    $access_control_handler->resetCache();
    $this->assertTrue($registration->access('update', $account));

    // Update "any" permission.
    $account = $this->createUser([], ['update any conference registration']);
    $this->assertTrue($registration->access('update', $account));
    $account = $this->createUser([], ['update any seminar registration']);
    $this->assertFalse($registration->access('update', $account));
  }

  /**
   * Tests route access for registrations.
   */
  public function testRouteAccess() {
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);

    $account = $this->createUser([], ['administer registration']);
    $this->assertTrue($registration->toUrl('collection')->access($account));
    $this->assertTrue($registration->toUrl('edit-form')->access($account));
    $this->assertTrue($registration->toUrl('delete-form')->access($account));

    $account = $this->createUser([], ['access registration overview']);
    $this->assertTrue($registration->toUrl('collection')->access($account));
    $this->assertFalse($registration->toUrl('edit-form')->access($account));
    $this->assertFalse($registration->toUrl('delete-form')->access($account));

    $account = $this->createUser([], ['access content overview']);
    $this->assertFalse($registration->toUrl('collection')->access($account));
    $this->assertFalse($registration->toUrl('edit-form')->access($account));
    $this->assertFalse($registration->toUrl('delete-form')->access($account));
  }

}
