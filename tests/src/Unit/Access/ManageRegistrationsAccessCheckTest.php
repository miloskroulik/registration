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
  }

  /**
   * @covers ::access
   */
  public function testManageRegistrationsAccessCheck() {
    // Mock the required services and objects.
    $route_match = $this->createMock(RouteMatch::class);
    $bag = new ParameterBag();
    $route_match->expects($this->any())->method('getParameters')->willReturn($bag);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->any())->method('getCacheTags')->willReturn(['node:57']);
    $entity->expects($this->any())->method('getCacheContexts')->willReturn([]);

    $host_entity = $this->createMock(HostEntityInterface::class);
    $host_entity->expects($this->any())->method('getEntity')->willReturn($entity);
    $host_entity->expects($this->any())->method('getRegistrationTypeBundle')->willReturn('conference');

    $registration_manager = $this->createMock(RegistrationManagerInterface::class);
    $registration_manager->expects($this->any())->method('getEntityFromParameters')->willReturn($host_entity);

    $access_checker = new ManageRegistrationsAccessCheck($registration_manager);

    // Administer registration permission.
    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->once())
      ->method('hasPermission')
      ->with('administer registration')
      ->willReturn(TRUE);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Administer "type" registration permission.
    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap([
        ['administer registration', FALSE],
        ['administer conference registration', TRUE],
        ['administer own conference registration', FALSE],
      ]));
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Setup entity access results for remaining checks.
    $result1 = AccessResult::neutral();
    $result2 = AccessResult::allowed();
    $result3 = AccessResult::allowed();
    $entity->expects($this->any())->method('access')->willReturnOnConsecutiveCalls($result1, $result2, $result3);

    // Administer "own" registration permission.
    // Needs update access to the entity to succeed.
    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap([
        ['administer registration', FALSE],
        ['administer conference registration', FALSE],
        ['administer own conference registration', TRUE],
      ]));
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
    $access_result = $access_checker->access($account, $route_match);
    $this->assertTrue($access_result->isAllowed());

    // Insufficient permission.
    $account = $this->createMock(AccountInterface::class);
    $account
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturn(FALSE);
    $access_result = $access_checker->access($account, $route_match);
    $this->assertFalse($access_result->isAllowed());
  }

}
