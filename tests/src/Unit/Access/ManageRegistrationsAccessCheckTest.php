<?php

namespace Drupal\Tests\registration\Unit\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration\Access\ManageRegistrationsAccessCheck;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests the 'manage registrations' access check.
 *
 * @coversDefaultClass \Drupal\registration\Access\ManageRegistrationsAccessCheck
 *
 * @group registration
 */
class ManageRegistrationsAccessCheckTest extends UnitTestCase {

  /**
   * The host entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

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

    \Drupal::unsetContainer();
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $cache_contexts_manager = new CacheContextsManager($container, [
      'user',
      'user.permissions',
    ]);
    $container->set('cache_contexts_manager', $cache_contexts_manager);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->any())->method('getCacheTags')->willReturn(['node:57']);
    $entity->expects($this->any())->method('getCacheContexts')->willReturn([]);

    $host_entity = $this->createMock(HostEntityInterface::class);
    $host_entity->expects($this->any())->method('getEntity')->willReturn($entity);
    $host_entity->expects($this->any())->method('getRegistrationTypeBundle')->willReturn('conference');

    $registration_manager = $this->createMock(RegistrationManagerInterface::class);
    $registration_manager->expects($this->any())->method('getEntityFromParameters')->willReturn($host_entity);

    $this->entity = $entity;
    $this->registrationManager = $registration_manager;
  }

  /**
   * @covers ::access
   */
  public function testManageRegistrationsAccessCheckWithoutEntityUpdate() {
    // Mock the required services and objects.
    $route_match = $this->createMock(RouteMatch::class);
    $bag = new ParameterBag();
    $route_match->expects($this->any())->method('getParameters')->willReturn($bag);

    $access_checker = new ManageRegistrationsAccessCheck($this->registrationManager);

    // Access to update the entity is not granted.
    $this->entity->expects($this->any())->method('access')->willReturn(AccessResult::neutral());

    // Administer registration permission.
    $account = $this->getMockAccount(['administer registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->getMockAccount(['administer conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type settings" registration permission.
    $account = $this->getMockAccount(['administer conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "type" registration permission.
    $account = $this->getMockAccount(['manage conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['administer own conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Administer "own" settings registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['administer own conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());

    // Manage "own" registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['manage own conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

  /**
   * Creates a mock account with the given permissions granted.
   *
   * @param array $permissions_granted
   *   The permissions that should be granted.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  protected function getMockAccount(array $permissions_granted = []) {
    $permissions = [
      'administer registration',
      'administer conference registration',
      'administer conference registration settings',
      'administer own conference registration',
      'administer own conference registration settings',
      'manage conference registration',
      'manage conference registration broadcast',
      'manage conference registration settings',
      'manage own conference registration',
    ];
    $permissions_map = [];
    foreach ($permissions as $permission) {
      $permissions_map[] = [$permission, in_array($permission, $permissions_granted)];
    }

    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap($permissions_map);
    return $account;
  }

  /**
   * @covers ::access
   */
  public function testManageRegistrationsAccessCheckWithEntityUpdate() {
    // Mock the required services and objects.
    $route_match = $this->createMock(RouteMatch::class);
    $bag = new ParameterBag();
    $route_match->expects($this->any())->method('getParameters')->willReturn($bag);

    $access_checker = new ManageRegistrationsAccessCheck($this->registrationManager);

    // Access to update the entity is granted.
    $this->entity->expects($this->any())->method('access')->willReturn(AccessResult::allowed());

    // Administer "own" registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['administer own conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "own" settings registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['administer own conference registration settings']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Manage "own" registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->getMockAccount(['manage own conference registration']);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->getMockAccount([]);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
