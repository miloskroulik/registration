<?php

namespace Drupal\registration;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\RouteProvider;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the class for the registration manager service.
 */
class RegistrationManager implements RegistrationManagerInterface {

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
   * Creates a RegistrationManager object.
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
  public function getFieldConfigSetting(EntityTypeInterface $entity_type, string $key): mixed {
    $setting_value = NULL;

    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $entity_type_id = $entity_type->id();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      // If there are multiple bundles with a registration field, the field
      // instance for the last bundle will determine the setting value. This
      // is not ideal but replicates the behavior of the D7 module.
      foreach ($bundle_info as $bundle => $info) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            $value = $this->getFieldWidgetSetting($entity_type, $field, $key);
            if (!is_null($value)) {
              $setting_value = $value;
            }
          }
        }
      }
    }

    return $setting_value;
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
    return $this->getFieldWidgetSetting($host_entity->getEntityType(),
      $this->getRegistrationField($host_entity), $key);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoute(EntityTypeInterface $entity_type, string $id): ?Route {
    $path = $this->getLinkTemplate($entity_type);
    if (!$path) {
      return NULL;
    }

    // Truncate if using the edit-form link template.
    $edit = '/edit';
    if (str_ends_with($path, $edit)) {
      $path = substr($path, 0, strlen($path) - strlen($edit));
    }

    // Build the 'manage' route and adjust for other routes.
    $route = $this->buildManageRoute($entity_type, $path);
    switch($id) {
      case 'broadcast':
        $route
          ->setPath($route->getPath() . '/broadcast')
          ->setDefaults([
            '_form' => '\Drupal\registration\Form\EmailRegistrantsForm',
            '_title' => 'Email registrants',
          ]);
        break;

      case 'manage':
        break;

      case 'register':
        $route
          ->setPath($path . '/register')
          ->setDefaults([
            '_form' => '\Drupal\registration\Form\RegisterForm',
            '_title' => 'Register',
          ])
          ->setOption('_admin_route', FALSE)
          ->setRequirements([
            '_register_access_check' => 'TRUE',
          ]);
        break;

      case 'settings':
        $route
          ->setPath($route->getPath() . '/settings')
          ->setDefaults([
            '_form' => '\Drupal\registration\Form\RegistrationSettingsForm',
            '_title' => 'Registration settings',
          ]);
        break;
    }

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRegistrationField(EntityTypeInterface $entity_type): bool {
    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $entity_type_id = $entity_type->id();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundle_info as $bundle => $info) {
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($fields as $field) {
          if ($field->getType() == 'registration') {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Build the manage registrations route for an entity type and base path.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $path
   *   The base path.
   *
   * @return \Symfony\Component\Routing\Route
   *   The generated route.
   *
   */
  protected function buildManageRoute(EntityTypeInterface $entity_type, string $path): Route {
    $entity_type_id = $entity_type->id();
    $route = new Route($path . '/registrations');
    $route
      ->addDefaults([
        '_controller' => '\Drupal\registration\Controller\RegistrationController::manageRegistrations',
        '_title' => 'Manage Registrations',
      ])
      ->addRequirements([
        '_manage_registrations_access_check' => 'TRUE',
      ])
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

    return $route;
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

  /**
   * Gets the value of a setting from a registration field widget.
   *
   * The value is retrieved from the form display containing the widget.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition for a registration field.
   * @param string $key
   *   The setting name, for example "hide_register_tab".
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  protected function getFieldWidgetSetting(EntityTypeInterface $entity_type, FieldDefinitionInterface $field, string $key): mixed {
    $entity_type_id = $entity_type->id();
    $bundle = $field->getTargetBundle();

    $form_modes = ['default' => ''];
    $form_modes += $this->entityDisplayRepository->getFormModes($entity_type_id);
    foreach(array_keys($form_modes) as $form_mode) {
      $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, $form_mode);
      if ($form_display) {
        $component = $form_display->getComponent($field->getName());
        if (isset($component, $component['settings'], $component['settings'][$key])) {
          return $component['settings'][$key];
        }
      }
    }

    return NULL;
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

}
