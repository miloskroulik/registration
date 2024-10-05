<?php

namespace Drupal\Tests\registration\Kernel\Access;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\registration\Access\RegisterAccessCheck;
use Drupal\registration\RegistrationManagerInterface;

/**
 * Tests the "register" access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\RegisterAccessCheck
 *
 * @group registration
 */
class RegisterAccessCheckTest extends RegistrationKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->registrationManager = $this->container->get('registration.manager');
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationConfigured() {
    $access_checker = new RegisterAccessCheck($this->entityTypeManager, $this->registrationManager);

    $node = $this->createAndSaveNode();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    $parameters = $route_match->getParameters();
    $host_entity = $this->registrationManager->getEntityFromParameters($parameters, TRUE);
    $settings = $host_entity->getSettings();
    $settings->set('status', FALSE);
    $settings->save();

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAccessRegistrationNotConfigured() {
    $access_checker = new RegisterAccessCheck($this->entityTypeManager, $this->registrationManager);

    $node = $this->createNode();
    $node->set('event_registration', NULL);
    $node->save();
    $entity_type = $node->getEntityType();

    $route = $this->registrationManager->getRoute($entity_type, 'register');
    $route_name = $this->registrationManager->getBaseRouteName($entity_type) . '.register';
    $route_match = new RouteMatch($route_name, $route, [
      'node' => $node,
    ]);

    $account = $this->createUser(['access registration overview']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration self']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other users']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    $account = $this->createUser(['create conference registration other anonymous']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
