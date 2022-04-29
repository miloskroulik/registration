<?php

namespace Drupal\registration\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the registration entity class.
 *
 * @ContentEntityType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   label_collection = @Translation("Registrations"),
 *   label_singular = @Translation("registration"),
 *   label_plural = @Translation("registrations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count registration",
 *     plural = "@count registrations",
 *   ),
 *   bundle_label = @Translation("Registration type"),
 *   handlers = {
 *     "event" = "Drupal\registration\Event\RegistrationEvent",
 *     "storage" = "Drupal\registration\RegistrationStorage",
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *     "access" = "Drupal\registration\RegistrationAccessControlHandler",
 *     "list_builder" = "Drupal\registration\RegistrationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\registration\Form\RegisterForm",
 *       "edit" = "Drupal\registration\Form\RegisterForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "register" = "Drupal\registration\Form\RegisterForm",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer registration",
 *   permission_granularity = "bundle",
 *   storage_schema = "Drupal\registration\RegistrationStorageSchema",
 *   base_table = "registration",
 *   entity_keys = {
 *     "id" = "registration_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/registration/{registration}",
 *     "edit-form" = "/registration/{registration}/edit",
 *     "delete-form" = "/registration/{registration}/delete",
 *     "collection" = "/admin/people/registrations"
 *   },
 *   bundle_entity_type = "registration_type",
 *   field_ui_base_route = "entity.registration_type.edit_form"
 * )
 */
class Registration extends ContentEntityBase implements RegistrationInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    $host_entity = $this->getHostEntity();
    if (!$this->isNew() && $host_entity) {
      return (string) t('Registration #@id for @label', [
        '@id' => $this->id(),
        '@label' => $host_entity->label(),
      ]);
    }
    elseif ($this->isNew() && $host_entity) {
      return (string) t('Registration for @label', [
        '@label' => $host_entity->label(),
      ]);
    }
    elseif (!$this->isNew()) {
      return (string) t('Registration #@id', [
        '@id' => $this->id(),
      ]);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousEmail(): string {
    if (!$this->get('anon_mail')->isEmpty()) {
      return $this->get('anon_mail')->first()->value;
    }
    return '';
  }

  public function getAuthor(): ?UserInterface {
    if (!$this->get('author_uid')->isEmpty()) {
      $author = $this->get('author_uid')->first()->entity;
      if ($author && $author->isAuthenticated()) {
        return $author;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorDisplayName(): ?string {
    if ($author = $this->getAuthor()) {
      return $author->getDisplayName();
    }
    // No author, must be an anonymous self registration.
    // Return the name of the anonymous site visitor.
    return Drupal::config('user.settings')->get('anonymous');
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    if (!$this->get('mail')->isEmpty()) {
      return $this->get('mail')->first()->value;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntity(): ?EntityInterface {
    if (!$this->get('host_entity')->isEmpty()) {
      return $this->get('host_entity')->first()->entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntityId(): int {
    if (!$this->get('entity_id')->isEmpty()) {
      return (int) $this->get('entity_id')->first()->value;
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntityTypeId(): string {
    if (!$this->get('entity_type_id')->isEmpty()) {
      return $this->get('entity_type_id')->first()->value;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getHostEntityTypeLabel(): ?string {
    if ($host_entity = $this->getHostEntity()) {
      $entity_type = $host_entity->getEntityType();
      if ($bundle_type = $entity_type->getBundleEntityType()) {
        return Drupal::entityTypeManager()
          ->getStorage($bundle_type)
          ->load($host_entity->bundle())
          ->label();
      }
      else {
        return $entity_type->getLabel();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistrantType(AccountInterface $account): ?string {
    $reg_type = NULL;
    if ($account->id() && ($account->id() == $this->getUserId())) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_ME;
    }
    elseif ($this->getUserId()) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_USER;
    }
    elseif ($this->getAnonymousEmail()) {
      $reg_type = self::REGISTRATION_REGISTRANT_TYPE_ANON;
    }
    return $reg_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpacesReserved(): int {
    if (!$this->get('count')->isEmpty()) {
      return (int) $this->get('count')->first()->value;
    }
    else {
      return 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): RegistrationTypeInterface {
    return $this->type->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?UserInterface {
    if (!$this->get('user_uid')->isEmpty()) {
      return $this->get('user_uid')->first()->entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId(): int {
    if (!$this->get('user_uid')->isEmpty()) {
      return (int) $this->get('user_uid')->first()->target_id;
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface {
    if ($this->isNew() || $this->get('workflow')->isEmpty()) {
      return $this->getType()->getWorkflow();
    }
    else {
      return $this->workflow->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): StateInterface {
    $workflow = $this->getWorkflow();
    if ($this->isNew() || $this->get('state')->isEmpty()) {
      return $workflow->getTypePlugin()->getState($this->getType()->getDefaultState());
    }
    else {
      return $workflow->getTypePlugin()->getState($this->get('state')->first()->value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): RegistrationInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return $this->getState()->isActive();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure host entity fields are set.
    foreach (['entity_type_id', 'entity_id'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required registration field "%s" is empty.', $field));
      }
    }

    // Author default.
    if ($this->get('author_uid')->isEmpty()) {
      $current_user = Drupal::service('current_user');
      if ($current_user->isAuthenticated()) {
        $this->set('author_uid', $current_user->id());
      }
    }
    // Count default.
    if ($this->get('count')->isEmpty()) {
      $this->set('count', 1);
    }
    // Mail default.
    if ($this->get('mail')->isEmpty()) {
      if ($user = $this->getUser()) {
        $this->set('mail', $user->getEmail());
      }
      else {
        $this->set('mail', $this->getAnonymousEmail());
      }
    }
    // Status default.
    if ($this->get('state')->isEmpty()) {
      $this->set('state', $this->getState()->id());
    }
    // Workflow default.
    if ($this->get('workflow')->isEmpty()) {
      $this->set('workflow', $this->getType()->getWorkflowId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Ensure registrations are backed by stored settings.
    if (!$update) {
      $host_entity = $this->getHostEntity();
      $storage = Drupal::entityTypeManager()->getStorage('registration_settings');
      $settings = $storage->loadSettingsForEntity($host_entity);
      if ($settings->isNew()) {
        $settings->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['workflow'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workflow'))
      ->setDescription(t('The workflow the registration is in.'))
      ->setSetting('target_type', 'workflow')
      ->setRequired(TRUE);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type ID'))
      ->setDescription(t('The machine name of the host entity type this registration is attached to.'))
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the host entity this registration is attached to.'))
      ->setSetting('unsigned', TRUE);

    $fields['host_entity'] = BaseFieldDefinition::create('registration_host_entity')
      ->setLabel(t('Host entity'))
      ->setDescription(t('The host entity for the registration.'))
      ->setComputed(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['anon_mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email to associate with this registration.'))
      ->setDisplayOptions('form', [
        'type' => 'email_default',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Spaces'))
      ->setDescription(t('How many spaces the registration should use towards the total capacity for the event.'))
      ->setSetting('min', 1)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('Select a user by typing their username to get a list of matches.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email address'))
      ->setDescription(t('The email (anonymous or authenticated) associated with this registration.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'email_mailto',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['author_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user who created the registration.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The registration status.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'registration_state_default',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'registration_state',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the registration was created.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the registration was last saved.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
