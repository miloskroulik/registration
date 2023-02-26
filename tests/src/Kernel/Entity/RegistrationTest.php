<?php

namespace Drupal\Tests\registration\Kernel\Entity;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\registration\Entity\Registration;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\user\UserInterface;

/**
 * Tests the Registration entity.
 *
 * @coversDefaultClass \Drupal\registration\Entity\Registration
 *
 * @group registration
 */
class RegistrationTest extends RegistrationKernelTestBase {

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([], ['administer registration']);
    $user = $this->reloadEntity($user);
    /** @var \Drupal\user\UserInterface $user */
    $this->user = $user;
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * @covers ::label
   * @covers ::getAnonymousEmail
   * @covers ::getAuthor
   * @covers ::getAuthorDisplayName
   * @covers ::getEmail
   * @covers ::getHostEntity
   * @covers ::getHostEntityId
   * @covers ::getHostEntityTypeId
   * @covers ::getHostEntityTypeLabel
   * @covers ::getLangcode
   * @covers ::getRegistrantType
   * @covers ::getSpacesReserved
   * @covers ::getType
   * @covers ::getUser
   * @covers ::getUserId
   * @covers ::getWorkflow
   * @covers ::getState
   * @covers ::getCompletedTime
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::isActive
   * @covers ::isCanceled
   * @covers ::isComplete
   * @covers ::isHeld
   */
  public function testRegistration() {
    $node = Node::create([
      'type' => 'event',
      'title' => 'My event',
    ]);
    $node->save();
    $node = $this->reloadEntity($node);

    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
      'user_uid' => $this->user->id(),
    ]);
    $registration->save();

    $this->assertEquals('Registration #1 for My event', $registration->label());
    $this->assertEquals('', $registration->getAnonymousEmail());
    $this->assertEquals($this->user, $registration->getAuthor());
    $this->assertEquals($this->user->getDisplayName(), $registration->getAuthorDisplayName());
    $this->assertEquals($this->user->getEmail(), $registration->getEmail());
    $this->assertEquals($node, $registration->getHostEntity()->getEntity());
    $this->assertEquals($node->id(), $registration->getHostEntityId());
    $this->assertEquals($node->getEntityTypeId(), $registration->getHostEntityTypeId());
    $this->assertEquals('Event', $registration->getHostEntityTypeLabel());
    $this->assertEquals('en', $registration->getLangcode());
    $this->assertEquals(RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME, $registration->getRegistrantType($this->user));
    $this->assertEquals(1, $registration->getSpacesReserved());
    $this->assertEquals($this->regType->id(), $registration->getType()->id());
    $this->assertEquals($this->user, $registration->getUser());
    $this->assertEquals($this->user->id(), $registration->getUserId());
    $this->assertEquals($this->regType->getWorkflow()->id(), $registration->getWorkflow()->id());
    $this->assertEquals($this->regType->getDefaultState(), $registration->getState()->id());
    $this->assertEquals(TRUE, $registration->isActive());
    $this->assertEquals(FALSE, $registration->isCanceled());
    $this->assertEquals(FALSE, $registration->isComplete());
    $this->assertEquals(FALSE, $registration->isHeld());

    $registration->setCreatedTime(635879700);
    $this->assertEquals(635879700, $registration->getCreatedTime());
    $registration->save();
    $this->assertEquals(635879700, $registration->getCreatedTime());

    $registration->set('state', 'complete');
    $this->assertEquals(TRUE, $registration->isComplete());
    $registration->save();
    $this->assertEquals(TRUE, $registration->isComplete());
  }

}
