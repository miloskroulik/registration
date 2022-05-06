<?php

namespace Drupal\registration\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'registration_settings' field type.
 *
 * @FieldType(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   description = @Translation("Creates registration settings."),
 *   category = @Translation("Entity registration"),
 *   default_widget = "registration_settings",
 *   default_formatter = "registration_settings",
 *   cardinality = 1,
 *   no_ui = TRUE,
 *   serialized_property_names = {
 *     "value"
 *   },
 * )
 */
class RegistrationSettingsItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'text',
          'size' => 'medium',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Settings'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '' || $value === serialize([]);
  }

}
