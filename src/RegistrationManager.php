<?php

namespace Drupal\registration;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Route;

/**
 * Defines the class for the registration manager service.
 */
class RegistrationManager implements RegistrationManagerInterface {

  use StringTranslationTrait;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProvider
   */
  protected RouteProvider $routeProvider;

  /**
   * Creates a RegistrationManager object.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type bundle info.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteProvider $route_provider
   *   The route provider.
   */
  public function __construct(AccountProxy $current_user, Connection $database, EntityDisplayRepositoryInterface $entity_display_repository, EntityFieldManager $entity_field_manager, EntityTypeBundleInfo $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RouteProvider $route_provider) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveRegistrationCount(EntityInterface $host_entity, RegistrationSettings $settings, RegistrationInterface $registration = NULL): int {
    $states = [];

    if ($registration_type = $this->getRegistrationType($host_entity)) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return 0;
    }

    $query = $this->database->select('registration')
      ->condition('entity_id', $host_entity->id())
      ->condition('entity_type_id', $host_entity->getEntityTypeId())
      ->condition('state', array_keys($states), 'IN');

    if ($registration && !$registration->isNew()) {
      $query->condition('registration_id', $registration->id(), '<>');
    }

    $query->addExpression('sum(count)', 'count');

    $count = $query->execute()->fetchField();
    $count = empty($count) ? 0 : $count;

    // Allow other modules to override the count.
    $context = [
      'host_entity' => $host_entity,
      'registration' => $registration,
      'settings' => $settings,
    ];

    $this->moduleHandler->alter('registration_event_count', $count, $context);

