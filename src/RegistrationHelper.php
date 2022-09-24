<?php

namespace Drupal\registration;

/**
 * Defines a utility class.
 */
class RegistrationHelper {

  /**
   * Updates an array of registration links with current language.
   *
   * Used on entity operations and form action links, since both have the
   * same structure. Note the interface language is being used here, since
   * registration maintenance can be considered an admin task.
   *
   * @param array $links
   *   The links to update.
   */
  public static function applyInterfaceLanguageToLinks(array &$links) {
    foreach ($links as $index => &$link) {
      switch ($index) {
        case 'edit':
        case 'delete':
          $url_key = !empty($link['#type']) ? '#url' : 'url';
          if (!empty($link[$url_key])) {
            $options = $link[$url_key]->getOptions();
            if (isset($options['language'])) {
              $interface_language = \Drupal::languageManager()->getCurrentLanguage();
              if ($options['language']->getId() != $interface_language->getId()) {
                $options['language'] = $interface_language;
                $link[$url_key]->setOptions($options);
              }
            }
          }
          break;
      }
    }
  }

  /**
   * Expands a settings array.
   *
   * @param array $settings
   *   The input settings array.
   *
   * @return array
   *   An expanded array.
   */
  public static function expand(array $settings): array {
    $result = [];
    foreach ($settings as $field => $value) {
      if (!is_array($value)) {
        $value = [0 => ['value' => $value]];
      }
      $result[$field] = $value;
    }
    return $result;
  }

  /**
   * Flattens a settings array.
   *
   * @param array $settings
   *   The input settings array.
   *
   * @return array
   *   The flattened array.
   */
  public static function flatten(array $settings): array {
    $result = [];
    foreach ($settings as $field => $value) {
      if (is_array($value) && (count($value) == 1)) {
        if (array_key_exists('value', $value)) {
          $value = $value['value'];
        }
        elseif (isset($value[0], $value[0]['value'])) {
          if (count($value[0]) == 1) {
            // The "value" element is the only one, return it.
            $value = $value[0]['value'];
          }
          else {
            // This is likely a text field with both a value and a format. Need
            // to return the array containing both.
            $value = $value[0];
          }
        }
      }
      $result[$field] = $value;
    }
    return $result;
  }

}
