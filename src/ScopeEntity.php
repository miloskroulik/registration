<?php

namespace Drupal\registration;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the class for the scope entity.
 *
 * This is a pseudo-entity wrapper around a real entity.
 */
class ScopeEntity extends Scope implements ScopeEntityInterface {

  use StringTranslationTrait;

  use DependencySerializationTrait;

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
   * The settings for the host entity.
   *
   * @var \Drupal\registration\Entity\RegistrationSettings|null
   */
  protected RegistrationSettings|NULL $settings;

  /**
   * The registration validator.
   *
   * @var \Drupal\registration\RegistrationValidatorInterface
   */
  protected RegistrationValidatorInterface $validator;

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
    $result = $handler->access($this, $operation, $account, $return_as_object);
    foreach($this->getScopes() as $scope) {
      $result = $result->orIf($scope->access($operation, $account, $return_as_object));
    }
    return $result;
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
  public function getRegistrationList(array $state_ids = [], ?string $langcode = NULL): array {
    // Filter on host entity language if a language code was not specified.
    if (!$langcode) {
      $langcode = $this->getEntity()->language()->getId();
    }
    return parent::getRegistrationList($state_ids, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): ?RegistrationSettings {
    if (!isset($this->settings)) {
      // todo
      $this->settings = NULL;
    }
    return $this->settings;
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