    return $count;
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
  public function getRegistrantOptions(EntityInterface $host_entity, RegistrationSettings $settings): array {
    $options = [];

    $type = $this->getRegistrationTypeBundle($host_entity);

    // Me:
    $allow_multiple = $this->getRegistrationSetting($host_entity, $settings, 'multiple_registrations');
    if ($this->currentUser->isAuthenticated()
      && $this->currentUser->hasPermission("create $type registration self")
      && (!$this->isUserRegistered($host_entity, $this->currentUser) || $allow_multiple)
    ) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ME] = $this->t('Myself');
    }

    // Other users:
    $user_is_anonymous = $this->currentUser->isAnonymous();
    if ($this->currentUser->hasPermission("create $type registration other users") && !$user_is_anonymous) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_USER] = $this->t('Other account');
    }

    // Other anonymous people:
    if ($this->currentUser->hasPermission("create $type registration other anonymous") && !$user_is_anonymous) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON] = $this->t('Other person');
    }

    // Anonymous self-registration:
    if ($user_is_anonymous && $this->currentUser->hasPermission("create $type registration")) {
      $options[RegistrationInterface::REGISTRATION_REGISTRANT_TYPE_ANON] = $this->t('Myself');
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationField(EntityInterface $host_entity): ?FieldDefinitionInterface {
    $fields = $this->entityFieldManager->getFieldDefinitions($host_entity->getEntityTypeId(), $host_entity->bundle());
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
  public function getRegistrationSetting(EntityInterface $host_entity, RegistrationSettings $settings, string $key): mixed {
    $setting_value = $settings->getSetting($key);
    if (!is_null($setting_value)) {
      // Registration settings entity has the setting.
      return $setting_value;
    }

    // The registration settings entity does not have the setting yet.
    // Get a default value from the host entity registration field defaults.
    return $this->getFieldWidgetSetting($host_entity->getEntityType(),
      $this->getRegistrationField($host_entity), $key);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationType(EntityInterface $host_entity): ?RegistrationTypeInterface {
    $registration_type = NULL;

    if ($bundle = $this->getRegistrationTypeBundle($host_entity)) {
      $registration_type = RegistrationType::load($bundle);
    }

    return $registration_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeBundle(EntityInterface $host_entity): ?string {
    $bundle = NULL;

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $host_entity */
    if ($field = $this->getRegistrationField($host_entity)) {
      if (!$host_entity->get($field->getName())->isEmpty()) {
        $value = $host_entity->get($field->getName())->getValue();
        if (!empty($value)) {
          $value = reset($value);
          if (is_array($value) && isset($value['registration_type'])) {
            $bundle = $value['registration_type'];
          }
        }
      }
    }

    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoute(EntityTypeInterface $entity_type, string $route_id): ?Route {
    $path = $this->getBasePath($entity_type);
    if (!$path) {
      return NULL;
    }

    // Build the 'manage' route and adjust for other routes.
    $route = $this->buildManageRoute($entity_type, $path);
    switch($route_id) {
      case 'broadcast':
        $route
          ->setPath($route->getPath() . '/broadcast')
          ->setDefaults([
            '_form' => '\Drupal\registration\Form\EmailRegistrantsForm',
            '_title' => 'Email registrants',
          ]);
        break;

      case 'manage':
        $route->setOption('_admin_route', FALSE);
        break;

      case 'register':
        $route
          ->setPath($path . '/register')
          ->setDefaults([
            '_entity_form' => 'registration.register',
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
  public function getSettingsForHost(EntityInterface $host_entity): ?RegistrationSettings {
    $settings = NULL;
    if ($this->getRegistrationTypeBundle($host_entity)) {
      /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
      $storage = $this->entityTypeManager->getStorage('registration_settings');
      $settings = $storage->loadSettingsForEntity($host_entity);
    }
    return $settings;
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
   * {@inheritdoc}
   */
  public function hasRoom(EntityInterface $host_entity, RegistrationSettings $settings, int $spaces = 1, RegistrationInterface $registration = NULL): bool {

    $capacity = $this->getRegistrationSetting($host_entity, $settings, 'capacity');
    if ($capacity) {
      $count = $this->getActiveRegistrationCount($host_entity, $settings, $registration) + $spaces;
      if (($capacity - $count) < 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabledForRegistration(EntityInterface $host_entity, RegistrationSettings $settings, int $spaces = 1, RegistrationInterface $registration = NULL, array &$errors = []): bool {
    $status = $this->getRegistrationSetting($host_entity, $settings, 'status');
    $open = $this->getRegistrationSetting($host_entity, $settings, 'open');
    $close = $this->getRegistrationSetting($host_entity, $settings, 'close');

    // Only explore other settings if main status is enabled.
    if ($status) {

      // Check maximum allowed spaces per registration.
      $maximum_spaces = (int) $this->getRegistrationSetting($host_entity, $settings, 'maximum_spaces');
      if ($maximum_spaces && ($spaces > $maximum_spaces)) {
        $status = FALSE;
        $errors[] = $this->t('You may not register for more than @count spaces.', [
          '@count' => $maximum_spaces,
        ]);
      }

      // Check capacity.
      if (!$this->hasRoom($host_entity, $settings, $spaces, $registration)) {
        $status = FALSE;
        $errors[] = $this->t('Sorry, unable to register for %label due to: insufficient spaces remaining.', [
          '%label' => $host_entity->label(),
        ]);
      }

      // Check open date range.
      $now = new DrupalDateTime('now');
      $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $now = $now->getTimestamp();
      if ($open && ($now < strtotime($open))) {
        $status = FALSE;
        $errors[] = $this->t('Registration is not yet open.');
      }

      // Check close date range.
      if ($close && ($now >= strtotime($close))) {
        $status = FALSE;
        $errors[] = $this->t('Registration is closed.');
      }
    }
    else {
      $errors[] = $this->t('Registration is disabled.');
    }

    // Allow other mods to override status.
    $context = [
      'host_entity' => $host_entity,
      'errors' => &$errors,
    ];
    $this->moduleHandler->alter('registration_status', $status, $context);

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegistered(EntityInterface $host_entity, string $email): bool {
    $states = [];

    if ($registration_type = $this->getRegistrationType($host_entity)) {
      $states = $registration_type->getActiveStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $query = $this->database->select('registration')
      ->condition('entity_id', $host_entity->id())
      ->condition('entity_type_id', $host_entity->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegistered(EntityInterface $host_entity, AccountInterface $account): bool {
    $states = [];

    if ($registration_type = $this->getRegistrationType($host_entity)) {
      $states = $registration_type->getActiveStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $query = $this->database->select('registration')
      ->condition('entity_id', $host_entity->id())
      ->condition('entity_type_id', $host_entity->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
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
   * Gets the path for an entity type that registration routes will be based on.
   *
   * Returns NULL unless the type has a bundle with a registration field.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return string|null
   *   The base path, if available.
   */
  protected function getBasePath(EntityTypeInterface $entity_type): ?string {
    if (($template = $this->getBaseTemplate($entity_type)) && $this->hasRegistrationField($entity_type)) {
      $path = $entity_type->getLinkTemplate($template);

      // Truncate 'edit' if using the edit-form link template.
      $edit = '/edit';
      if (str_ends_with($path, $edit)) {
        $path = substr($path, 0, strlen($path) - strlen($edit));
      }

      return $path;
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

    // Check default first, then other form modes that exist.
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

}
