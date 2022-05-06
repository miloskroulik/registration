<?php

namespace Drupal\registration;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\registration\Entity\RegistrationSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the storage handler class for registration settings entities.
 */
class RegistrationSettingsStorage extends RegistrationStorage {

  /**
   * The UUID interface.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): RegistrationSettingsStorage {
    $instance = parent::createInstance($container, $entity_type);
    $instance->uuid = $container->get('uuid');
    return $instance;
  }

  /**
   * Load the settings entity for a given host entity.
   *
   * Creates one if settings do not exist yet.
   *
   * @param \Drupal\registration\HostEntityInterface $host_entity
   *   The host entity.
   * @param string|null $langcode
   *   (optional) Force the language the settings should use.
   *
   * @return \Drupal\registration\Entity\RegistrationSettings
   *   The settings entity.
   */
  public function loadSettingsForHostEntity(HostEntityInterface $host_entity, string $langcode = NULL): RegistrationSettings {
    // Determine the language if needed. If the host entity is in the site
    // default language, but the site is currently using a different language,
    // then switch to the current language, since this means the host entity
    // has not been translated to the site language yet.
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
    $original_langcode = $langcode;
    if (!$langcode) {
      $langcode = $host_entity->getEntity()->language()->getId();
      $site_langcode = $this->languageManager->getCurrentLanguage()->getId();
      if (($langcode == $default_langcode) && ($langcode != $site_langcode)) {
        $langcode = $site_langcode;
      }
    }

    // Look for settings for the given host entity and language.
    $values = [
      'entity_type_id' => $host_entity->getEntityTypeId(),
      'entity_id' => $host_entity->id(),
      'langcode' => $langcode,
    ];
    $settings = $this->loadByProperties($values);
    if (empty($settings)) {
      // Unable to find the settings for the host entity and language. If the
      // language requested is not the default for the site, try the default
      // and use it as a basis for creating a new settings entity for the
      // requested language.
      if ($langcode != $default_langcode) {
        $values['langcode'] = $default_langcode;
        $settings = $this->loadByProperties($values);
        if (!empty($settings)) {
          /** @var \Drupal\registration\Entity\RegistrationSettings $settings_entity */
          $settings_entity = reset($settings);
          // Initialize with default settings from field configuration.
          // These are language specific overrides for the settings.
          // For example, the reminder template is language specific.
          $settings_entity->initFromDefaults($host_entity, $langcode);
          // Set language to the override.
          $settings_entity->set('langcode', $langcode);
          // Make it new, otherwise save will overwrite the original.
          $settings_entity->set('settings_id', NULL);
          $settings_entity->set('uuid', $this->uuid->generate());
          $settings_entity->enforceIsNew();
          return $settings_entity;
        }
      }
    }

    if (empty($settings)) {
      // Settings entity still does not exist yet. Create it.
      $values['langcode'] = $langcode;
      /** @var \Drupal\registration\Entity\RegistrationSettings $settings_entity */
      $settings_entity = $this->create($values);

      // Add defaults for the site default language if different from the host
      // entity language.
      if ($langcode != $default_langcode) {
        if ($untranslated = $host_entity->getUntranslated()) {
          $settings_entity->initFromDefaults($untranslated);
        }
        else {
          $settings_entity->initFromDefaults($host_entity);
        }
      }

      // Add default overrides for the host entity language.
      $settings_entity->initFromDefaults($host_entity, $original_langcode);
    }
    else {
      // The entity exists, return it.
      $settings_entity = reset($settings);
    }

    return $settings_entity;
  }

}
