<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'registration_settings' formatter.
 *
 * This is an empty formatter only present to keep Field UI happy. Registration
 * settings are not displayed as content and are only used to control the
 * registration process.
 *
 * @FieldFormatter(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   field_types = {
 *     "registration_settings",
 *   }
 * )
 */
class RegistrationSettingsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    return [];
  }

}
