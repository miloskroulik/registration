<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\registration\Entity\Registration;
use Drupal\registration\Entity\RegistrationType;

/**
 * Tests registration permissions and access control.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationAccessControlHandler
 *
 * @group registration
 */
class RegistrationAccessTest extends RegistrationKernelTestBase {

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    \Drupal::currentUser()->setAccount($admin_user);

    $node = Node::create([
      'type' => 'event',
      'title' => 'My event',
      'event_registration' => 'conference',
    ]);
    $node->save();
    $this->node = $node;

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
    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
      'user_uid' => $account->id(),
    ]);
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
    $access_control_handler = \Drupal::entityTypeManager()->getAccessControlHandler('registration');

    $account = $this->createUser([], ['access content']);
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
   * Tests route access for registrations.
   */
  public function testRouteAccess() {
    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
    ]);
    $registration->save();

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
