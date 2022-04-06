<?php

namespace Drupal\registration\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the Registration field type.
 *
 * @FieldType(
 *   id = "registration",
 *   label = @Translation("Registration"),
 *   description = @Translation("Enables registrations of a selected type for an entity."),
 *   category = @Translation("Entity registration"),
 *   default_widget = "registration_settings",
 *   default_formatter = "registration_type",
 *   cardinality = 1,
 * )
 */
class RegistrationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'registration_type' => [
          'description' => 'The type of registration that should be enabled.',
          'type' => 'varchar',
          'length' => 32,
          'not null' => FALSE,
        ],
        'capacity' => [
          'description' => 'Maximum number of users who can register.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'status' => [
          'description' => 'Boolean indicating if registrations are open (1) or closed (0).',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 1,
        ],
        'send_reminder' => [
          'description' => 'Boolean indicating whether reminder emails should be sent. This is set to 0 once the reminders are sent.',
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
        ],
        'reminder_date' => [
          'description' => 'Date to send the reminder on.',
          'type' => 'datetime',
          'mysql_type' => 'datetime',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'varchar',
          'sqlsrv_type' => 'smalldatetime',
          'not null' => FALSE,
        ],
        'reminder_template' => [
          'description' => 'Reminder email template.',
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'open' => [
          'description' => 'Date to open registrations. Or NULL to open immediately.',
          'type' => 'datetime',
          'mysql_type' => 'datetime',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'varchar',
          'sqlsrv_type' => 'smalldatetime',
          'not null' => FALSE,
        ],
        'close' => [
          'description' => 'Date to close registrations. Or NULL to never close automatically.',
          'type' => 'datetime',
          'mysql_type' => 'datetime',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'varchar',
          'sqlsrv_type' => 'smalldatetime',
          'not null' => FALSE,
        ],
        'settings' => [
          'type' => 'blob',
          'not null' => TRUE,
          'size' => 'big',
          'serialize' => TRUE,
          'description' => 'A serialized object that stores additional registration settings.',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['registration_type'] = DataDefinition::create('string')
      ->setLabel(t('Registration type'))
      ->setDescription(t('The type of registration to create for an entity.'))
      ->setRequired(TRUE);

    $properties['capacity'] = DataDefinition::create('integer')
      ->setLabel(t('Capacity'))
      ->setDescription(t('The maximum number of users who can register.'))
      ->setRequired(TRUE);

    $properties['status'] = DataDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether registrations are open (1) or closed (0).'))
      ->setRequired(TRUE);

    $properties['send_reminder'] = DataDefinition::create('boolean')
      ->setLabel(t('Send reminder'))
      ->setDescription(t('Whether reminder emails should be sent. This is set to 0 once the reminders are sent.'))
      ->setRequired(TRUE);

    $properties['reminder_date'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Reminder date'))
      ->setDescription(t('When to send the reminder.'))
      ->setRequired(FALSE);

    $properties['reminder_template'] = DataDefinition::create('string')
      ->setLabel(t('Reminder template'))
      ->setDescription(t('The reminder email template.'))
      ->setRequired(FALSE);

    $properties['open'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Open date'))
      ->setDescription(t('Date to open registrations. Or NULL to open immediately.'))
      ->setRequired(FALSE);

    $properties['close'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Close date'))
      ->setDescription(t('Date to close registrations. Or NULL to never close automatically.'))
      ->setRequired(FALSE);

    $properties['settings'] = DataDefinition::create('string')
      ->setLabel(t('Additional settings'))
      ->setDescription(t('A serialized string that stores additional registration settings.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('registration_type')->getValue();
    return ($value === NULL) || ($value === '');
  }

}
