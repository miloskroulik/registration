<?php

namespace Drupal\registration;

/**
 * Defines a utility class.
 */
class RegistrationHelper {

  /**
   * Update an array of registration links with current language.
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

}
