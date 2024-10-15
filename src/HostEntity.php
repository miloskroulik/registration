<?php

namespace Drupal\registration;

use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the host entity.
 *
 * This is a pseudo-entity wrapper around a real entity.
 */
class HostEntity implements HostEntityInterface {

  use StringTranslationTrait;

  use DependencySerializationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected AccountProxy $currentUser;

  /**
   * The real entity that is wrapped.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The entity field manager.
   *
   * @var \Drupal\registration\RegistrationFieldManagerInterface
   */
  protected RegistrationFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The settings for the host entity.
   *
   * @var \Drupal\registration\Entity\RegistrationSettings|null
   */
  protected RegistrationSettings|NULL $settings;

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
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $handler = $this->entityTypeManager()->getHandler($this->getEntityTypeId(), 'registration_host_access');
    return $handler->access($this, $operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function bundle(): string {
    return $this->getEntity()->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->getEntity()->getEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabel(): string {
    $entity_type = $this->getEntity()->getEntityType();
    if ($bundle_type = $entity_type->getBundleEntityType()) {
      return $this->entityTypeManager()
        ->getStorage($bundle_type)
        ->load($this->bundle())
        ->label();
    }
    else {
      return $entity_type->getLabel();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string|int|NULL {
    return $this->getEntity()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isNew(): bool {
    return $this->getEntity()->isNew();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getEntity()->label();
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
    $count = $this->getRegistrationQuery()->count()->execute();

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
  public function getRegistrationList(array $states = [], ?string $langcode = NULL): array {
    $properties = [];
    if (!empty($states)) {
      $properties['state'] = $states;
    }

    // Filter on host entity language if a language code was not specified.
    if (!$langcode) {
      $langcode = $this->getEntity()->language()->getId();
    }
    // Do not filter on language if it would be "undefined" since nothing would
    // match.
    if ($langcode != 'und') {
      $properties['langcode'] = $langcode;
    }
    $ids = $this->getRegistrationQuery($properties)->execute();
    return $ids ? $this->entityTypeManager()->getStorage('registration')->loadMultiple($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationQuery(array $properties = [], ?AccountInterface $account = NULL, $email = NULL): QueryInterface {
    $query = $this->entityTypeManager()->getStorage('registration')->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->condition('entity_id', $this->id());

    // Add property conditions using same logic as
    // EntityStorageBase::loadByProperties().
    foreach ($properties as $name => $value) {
      // Cast scalars to array so we can consistently use an IN condition.
      $query->condition($name, (array) $value, 'IN');
    }

    // Add special handling for identifying the registrant.
    $emails = [];
    $uids = [];
    if ($account) {
      $uids[] = $account->id();
      if ($account->getEmail()) {
        $emails[] = $account->getEmail();
      }
    }
    if ($email) {
      $emails[] = $email;
      // Check for other users based on provided email.
      if (!$account || $account->getEmail() !== $email) {
        $user_query = $this->entityTypeManager()->getStorage('user')->getQuery()->accessCheck(FALSE);
        $uids = array_merge($uids, $user_query->condition('mail', $email)->execute());
      }
    }
    if ($emails || $uids) {
      $orGroup = $query->orConditionGroup();
      if ($emails) {
        $orGroup->condition('anon_mail', $emails, 'IN');
      }
      if ($uids) {
        $orGroup->condition('user_uid', $uids, 'IN');
      }
      $query->condition($orGroup);
    }

    return $query;
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
  public function getSetting(string $key): mixed {
    if ($settings = $this->getSettings()) {
      return $settings->getSetting($key);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): ?RegistrationSettings {
    if (!isset($this->settings)) {
      $this->settings = NULL;
      if ($this->getRegistrationTypeBundle()) {
        /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
        $storage = $this->entityTypeManager()->getStorage('registration_settings');
        $this->settings = $storage->loadSettingsForHostEntity($this);
      }
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->needsCapacityCheck($spaces, $registration)) {
      $capacity = $this->getSetting('capacity');
      if ($capacity) {
        $projected_usage = $this->getActiveSpacesReserved($registration) + $spaces;
        if (($capacity - $projected_usage) < 0) {
          return FALSE;
        }
      }
    }
    return TRUE;
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
  public function isEnabledForRegistration(int $spaces = 1, ?RegistrationInterface $registration = NULL, array &$errors = []): bool {
    $settings = $this->getSettings();
    if (!$settings) {
      $errors['settings'] = $this->t('Host entity settings not available for %label.', [
        '%label' => $this->label(),
      ]);
      return FALSE;
    }
    $enabled = $settings->getSetting('status');

    // Only explore other settings if main status is enabled.
    if ($enabled) {
      // Check maximum allowed spaces per registration.
      $maximum_spaces = (int) $settings->getSetting('maximum_spaces');
      if ($maximum_spaces && ($spaces > $maximum_spaces)) {
        $enabled = FALSE;
        $errors['maximum_spaces'] = $this->formatPlural($maximum_spaces,
          'You may not register for more than 1 space.',
          'You may not register for more than @count spaces.', [
            '@count' => $maximum_spaces,
          ]);
      }

      // Check capacity.
      if (!$this->hasRoom($spaces, $registration)) {
        $enabled = FALSE;
        $errors['capacity'] = $this->t('Sorry, unable to register for %label due to: insufficient spaces remaining.', [
          '%label' => $this->label(),
        ]);
      }

      // Check open date.
      if ($this->isBeforeOpen()) {
        $enabled = FALSE;
        $errors['open'] = $this->t('Registration for %label is not open yet.', [
          '%label' => $this->label(),
        ]);
      }

      // Check close date.
      if ($this->isAfterClose()) {
        $enabled = FALSE;
        $errors['close'] = $this->t('Registration for %label is closed.', [
          '%label' => $this->label(),
        ]);
      }
    }
    else {
      $errors['status'] = $this->t('Registration for %label is disabled.', [
        '%label' => $this->label(),
      ]);
    }

    // Allow other modules to override the result.
    $event = new RegistrationDataAlterEvent($enabled, [
      'host_entity' => $this,
      'settings' => $settings,
      'spaces' => $spaces,
      'registration' => $registration,
      'errors' => $errors,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_ENABLED);
    if ($event->hasErrors()) {
      $errors = $event->getErrors();
    }
    return $event->getData() ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmailRegistered(string $email): bool {
    @trigger_error('Calling HostEntity::isEmailRegistered() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
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
    @trigger_error('Calling HostEntity::isEmailRegisteredInStates() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
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
    @trigger_error('Calling HostEntity::isUserRegistered() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
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
    @trigger_error('Calling HostEntity::isUserRegisteredInStates() is deprecated in registration:3.1.5 and will be removed before registration:4.0.0. See https://www.drupal.org/node/3465690', E_USER_DEPRECATED);
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
  public function isRegistrant(?AccountInterface $account = NULL, $email = NULL, array $states = []): bool {
    if (!$account && !$email) {
      throw new \InvalidArgumentException("Either an account or an email must be passed to HostEntity::isRegistrant().");
    }

    // Default to active or held states if none specified.
    if (!$states && $registration_type = $this->getRegistrationType()) {
      $states = array_keys($registration_type->getActiveOrHeldStates());
    }
    // Ensure we have active states before querying against them.
    if (empty($states)) {
      return FALSE;
    }

    $query = $this->getRegistrationQuery(['state' => $states], $account, $email);
    return (!empty($query->execute()));
  }

  /**
   * {@inheritdoc}
   */
  public function isBeforeOpen(): bool {
    // Initialize the current time.
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);

    // Check open date.
    $open = $this->getSetting('open');
    if ($open) {
      $open = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $open, $storage_timezone);
    }
    return ($open && ($now < $open));
  }

  /**
   * {@inheritdoc}
   */
  public function isAfterClose(): bool {
    // Initialize the current time.
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);

    // Check close date.
    $close = $this->getSetting('close');
    if ($close) {
      $close = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $close, $storage_timezone);
    }
    return ($close && ($now >= $close));
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
   * Retrieves the entity field manager.
   *
   * @return \Drupal\registration\RegistrationFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager(): RegistrationFieldManagerInterface {
    if (!isset($this->entityFieldManager)) {
      $this->entityFieldManager = $this->container()->get('registration.field_manager');
    }
    return $this->entityFieldManager;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = $this->container()->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the event dispatcher.
   *
   * @return \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher(): EventDispatcherInterface {
    if (!isset($this->eventDispatcher)) {
      $this->eventDispatcher = $this->container()->get('event_dispatcher');
    }
    return $this->eventDispatcher;
  }

  /**
   * Determines if a registration needs a capacity check.
   *
   * New registrations are always checked. Existing registrations are checked
   * in cases when the spaces reserved or registration state have changed.
   *
   * @param int $spaces
   *   The number of spaces requested.
   * @param \Drupal\registration\Entity\RegistrationInterface|null $registration
   *   (optional) If set, an existing registration to check.
   *
   * @return bool
   *   TRUE if a check is needed, FALSE otherwise.
   */
  protected function needsCapacityCheck(int $spaces, ?RegistrationInterface $registration): bool {
    $needs_check = TRUE;
    if ($registration && !$registration->isNew()) {
      // The check can be skipped for an existing registration if it is canceled
      // or in the process of being canceled.
      if ($registration->getState()->isCanceled()) {
        $needs_check = FALSE;
      }
      else {
        // The check can be skipped for an existing registration if its spaces
        // reserved and registration state fields are unchanged. Skipping the
        // check in this case allows an existing registration to be editable
        // even if the overall capacity has been exceeded by the actions of
        // some other module.
        $original = $this->entityTypeManager()->getStorage('registration')->loadUnchanged($registration->id());
        // A reduction to spaces reserved should not trigger a capacity check.
        $spaces_changed = ($spaces > $original->getSpacesReserved());
        $status_changed = ($registration->getState()->id() != $original->getState()->id());
        $needs_check = $spaces_changed || $status_changed;
      }
    }
    return $needs_check;
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
