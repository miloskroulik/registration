<?php

namespace Drupal\registration\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the registration entity class.
 *
 * @ContentEntityType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   label_collection = @Translation("Registrations"),
 *   label_singular = @Translation("Registration"),
 *   label_plural = @Translation("Registrations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count registration",
 *     plural = "@count registrations",
 *   ),
 *   bundle_label = @Translation("Registration type"),
 *   handlers = {
 *     "event" = "Drupal\registration\Event\RegistrationEvent",
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "query_access" = "Drupal\entity\QueryAccess\QueryAccessHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "list_builder" = "Drupal\registration\RegistrationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\registration\Form\RegistrationForm",
 *       "add" = "Drupal\registration\Form\RegistrationForm",
 *       "edit" = "Drupal\registration\Form\RegistrationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   admin_permission = "administer registration",
 *   permission_granularity = "bundle",
 *   storage_schema = "Drupal\registration\RegistrationStorageSchema",
 *   base_table = "registration",
 *   data_table = "registration_field_data",
 *   entity_keys = {
 *     "id" = "registration_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/registration/{registration}",
 *     "add-page" = "/registration/add",
 *     "add-form" = "/registration/add/{registration_type}",
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
  public function label():string {
    if (!$this->isNew()) {
      return (string) t('Registration #@id', ['@id' => $this->id()]);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorDisplayName(): string|null {
    if (!$this->isNew()) {
      $user = $this->entityTypeManager->getStorage('user')->load($this->author_uid);
      if ($user) {
        return $user->getDisplayName();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface {
    if ($this->isNew() || $this->get('workflow')->isEmpty()) {
      return $this->type->entity->getWorkflow();
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
      return $workflow->getTypePlugin()->getState($this->type->entity->getDefaultState());
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
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if ($this->get('workflow')->isEmpty()) {
      $this->set('workflow', $this->type->entity->getWorkflowId());
    }
    if ($this->get('state')->isEmpty()) {
      $this->set('state', $this->getState()->id());
    }
    if ($this->get('author_uid')->isEmpty()) {
      $this->set('author_uid', Drupal::service('current_user')->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
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
      ->setDescription(t('The ID of the entity type this registration is attached to.'))
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity this registration is attached to.'))
      ->setSetting('unsigned', TRUE);

    $fields['anon_mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Anonymous email'))
      ->setDescription(t('The email address for anonymous registrations.'))
      ->setDisplayOptions('form', [
        'type' => 'email_default',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Count'))
      ->setDescription(t('How many spaces the registration should use towards the total capacity for the event.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Registrant'))
      ->setDescription(t('The registrant for authenticated user registrations.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setDisplayConfigurable('form', TRUE)
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
        'weight' => 10,
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
      ->setTranslatable(TRUE);

    return $fields;
  }

}
