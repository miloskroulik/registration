<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\RouteProvider;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Defines a utility class for registrations.
 */
class RegistrationService implements RegistrationServiceInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected EntityFieldManager $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected RouteProvider $routeProvider;

  /**
   * Creates a RegistrationService object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Routing\RouteProvider $route_provider
   *   The route provider.
   */
  public function __construct(EntityFieldManager $entity_field_manager, EntityTypeBundleInfo $entity_type_bundle_info, RouteProvider $route_provider) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteName(EntityTypeInterface $entity_type): ?string {
    $base_route = NULL;

    if ($template = $this->getBaseTemplate($entity_type)) {
      $entity_type_id = $entity_type->id();
      // Must convert edit-form to edit_form.
      $base_route = "entity.$entity_type_id." . str_replace('-', '_', $template);
    }

    return $base_route;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromParameters(ParameterBag $parameters): ?EntityInterface {
    $entity = NULL;
    foreach ($parameters as $parameter) {
      if ($parameter instanceof EntityInterface) {
        $entity = $parameter;
        break;
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getManageRoute(EntityTypeInterface $entity_type): ?Route {
    $route = NULL;

    if ($path = $this->getLinkTemplate($entity_type)) {
      if ($base_route_name = $this->getBaseRouteName($entity_type)) {
        $base_route = $this->routeProvider->getRouteByName($base_route_name);
        if (!$base_route) {
          throw new RouteNotFoundException('Route "'. $base_route_name . '" does not exist.');
        }
        $entity_type_id = $entity_type->id();
        $edit = '/edit';
        if (str_ends_with($path, $edit)) {
          $path = substr($path, 0, strlen($path) - strlen($edit));
        }
        $route = new Route($path . '/registrations');
        $route
          ->addDefaults([
            '_controller' => '\Drupal\registration\Controller\RegistrationController::manageRegistrations',
            '_title' => 'Manage Registrations',
          ])
          ->addRequirements([
            '_manage_registrations_access_check' => 'TRUE',
          ])
          ->setOption('_admin_route', $base_route->getOption('_admin_route'))
          ->setOption('parameters', [
            $entity_type_id => ['type' => 'entity:' . $entity_type_id],
          ]);
      }
    }

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationField(EntityInterface $entity): ?FieldDefinitionInterface {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityType()->id(), $entity->bundle());
    foreach ($fields as $field) {
      if ($field->getType() == 'registration') {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRegistrationField(EntityTypeInterface $entity_type): bool {
    $entity_type_id = $entity_type->id();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach ($bundle_info as $bundle => $info) {
      try {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            return TRUE;
          }
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }
    return FALSE;
  }

  /**
   * Gets the link template for an entity type.
   *
   * Returns NULL unless the type has a bundle with a registration field.
   * 
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The link template path, if available.
   */
  protected function getLinkTemplate(EntityTypeInterface $entity_type): ?string {
    if (($template = $this->getBaseTemplate($entity_type)) && $this->hasRegistrationField($entity_type)) {
      return $entity_type->getLinkTemplate($template);
    }

    return NULL;
  }

  /**
   * Gets the base template for an entity type.
   *
   * This is typically 'canonical', but falls back to 'edit-form'.
   * Returns NULL if the entity type has neither.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base template name, if available.
   */
  protected function getBaseTemplate(EntityTypeInterface $entity_type): ?string {
    $base_template = NULL;

    // Find a suitable link template for use in base route construction.
    // Most entity types have a canonical template, but not all.
    // Fallback to the edit form link if it exists.
    $templates = [
      'canonical',
      'edit-form',
    ];
    foreach ($templates as $template) {
      if ($entity_type->hasLinkTemplate($template)) {
        $base_template = $template;
        break;
      }
    }

    return $base_template;
  }

}
