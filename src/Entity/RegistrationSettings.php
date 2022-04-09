<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the registration settings entity class.
 *
 * @ContentEntityType(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   handlers = {
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *   },
 *   base_table = "registration_entity",
 *   entity_keys = {
 *     "id" = "settings_id",
 *   },
 * )
 */
class RegistrationSettings extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type ID'))
      ->setDescription(t('The ID of the entity type this registration is attached to.'))
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity this registration is attached to.'))
      ->setSetting('unsigned', TRUE);

    $fields['capacity'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Capacity'))
      ->setDescription(t('The maximum number of users who can register.'))
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether registrations are open (1) or closed (0).'))
      ->setRequired(TRUE);

    $fields['send_reminder'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Send reminder'))
      ->setDescription(t('Whether reminder emails should be sent. This is set to 0 once the reminders are sent.'))
      ->setRequired(TRUE);

    $fields['reminder_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Reminder date'))
      ->setDescription(t('When to send the reminder.'))
      ->setRequired(FALSE);

    $fields['reminder_template'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Reminder template'))
      ->setDescription(t('The reminder email template.'))
      ->setRequired(FALSE);

    $fields['open'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Open date'))
      ->setDescription(t('Date to open registrations. Or NULL to open immediately.'))
      ->setRequired(FALSE);

    $fields['close'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Close date'))
      ->setDescription(t('Date to close registrations. Or NULL to never close automatically.'))
      ->setRequired(FALSE);

    $fields['settings'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Additional settings'))
      ->setDescription(t('A serialized string that stores additional registration settings.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
