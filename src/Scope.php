<?php

namespace Drupal\registration;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\Event\RegistrationDataAlterEvent;
use Drupal\registration\Event\RegistrationEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the class for the scope entity.
 *
 * This is abstract as the getSettings() method is not implemented.
 */
abstract class Scope implements ScopeInterface {

  use StringTranslationTrait;

  use DependencySerializationTrait;

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
   * The settings for the host entity.
   *
   * @var \Drupal\registration\Entity\RegistrationSettings|null
   */
  protected RegistrationSettings|NULL $settings;

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::neutral();
    foreach($this->getScopes() as $scope) {
      $result = $result->orIf($scope->access($operation, $account, $return_as_object));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesReserved(?array $state_ids = []): int {
    if (empty($state_ids)) {
      $state_ids = $this->getActiveOrHeldStateIds();
    }
    // Ensure we have active states before querying against them.
    if (empty($state_ids)) {
      return 0;
    }

    $query = $this->entityTypeManager()
      ->getStorage('registration')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('state', $state_ids, 'IN');

    $this->addHostConditions($query);
    $query->aggregate('count', 'SUM');

    $result = $query->execute();
    $spaces = !empty($result[0]['count_sum']) ? $result[0]['count_sum'] : 0;

    // Allow other modules to alter the number of spaces reserved.
    $event = new RegistrationDataAlterEvent($spaces, [
      'scope' => $this,
      'state_ids' => $state_ids,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_SPACES_RESERVED);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCloseTime(): ?int {
    $times = [];

    $close = $this->getSetting('close');
    if ($close) {
      $date = new DrupalDateTime($close, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $times[] = $date->getTimestamp();
    }

    foreach ($this->getScopes() as $scope) {
      $times[] = $scope->getCloseTime();
    }
    $times = array_filter($times);
    if (!empty($times)) {
      return min($times);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOpenTime(): ?int {
    $times = [];

    $open = $this->getSetting('open');
    if ($open) {
      $date = new DrupalDateTime($open, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $times[] = $date->getTimestamp();
    }

    foreach ($this->getScopes() as $scope) {
      $times[] = $scope->getOpenTime();
    }
    $times = array_filter($times);
    if (!empty($times)) {
      return max($times);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCapacity(?string $capacity_type = 'capacity'): ?int {
    $spaces = [(int) $this->getSetting($capacity_type)];
    foreach ($this->getScopes() as $scope) {
      $spaces[] = $scope->getCapacity($capacity_type);
    }
    $spaces = array_filter($spaces);
    if (!empty($spaces)) {
      return min($spaces);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumSpaces(): ?int {
    $spaces = [(int) $this->getSetting('maximum_spaces')];
    foreach ($this->getScopes() as $scope) {
      $spaces[] = $scope->getMaximumSpaces();
    }
    $spaces = array_filter($spaces);
    if (!empty($spaces)) {
      return min($spaces);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesAvailable($capacity_type = 'capacity'): ?int {
    if ($capacity = $this->getCapacity($capacity_type)) {
      $spaces_remaining = $capacity - $this->getSpacesReserved();
      // Allow other modules to alter the number of spaces available.
      $event = new RegistrationDataAlterEvent($spaces_remaining, [
        'scope' => $this,
      ]);
      $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_SPACES_AVAILABLE);
      return $event->getData() ?? NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHosts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationCount(): int {
    $count = $this->getRegistrationQuery()->count()->execute();

    // Allow other modules to alter the count.
    $event = new RegistrationDataAlterEvent($count, [
      'scope' => $this,
    ]);
    $this->eventDispatcher()->dispatch($event, RegistrationEvents::REGISTRATION_ALTER_REGISTRATIONS_COUNT);
    return $event->getData() ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrationList(array $state_ids = [], ?string $langcode = NULL): array {
    $properties = [];
    if (!empty($state_ids)) {
      $properties['state'] = $state_ids;
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
  public function getRegistrationQuery(array $properties = [], ?AccountInterface $account = NULL, ?string $email = NULL): QueryInterface {
    $query = $this->entityTypeManager()->getStorage('registration')->getQuery()
      ->accessCheck(FALSE);

    $this->addHostConditions($query);

    // Add property conditions using same logic as
    // EntityStorageBase::loadByProperties().
    foreach ($properties as $name => $value) {
      // Cast scalars to array, so we can consistently use an IN condition.
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
  public function getSetting(string $key): mixed {
    if ($settings = $this->getSettings()) {
      return $settings->getSetting($key);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRoom(int $spaces = 1, ?RegistrationInterface $registration = NULL): bool {
    if ($this->needsCapacityCheck($spaces, $registration)) {
      $spaces_available = $this->getSpacesAvailable();
      if ($registration) {
        // @todo assumes getSpacesReserved  is the previously saved value.
        $spaces_available += $registration->getSpacesReserved();
      }
      if ($spaces > $spaces_available) {
        return FALSE;
      }
      foreach ($this->getScopes() as $scope) {
        if (!$scope->hasRoom($spaces, $registration)) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailableForRegistration(bool $return_as_object = FALSE): bool|RegistrationValidationResultInterface {
    $unavailable = new RegistrationValidationResult([], $this);
    foreach ($this->getHosts() as $host) {
      $result = $host->isAvailableForRegistration($return_as_object);
      $available = $return_as_object ? $result->isValid() : $result;
      if ($available) {
        // Return either TRUE or a validation result with no violations.
        return $result;
      }
      $unavailable->addCacheableDependency($result->getCacheableMetadata());
    }
    if ($return_as_object) {
      $unavailable->addViolation($this->t('Registration is not available for %label.', ['%label' => $this->label()], NULL, NULL, NULL, 'hosts_unavailable', $this->t('Registration not available.')));
      return $unavailable;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    $statuses = [(bool) $this->getSetting('status')];
    foreach ($this->getScopes() as $scope) {
      $statuses[] = $scope->isEnabled();
    }
    return !in_array(FALSE, $statuses, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isMultipleRegistrationAllowed(): bool {
    $allows = [(bool) $this->getSetting('multiple_registrations')];
    foreach ($this->getScopes() as $scope) {
      $allows[] = $scope->isMultipleRegistrationAllowed();
    }
    return !in_array(FALSE, $allows, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isRegistrant(?AccountInterface $account = NULL, ?string $email = NULL, array $state_ids = []): bool {
    if (!$account && !$email) {
      throw new \InvalidArgumentException("Either an account or an email must be passed to HostEntity::isRegistrant().");
    }

    // Default to active or held states if none specified.
    if (!$state_ids) {
      $state_ids = $this->getActiveOrHeldStateIds();
    }
    // Ensure we have active states before querying against them.
    if (empty($state_ids)) {
      return FALSE;
    }

    $query = $this->getRegistrationQuery(['state' => $state_ids], $account, $email);
    return (!empty($query->execute()));
  }

  /**
   * {@inheritdoc}
   */
  public function isBeforeOpen(): bool {
    // Check open date.
    $open = $this->getOpenTime();
    if ($open) {
      $now = $this->container()->get('datetime.time')->getCurrentTime();
    }
    return ($open && ($now < $open));
  }

  /**
   * {@inheritdoc}
   */
  public function isAfterClose(): bool {
    // Check close date.
    $close = $this->getCloseTime();
    if ($close) {
      $now = $this->container()->get('datetime.time')->getCurrentTime();
    }
    return ($close && ($now >= $close));
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes(): array {
    return [];
  }

  /**
   * Determines if a registration needs a capacity check.
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
    if (empty($this->getCapacity())) {
      return FALSE;
    }
    if ($registration) {
      return $registration->requiresCapacityCheck() || ($spaces > $registration->getSpacesReserved());
    }
    return TRUE;
  }

  /**
   * Adds host conditions to a query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to modify.
   */
  protected function addHostConditions($query): void {
    $hosts = $this->getHosts();
    if ($hosts) {
      $hostsGroup = $query->orConditionGroup();
      foreach ($hosts as $host) {
        $hostGroup = $query->andConditionGroup();
        $hostGroup->condition('entity_type_id', $host->getEntityTypeId());
        $hostGroup->condition('entity_id', $host->id());
        $hostsGroup->condition($hostGroup);
      }
      $query->condition($hostsGroup);
    }
  }

  /**
   * Gets the active or held state IDs.
   *
   * @return array
   *   The active or held state IDs.
   */
  protected function getActiveOrHeldStateIds(): array {
    // @todo provide a better default, maybe by checking all states in system for active/held.
    return [];
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
