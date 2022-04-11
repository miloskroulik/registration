<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
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
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

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
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Routing\RouteProvider $route_provider
   *   The route provider.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, EntityFieldManager $entity_field_manager, EntityTypeBundleInfo $entity_type_bundle_info, RouteProvider $route_provider) {
    $this->entityDisplayRepository = $entity_display_repository;
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
  public function getBroadcastRoute(EntityTypeInterface $entity_type): ?Route {
    if ($route = $this->getManageRoute($entity_type)) {
      $route
        ->setPath($route->getPath() . '/broadcast')
        ->setDefaults([
          '_form' => '\Drupal\registration\Form\EmailRegistrantsForm',
          '_title' => 'Email registrants',
        ]);
    }
    return $route;
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
        // @todo Allow non-standard base routes for custom entities.
        // Use hook or event.
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
  public function getRegisterRoute(EntityTypeInterface $entity_type): ?Route {
    $route = NULL;

    if ($path = $this->getLinkTemplate($entity_type)) {
      $entity_type_id = $entity_type->id();
      $edit = '/edit';
      if (str_ends_with($path, $edit)) {
        $path = substr($path, 0, strlen($path) - strlen($edit));
      }
      $route = new Route($path . '/register');
      $route
        ->addDefaults([
          '_form' => '\Drupal\registration\Form\RegisterForm',
          '_title' => 'Register',
        ])
        ->addRequirements([
          '_register_access_check' => 'TRUE',
        ])
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
    }

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationField(EntityInterface $entity): ?FieldDefinitionInterface {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
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
  public function getRegistrationFormDisplaySetting(EntityInterface $host_entity, string $key): mixed {
    $field_definition = $this->getRegistrationField($host_entity);
    $field_name = $field_definition->getName();

    $form_modes = ['default' => ''];
    $form_modes += $this->entityDisplayRepository->getFormModes($host_entity->getEntityTypeId());
    foreach(array_keys($form_modes) as $form_mode) {
      $form_display = $this->entityDisplayRepository
        ->getFormDisplay($host_entity->getEntityTypeId(), $host_entity->bundle(), $form_mode);
      if ($form_display) {
        $component = $form_display->getComponent($field_name);
        if (isset($component, $component['settings'], $component['settings'][$key])) {
          return $component['settings'][$key];
        }
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationSetting(EntityInterface $host_entity, EntityInterface $registration_settings_entity, string $key): mixed {
    if ($registration_settings_entity->hasField($key) && !$registration_settings_entity->get($key)->isEmpty()) {
      // Registration settings entity has the setting.
      $setting = $registration_settings_entity->get($key)->first()->getValue();
      return $setting['value'];
    }

    // Check for an additional setting.
    // Extract from the serialized settings property.
    if (!$registration_settings_entity->hasField($key)) {
      if (!$registration_settings_entity->get('settings')->isEmpty()) {
        $settings = $registration_settings_entity->get('settings')->first()->getValue();
        if (!empty($settings)) {
          $settings = unserialize($settings['value']);
          if (!empty($settings[$key])) {
            // Registration settings entity has the additional setting.
            return $settings[$key];
          }
        }
      }
    }

    // The registration settings entity does not have the setting yet.
    // Get a default value from the host entity registration field defaults.
    return $this->getRegistrationFormDisplaySetting($host_entity, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsRoute(EntityTypeInterface $entity_type): ?Route {
    if ($route = $this->getManageRoute($entity_type)) {
      $route
        ->setPath($route->getPath() . '/settings')
        ->setDefaults([
          '_form' => '\Drupal\registration\Form\RegistrationSettingsForm',
          '_title' => 'Registration settings',
        ]);
    }
    return $route;
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
   * {@inheritdoc}
   */
  public function isRegisterTabHidden(EntityTypeInterface $entity_type): bool {
    $hide = FALSE;

    // If there are multiple bundles with a registration field, use the last
    // field instance to determine if the Register tab should be hidden. This
    // is not ideal but replicates the behavior of the D7 version of the module.
    $key = 'hide_register_tab';
    $entity_type_id = $entity_type->id();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach ($bundle_info as $bundle => $info) {
      try {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            $field_name = $field->getName();
            $form_modes = ['default' => ''];
            $form_modes += $this->entityDisplayRepository->getFormModes($entity_type_id);
            foreach(array_keys($form_modes) as $form_mode) {
              $form_display = $this->entityDisplayRepository
                ->getFormDisplay($entity_type_id, $bundle, $form_mode);
              if ($form_display) {
                $component = $form_display->getComponent($field_name);
                if (isset($component, $component['settings'], $component['settings'][$key])) {
                  $hide = (bool) $component['settings'][$key];
                }
              }
            }
          }
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    return $hide;
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
    // @todo Allow non-standard template for custom entities via hook or event.
    $base_template = NULL;

    // Find a suitable link template for use in base route construction. Most
    // entity types have a canonical template, but not all. Use canonical if
    // available, otherwise fallback to the edit form link if it exists.
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
