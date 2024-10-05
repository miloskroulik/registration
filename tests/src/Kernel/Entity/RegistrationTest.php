<?php

namespace Drupal\Tests\registration\Kernel\Entity;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
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

  use NodeCreationTrait;
  use RegistrationCreationTrait;

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

    $user = $this->createUser(['administer registration']);
    $user = $this->reloadEntity($user);
    /** @var \Drupal\user\UserInterface $user */
    $this->user = $user;
    $this->setCurrentUser($user);
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
    $node = $this->createAndSaveNode();
    $node = $this->reloadEntity($node);

    /** @var \Drupal\node\NodeInterface $node */
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $this->user->id());
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
    $this->assertTrue($registration->isActive());
    $this->assertFalse($registration->isCanceled());
    $this->assertFalse($registration->isComplete());
    $this->assertNull($registration->getCompletedTime());
    $this->assertFalse($registration->isHeld());

    $registration->setCreatedTime(635879700);
    $registration->save();
    $this->assertEquals(635879700, $registration->getCreatedTime());

    $registration->set('state', 'complete');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'admin@example.org');
    $registration->save();
    $this->assertEquals(1, $registration->getSpacesReserved());
    $this->assertEquals('admin@example.org', $registration->getAnonymousEmail());

    // A registration that starts out complete should have a completed time.
    $registration = $this->createRegistration($node);
    $registration->set('user_uid', $this->user->id());
    $registration->set('state', 'complete');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    // A registration completed in a presave hook should have a completed time.
    // @see registration_test_registration_presave().
    $registration = $this->createRegistration($node);
    $registration->set('anon_mail', 'trigger_presave_hook@example.org');
    $registration->save();
    $this->assertTrue($registration->isComplete());
    $this->assertEquals(\Drupal::time()->getRequestTime(), $registration->getCompletedTime());

    // Delete the host entity.
    $node->delete();
    $registration = $this->reloadEntity($registration);
    $this->assertNull($registration->getHostEntity());
    $this->assertNull($registration->getHostEntityTypeLabel());
  }

}
