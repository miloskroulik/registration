<?php

namespace Drupal\registration\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\registration\RegistrationManagerInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views argument plugin to extract the host entity from a URL.
 *
 * @ViewsArgumentDefault(
 *   id = "registration_host_entity_type_id",
 *   title = @Translation("Host entity type ID from URL")
 * )
 */
class HostEntityTypeId extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a new HostEntityTypeId instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RegistrationManagerInterface $registration_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->registrationManager = $registration_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): HostEntityTypeId {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('registration.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $parameters = $this->routeMatch->getParameters();
    if ($entity = $this->registrationManager->getEntityFromParameters($parameters)) {
      return $entity->getEntityTypeId();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['url'];
  }

}
