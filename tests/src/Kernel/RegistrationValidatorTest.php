<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\registration\RegistrationValidatorInterface;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint1;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint3;
use Drupal\registration_test_validator\Plugin\Validation\RegistrationConstraint\Constraint4;

/**
 * Tests the RegistrationValidator class.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationValidator
 *
 * @group registration
 */
class RegistrationValidatorTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;

  /**
   * The registration validator.
   */
  protected RegistrationValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_validator',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->validator = $this->container->get('registration.validator');
  }

  /**
   * @covers ::execute
   */
  public function testRegistrationValidator() {
    $node1 = $this->createAndSaveNode();
    $node2 = $this->createAndSaveNode();
    $node3 = $this->createAndSaveNode();

    // Cacheability accumulates across constraints.
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
    ], [$node1, $node2]);

    $this->assertTrue($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertCount(2, $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());

    // Violations accumulate across constraints.
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
    ], [$node1, $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertContains('node:3', $metadata->getCacheTags());
    $this->assertCount(3, $metadata->getCacheTags());
    $this->assertEmpty($metadata->getCacheContexts());
    $this->assertEquals(-1, $metadata->getCacheMaxAge());
    $this->assertEquals(1, $validation_result->getViolations()->count());

    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
      'Constraint4',
    ], [$node1, $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertContains('node:1', $metadata->getCacheTags());
    $this->assertContains('node:2', $metadata->getCacheTags());
    $this->assertContains('node:3', $metadata->getCacheTags());
    $this->assertCount(3, $metadata->getCacheTags());
    $this->assertContains('user.permissions', $metadata->getCacheContexts());
    $this->assertCount(1, $metadata->getCacheContexts());
    $this->assertEquals(0, $metadata->getCacheMaxAge());
    $this->assertEquals(2, $validation_result->getViolations()->count());
    $violations = $validation_result->getViolations();
    $this->assertEquals('random3', $violations[0]->getCode());
    $this->assertTrue($violations[0]->getConstraint() instanceof Constraint3);
    $this->assertEquals('random4', $violations[1]->getCode());
    $this->assertTrue($violations[1]->getConstraint() instanceof Constraint4);
  }

  /**
   * Tests ending the pipeline early.
   */
  public function testRegistrationValidatorEndPipelineEarly() {
    $node1 = $this->createAndSaveNode();
    $node2 = $this->createAndSaveNode();
    $node3 = $this->createAndSaveNode();
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint1',
      'Constraint2',
      'Constraint3',
    ], ['not a node', $node2, $node3]);
    $this->assertFalse($validation_result->isValid());
    $metadata = $validation_result->getCacheableMetadata();
    $this->assertNotContains('node:2', $metadata->getCacheTags());
    $this->assertNotContains('node:3', $metadata->getCacheTags());
    $this->assertEquals(1, $validation_result->getViolations()->count());
    $violations = $validation_result->getViolations();
    $this->assertEquals('early', $violations[0]->getCode());
    $this->assertTrue($violations[0]->getConstraint() instanceof Constraint1);
  }

  /**
   * Tests the unmet dependencies exception.
   */
  public function testRegistrationValidatorUnmetDependenciesException() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Constraint2 has unmet dependencies');
    $validation_result = $this->validator->execute('RandomPipeline', [
      'Constraint2',
      'Constraint3',
      'Constraint4',
    ], []);
  }

}
