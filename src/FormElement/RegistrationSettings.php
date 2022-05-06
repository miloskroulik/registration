<?php

namespace Drupal\registration\FormElement;

use Drupal\config_translation\FormElement\FormElementBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Config\LanguageConfigOverride;

/**
 * Defines the registration settings element for configuration translation.
 *
 * This is a serialized field that needs to be unserialized for display and
 * serialized again for config storage. Without this custom form element,
 * the translator would be presented with a text field containing the
 * serialized data.
 *
 * @see \Drupal\registration\Plugin\Field\FieldType\RegistrationSettingsItem
 */
class RegistrationSettings extends FormElementBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected EntityFieldManager $entityFieldManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(TypedDataInterface $schema): RegistrationSettings {
    $instance = parent::create($schema);
    $container = \Drupal::getContainer();
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceElement(LanguageInterface $source_language, $source_config): array {
    // Render display elements from the fields defined for the entity type.
    $value = is_string($source_config) ? unserialize($source_config) : [];

    $element = [];
    foreach ($this->getTranslatableFields() as $field_definition) {
      $build = [];
      $field_name = $field_definition->getName();
      switch ($field_definition->getType()) {
        case 'string':
        case 'string_long':
          $build = [
            '#plain_text' => $value[$field_name] ?? '',
          ];
          break;

        case 'text_long':
          $build = [
            '#type' => 'processed_text',
            '#text' => $value[$field_name]['value'] ?? '',
            '#format' => $value[$field_name]['format'] ?? filter_default_format(),
          ];
          break;
      }
      if (!empty($build)) {
        $element[$field_name] = [
          '#title' => $field_definition->getLabel(),
          '#type' => 'item',
          '#markup' => $this->renderer->render($build),
        ];
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationElement(LanguageInterface $translation_language, $source_config, $translation_config): array {
    $value = is_string($translation_config) ? unserialize($translation_config) : [];
    if (empty($value)) {
      $value = is_string($source_config) ? unserialize($source_config) : [];
    }

    // Render form API elements for the fields defined for the entity type.
    // Must use #tree so the submitted values are part of the parent config.
    $element = ['#tree' => TRUE];
    foreach ($this->getTranslatableFields() as $field_definition) {
      $field_name = $field_definition->getName();
      switch ($field_definition->getType()) {
        case 'string':
          $element[$field_name] = [
            '#title' => $field_definition->getLabel(),
            '#type' => 'textfield',
            '#default_value' => $value[$field_name] ?? '',
          ];
          break;

        case 'string_long':
          $element[$field_name] = [
            '#title' => $field_definition->getLabel(),
            '#type' => 'textarea',
            '#default_value' => $value[$field_name] ?? '',
          ];
          break;

        case 'text_long':
          $element[$field_name] = [
            '#title' => $field_definition->getLabel(),
            '#type' => 'text_format',
            '#default_value' => $value[$field_name]['value'] ?? '',
            '#format' => $value[$field_name]['format'] ?? filter_default_format(),
          ];
          break;
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfig(Config $base_config, LanguageConfigOverride $config_translation, $config_values, $base_key = NULL) {
    $config_values = serialize($config_values);
    parent::setConfig($base_config, $config_translation, $config_values, $base_key);
  }

  /**
   * Gets all translatable fields for a registration settings entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of fields indexed by field machine name.
   */
  protected function getTranslatableFields(): array {
    $field_definitions = $this->entityFieldManager
      ->getFieldDefinitions('registration_settings', 'registration_settings');

    $fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) {
      // Take any translatable base field, or any field added via Field UI.
      return $field_definition->isTranslatable() || ($field_definition instanceof FieldConfig);
    });

    return $fields;
  }

}
