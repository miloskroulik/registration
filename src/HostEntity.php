<?php

namespace Drupal\registration;

use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the class for the host entity.
 *
 * This is a pseudo-entity wrapper around a real entity.
 */
class HostEntity extends ScopeEntity implements HostEntityInterface {

  use StringTranslationTrait;

  use DependencySerializationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Creates a HostEntity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The real entity being wrapped.
   * @param string|null $langcode
   *   (optional) The language the real entity should use, if available.
   */
  public function __construct(EntityInterface $entity, ?string $langcode = NULL) {
    // Get the entity in the appropriate language if requested. Since the
    // entity type is not known until runtime, need to make sure it is
    // translatable before proceeding.
    if ($langcode) {
      if ($entity->getEntityType()->entityClassImplements(TranslatableInterface::class)) {
        /** @var \Drupal\Core\TypedData\TranslatableInterface $entity */
        if ($entity->isTranslatable() && ($entity->language()->getId() != $langcode)) {
          // Switch to the requested language if the entity has a translation
          // available.
          if ($entity->hasTranslation($langcode)) {
            $entity = $entity->getTranslation($langcode);
          }
        }
      }
    }
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function addCacheableDependencies(array &$build, array $other_entities = []) {
    // Rebuild if the host entity is updated.
    $this->renderer()->addCacheableDependency($build, $this->getEntity());

    // Rebuild if other entities are updated.
    foreach ($other_entities as $entity) {
      if (isset($entity)) {
        $this->renderer()->addCacheableDependency($build, $entity);
      }
    }

    // Rebuild when registrations are added and deleted.
    // @todo Make this more granular.
    $build['#cache']['tags'][] = 'registration_list';

    // Rebuild per user or anonymous session.
    if ($this->currentUser()->isAnonymous()) {
      $build['#cache']['contexts'][] = 'session';
    }
    else {
      $build['#cache']['contexts'][] = 'user.permissions';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createRegistration(bool $save = FALSE): RegistrationInterface {
    $values = [
      'entity_type_id' => $this->getEntityTypeId(),
      'entity_id' => $this->id(),
      'type' => $this->getRegistrationTypeBundle(),
      'count' => 1,
    ];
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->entityTypeManager()->getStorage('registration')->create($values);
    if ($save) {
      $registration->save();
    }
    return $registration;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSampleRegistration(bool $save = FALSE): RegistrationInterface {
    $registration = $this->createRegistration();
    $registration->set('user_uid', $this->currentUser()->id());
    $registration->set('mail', $this->currentUser()->getEmail());
    if ($save) {
      $registration->save();
    }
    return $registration;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveSpacesReserved(?RegistrationInterface $registration = NULL): int {
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return 0;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('state', array_keys($states), 'IN');

    if ($registration && !$registration->isNew()) {
      $query->condition('registration_id', $registration->id(), '<>');
    }

    $query->addExpression('sum(count)', 'spaces');

    $spaces = $query->execute()->fetchField();
    $spaces = empty($spaces) ? 0 : $spaces;

    // Allow other modules to alter the number of spaces reserved.
    $event = new RegistrationDataAlterEvent($spaces, [
      'host_entity' => $this,
      'settings' => $this->getSettings(),
      'registration' => $registration,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_USAGE);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesRemaining(?RegistrationInterface $registration = NULL): ?int {
    if ($capacity = $this->getSetting('capacity')) {
      // Allow other modules to alter the number of spaces remaining.
      $spaces_remaining = $capacity - $this->getActiveSpacesReserved($registration);
      $event = new RegistrationDataAlterEvent($spaces_remaining, [
        'host_entity' => $this,
        'settings' => $this->getSettings(),
        'registration' => $registration,
      ]);
      $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_SPACES_REMAINING);
      return $event->getData() ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSettings(?string $langcode = NULL): array {
    $entity_type_id = $this->getEntityTypeId();
    $bundle = $this->bundle();
    if (!$langcode) {
      $langcode = $this->getEntity()->language()->getId();
    }
    $fields = $this->entityFieldManager()->getFieldDefinitionsForLanguage($entity_type_id, $bundle, $langcode);
    foreach ($fields as $field) {
      if ($field->getType() == 'registration') {
        $settings = $field->getDefaultValueLiteral();
        // If the registration field has saved default values, return those.
        if (isset($settings[0], $settings[0]['registration_settings'])) {
          // Default settings are stored in configuration as a serialized array.
          // @see \Drupal\registration\Plugin\Field\RegistrationItemFieldItemList
          return RegistrationHelper::flatten(unserialize($settings[0]['registration_settings']));
        }
        else {
          /** @var \Drupal\registration\Plugin\Field\RegistrationItemFieldItemList $item_list */
          $item_list = $this->getEntity()->get($field->getName());
          // No defaults have been saved to the field. Use fallback settings.
          return RegistrationHelper::flatten($item_list->getFallbackSettings());
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationCount(): int {
    $count = parent::getRegistrationCount();

    // Invoke the legacy event.
    // Allow other modules to alter the count.
    $event = new RegistrationDataAlterEvent($count, [
      'host_entity' => $this,
      'settings' => $this->getSettings(),
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_COUNT);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationField(): ?FieldDefinitionInterface {
    $fields = $this->entityFieldManager()->getFieldDefinitions($this->getEntityTypeId(), $this->bundle());
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
  public function getRegistrationType(): ?RegistrationTypeInterface {
    $registration_type = NULL;

    if ($bundle = $this->getRegistrationTypeBundle()) {
      $registration_type = RegistrationType::load($bundle);
    }

    return $registration_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationTypeBundle(): ?string {
    $bundle = NULL;

    if ($field = $this->getRegistrationField()) {
      if ($this->getEntity()->hasField($field->getName())) {
        if (!$this->getEntity()->get($field->getName())->isEmpty()) {
          $value = $this->getEntity()->get($field->getName())->getValue();
          if (!empty($value)) {
            $value = reset($value);
            if (is_array($value) && isset($value['registration_type'])) {
              $bundle = $value['registration_type'];
            }
          }
        }
      }
    }

    return $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->needsCapacityCheck($spaces, $registration) && !$this->legacyHasRoom($spaces, $registration)) {
      return FALSE;
    }
    return parent::hasRoom($spaces, $registration);
  }

  /**
   * {@inheritdoc}
   */
  protected function legacyHasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    $capacity = $this->getSetting('capacity');
    if ($capacity) {
      $projected_usage = $this->getActiveSpacesReserved($registration) + $spaces;
      if (($capacity - $projected_usage) < 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailableForRegistration(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $validation_result = $this->validator()->execute('available_for_registration', [
      'HostHasSettings',
      'HostIsOpen',
      'HostIsEnabled',
      'HostHasRoom',
      'HostAllowsRegistrant',
    ], $this);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isConfiguredForRegistration(): bool {
    return !is_null($this->getRegistrationTypeBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function isEditableRegistration(RegistrationInterface $registration, ?AccountInterface $account = NULL, bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $validation_result = $this->validator()->execute('editable_registration', [
      'HostHasSettings' => ['hostEntity' => $registration->getHostEntity()],
      'RegistrationIsEditable' => ['account' => $account],
    ], $registration);
    return $return_as_object ? $validation_result : $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabledForRegistration(int $spaces = 1, ?RegistrationInterface $registration = NULL, array &$errors = []): bool {
    @trigger_error('HostEntity::isEnabledForRegistration() is deprecated in registration:3.1.8 and is removed from registration:4.0.0. See https://www.drupal.org/node/3496339', E_USER_DEPRECATED);

    $validation_result = $this->validator()->execute('enabled_for_registration', [
      'HostHasSettings' => ['hostEntity' => $this],
      'HostIsOpen' => ['hostEntity' => $this],
      'HostIsEnabled' => ['hostEntity' => $this],
      'HostHasRoom' => ['hostEntity' => $this],
      'RegistrationWithinMaximumSpaces' => ['spaces' => $spaces],
    ], $registration ?? $this);

    $errors = $validation_result->getLegacyErrors();
    return $validation_result->isValid();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegistered(string $email): bool {
    @trigger_error('HostEntity::isEmailRegistered() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegisteredInStates(string $email, array $states): bool {
    @trigger_error('HostEntity::isEmailRegisteredInStates() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    // Ensure we have states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('anon_mail', $email)
      ->condition('state', $states, 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegistered(AccountInterface $account): bool {
    @trigger_error('HostEntity::isUserRegistered() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    $states = [];

    if ($registration_type = $this->getRegistrationType()) {
      $states = $registration_type->getActiveOrHeldStates();
    }

    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', array_keys($states), 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isUserRegisteredInStates(AccountInterface $account, array $states): bool {
    @trigger_error('HostEntity::isUserRegisteredInStates() is deprecated in registration:3.1.5 and is removed from registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
    // Ensure we have states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $database = Database::getConnection();
    $query = $database->select('registration')
      ->condition('entity_id', $this->id())
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('user_uid', $account->id())
      ->condition('state', $states, 'IN');

    $count = $query->countQuery()->execute()->fetchField();
    return ($count > 0);
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value): RegistrationValidationResultInterface {
    // Validate a registration.
    if ($value instanceof RegistrationInterface) {
      // Setup configuration for those constraints that can take either a host
      // entity or a registration as the value, and require the host entity to
      // be passed as an option when the value is a registration.
      $configuration = ['hostEntity' => $this];

      // All registrations must have a host entity with settings.
      $pipeline = [
        'HostHasSettings' => $configuration,
      ];

      // Checks that apply to new registrations.
      if ($value->isNewToHost()) {
        $pipeline += [
          'HostIsOpen' => $configuration,
          'HostIsEnabled' => $configuration,
          'HostHasRoom' => $configuration,
        ];
      }

      // Checks that apply to all registrations.
      $pipeline += [
        'RegistrationIsEditable' => [],
        'RegistrationWithinMaximumSpaces' => $configuration,
        'RegistrationWithinCapacity' => [],
        'RegistrationAllowsRegistrant' => [],
        'UniqueRegistrant' => [],
      ];

      $validation_result = $this->validator()->execute('validate_registration', $pipeline, $value);
    }

    // Dispatch an event so other objects can be validated.
    $event = new RegistrationDataAlterEvent($validation_result ?? NULL, [
      'host_entity' => $this,
      'value' => $value,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_HOST_VALIDATION);

    /** @var \Drupal\registration\RegistrationValidationResultInterface $validation_result */
    $validation_result = $event->getData();

    // An object other than a registration was validated, but an event
    // subscriber to handle the validation was not provided.
    if (!isset($validation_result)) {
      throw new \InvalidArgumentException("Value could not be validated");
    }

    return $validation_result;
  }

  /**
   * Gets the active or held state IDs.
   *
   * @return array
   *   The active or held state IDs.
   */
  protected function getActiveOrHeldStateIds(): array {
    $state_ids = [];
    if ($registration_type = $this->getRegistrationType()) {
      $state_ids = array_keys($registration_type->getActiveOrHeldStates());
    }
    return $state_ids;
  }

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface|\Drupal\Core\Session\AccountProxy
   *   The current user.
   */
  protected function currentUser(): AccountInterface|AccountProxy {
    if (!isset($this->currentUser)) {
      $this->currentUser = $this->container()->get('current_user');
    }
    return $this->currentUser;
  }

  /**
   * Retrieves the renderer.
   *
   * @return \Drupal\Core\Render\Renderer
   *   The renderer.
   */
  protected function renderer(): Renderer {
    if (!isset($this->renderer)) {
      $this->renderer = $this->container()->get('renderer');
    }
    return $this->renderer;
  }

  /**
   * Retrieves the registration validator.
   *
   * @return \Drupal\registration\RegistrationValidatorInterface
   *   The registration validator.
   */
  protected function validator(): RegistrationValidatorInterface {
    if (!isset($this->validator)) {
      $this->validator = $this->container()->get('registration.validator');
    }
    return $this->validator;
  }

  /**
   * Returns the service container.
   *
   * This method is marked private to prevent subclasses from retrieving
   * services from the container through it. Instead,
   * \Drupal\Core\DependencyInjection\ContainerInjectionInterface should be used
   * for injecting services.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The service container.
   */
  private function container(): ContainerInterface {
    return \Drupal::getContainer();
  }

}
