<?php

/**
 * @file
 * Views integration for Entity Registration.
 */

/**
 * Implements hook_views_data().
 */
function registration_views_data() {
  $data['views']['manage_registrations_caption'] = [
    'title' => t('Manage registrations caption'),
    'help' => t('Displays a caption for the Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_registrations_caption',
    ],
  ];
  $data['views']['manage_registrations_empty'] = [
    'title' => t('Manage registrations empty'),
    'help' => t('Displays an empty notice for the Manage Registrations listing.'),
    'area' => [
      'id' => 'manage_registrations_empty',
    ],
  ];
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function registration_views_data_alter(array &$data) {
  foreach (\Drupal::service('registration.manager')->getRegistrationEnabledEntityTypes() as $entity_type) {
    $id = $entity_type->id();
    $base_table = $entity_type->getBaseTable();
    $data[$base_table][$base_table . '_registration'] = [
      'title' => t('@type registration', [
        '@type' => $entity_type->getLabel(),
      ]),
      'help' => t('Relate @type to their registrations.', [
        '@type' => $entity_type->getPluralLabel(),
      ]),
      'relationship' => [
        'id' => "registration",
        'label' => t("$id registration"),
        'base' => 'registration',
      ],
    ];
    $data[$base_table][$base_table . '_registration_settings'] = [
      'title' => t('@type registration settings', [
        '@type' => $entity_type->getLabel(),
      ]),
      'help' => t('Relate @type to their registration settings.', [
        '@type' => $entity_type->getPluralLabel(),
      ]),
      'relationship' => [
        'id' => "registration_settings",
        'label' => t("$id registration settings"),
        'base' => 'registration_entity',
      ],
    ];
  }
  $data['registration']['host_entity'] = [
    'title' => t('Host entity'),
    'group' => t('Registration'),
    'field' => [
      'title' => t('Host entity'),
      'help' => t('Displays the host entity for a registration.'),
      'id' => 'registration_host_entity',
    ],
  ];
  $data['registration_entity']['settings_operations'] = [
    'title' => t('Settings operations'),
    'help' => t('Provides links to perform settings operations.'),
    'field' => [
      'id' => 'registration_settings_operations',
    ],
  ];
}
