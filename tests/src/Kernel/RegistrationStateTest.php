<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\registration\RegistrationState;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\State;

/**
 * Tests registration state.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationState
 *
 * @group registration
 */
class RegistrationStateTest extends RegistrationKernelTestBase {

  /**
   * @covers ::getDescription
   * @covers ::isActive
   * @covers ::isCanceled
   * @covers ::isHeld
   * @covers ::isShownOnForm
   * @covers ::id
   * @covers ::label
   * @covers ::weight
   * @covers ::canTransitionTo
   * @covers ::getTransitionTo
   * @covers ::getTransitions
   */
  public function testState() {
    $workflow = Workflow::load('registration');

    // Pending.
    $state = $workflow
      ->getTypePlugin()
      ->getState('pending');

    /** @var \Drupal\registration\RegistrationState $state */
    $this->assertEquals('Registration is pending.', $state->getDescription());
    $this->assertTrue($state->isActive());
    $this->assertFalse($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('pending', $state->id());
    $this->assertEquals('Pending', $state->label());
    $this->assertEquals(0, $state->weight());
    $this->assertTrue($state->canTransitionTo('complete'));
    $this->assertFalse($state->canTransitionTo('pending'));

    $transition = $state->getTransitionTo('complete');
    $this->assertEquals('complete', $transition->id());

    $transitions = $state->getTransitions();
    $this->assertArrayHasKey('complete', $transitions);

    // Complete.
    $state = $transition->to();

    $this->assertEquals('Registration has been completed.', $state->getDescription());
    $this->assertTrue($state->isActive());
    $this->assertFalse($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('complete', $state->id());
    $this->assertEquals('Complete', $state->label());
    $this->assertEquals(1, $state->weight());
    $this->assertFalse($state->canTransitionTo('complete'));
    $this->assertFalse($state->canTransitionTo('pending'));

    $transitions = $state->getTransitions();
    $this->assertEmpty($transitions);
  }

  /**
   * Tests creation of a new state.
   */
  public function testNewState() {
    $workflow = Workflow::load('registration');
    $workflow_state = new State($workflow->getTypePlugin(), 'canceled', 'Canceled', 10);
    $active = FALSE;
    $canceled = TRUE;
    $held = FALSE;
    $show = FALSE;
    $state = new RegistrationState($workflow_state, 'Registration has been canceled.', $active, $canceled, $held, $show);

    $this->assertEquals('Registration has been canceled.', $state->getDescription());
    $this->assertFalse($state->isActive());
    $this->assertTrue($state->isCanceled());
    $this->assertFalse($state->isHeld());
    $this->assertFalse($state->isShownOnForm());
    $this->assertEquals('canceled', $state->id());
    $this->assertEquals('Canceled', $state->label());
    $this->assertEquals(10, $state->weight());
  }

}
