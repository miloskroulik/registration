<?php

namespace Drupal\registration\Entity;

use Drupal;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the registration settings entity class.
 *
 * @ContentEntityType(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   handlers = {
 *     "storage" = "Drupal\registration\RegistrationSettingsStorage",
 *     "storage_schema" = "Drupal\registration\RegistrationStorageSchema",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "registration_entity",
 *   entity_keys = {
 *     "id" = "settings_id",
 *   },
 * )
 */
class RegistrationSettings extends ContentEntityBase {

  /**
   * Gets the entity ID of the host entity that the settings are for.
   *
   * @return int
   *   The host entity ID.
   */
  public function getHostEntityId(): int {
    if (!$this->get('entity_id')->isEmpty()) {
      return (int) $this->get('entity_id')->first()->value;
    }
    return 0;
  }

  /**
   * Gets the entity type ID of the host entity that the settings are for.
   *
   * @return string
   *   The host entity type ID, for example "node".
   */
  public function getHostEntityTypeId(): string {
    if (!$this->get('entity_type_id')->isEmpty()) {
      return $this->get('entity_type_id')->first()->value;
    }
    return 0;
  }

  /**
   * Gets a settings value for a given key.
   *
   * @param string $key
   *   The setting name, for example "status", "reminder date" etc.
   *
   * @return mixed
   *   The setting value. The data type depends on the key.
   */
  public function getSetting(string $key): mixed {

    // Check the main settings.
    if ($this->hasField($key) && !$this->get($key)->isEmpty()) {
      // Registration settings entity has the setting.
      $setting = $this->get($key)->first()->getValue();
      return $setting['value'];
    }

    // Check for an additional setting.
    // Extract from the serialized settings property.
    if (!$this->hasField($key)) {
      if (!$this->get('settings')->isEmpty()) {
        $settings = $this->get('settings')->first()->getValue();
        if (!empty($settings)) {
          $settings = unserialize($settings['value']);
          if (!empty($settings[$key])) {
            // Registration settings entity has the additional setting.
            return $settings[$key];
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Initialize main settings from field configuration.
   *
   * @return $this
   *   The settings entity.
   */
  public function initFromConfig(EntityInterface $host_entity) {
    $keys = [
      'status',
      'capacity',
    ];

    $entity_type_id = $host_entity->getEntityTypeId();
    $entity_type = Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $registration_manager = Drupal::service('registration.manager');
    $registration_field = $registration_manager->getRegistrationField($host_entity);

    foreach ($keys as $key) {
      $value = $registration_manager->getFieldWidgetSetting($entity_type, $registration_field, $key);
      $this->set($key, $value);
    }

    return $this;
  }

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

  /**
   * Invalidates an entity's cache tag upon save.
   *
   * @param bool $update
   *   TRUE if the entity has been updated, or FALSE if it has been inserted.
   */
  protected function invalidateTagsOnSave($update) {
    parent::invalidateTagsOnSave($update);

    // Invalid the host entity cache tag when adding new settings.
    // Needed to rebuild registration related elements. After this,
    // the settings entity is included in cacheability so rebuilds
    // will happen through the default cache handling.
    if (!$update) {
      $host_entity_tag = $this->getHostEntityTypeId() . ':' . $this->getHostEntityId();
      Cache::invalidateTags([$host_entity_tag]);
    }
  }

}
