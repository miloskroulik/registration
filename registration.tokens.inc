<?php

/**
 * @file
 * Builds placeholder replacement tokens for registration-related data.
 */

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info_alter().
 */
function registration_token_info_alter(&$info) {
  // If the token module is installed, add a few tokens that it doesn't already
  // provide or doesn't handle properly.
  if (\Drupal::moduleHandler()->moduleExists('token')) {
    $info['tokens']['registration']['entity'] = [
      'name' => t('Host entity'),
      'description' => t('The host entity for the registration.'),
    ];
    $info['tokens']['registration']['id'] = [
      'name' => t('ID'),
      'description' => t('The unique identifier for the registration.'),
    ];
    $info['tokens']['registration']['label'] = [
      'name' => t('Label'),
      'description' => t('The registration label.'),
    ];
    $info['tokens']['registration']['state'] = [
      'name' => t('Status'),
      'description' => t('The registration status.'),
    ];
    $info['tokens']['registration']['type'] = [
      'name' => t('Type ID'),
      'description' => t('The registration type ID.'),
    ];
    $info['tokens']['registration']['type-name'] = [
      'name' => t('Type name'),
      'description' => t('The registration type name.'),
    ];
  }
}

/**
 * Implements hook_token_info().
 */
function registration_token_info() {
  // When the token module is installed, most of these tokens are already
  // defined, and the ones that aren't are defined by the alter hook above.
  if (!\Drupal::moduleHandler()->moduleExists('token')) {
    $types['registration'] = [
      'name' => t('Registration'),
      'description' => t('Tokens related to registrations.'),
      'needs-data' => 'registration',
    ];
    $registration['id'] = [
      'name' => t('ID'),
      'description' => t('The unique identifier for the registration.'),
    ];
    $registration['count'] = [
      'name' => t('Spaces'),
      'description' => t('The number of spaces reserved by the registration.'),
    ];
    $registration['label'] = [
      'name' => t('Label'),
      'description' => t('The registration label.'),
    ];
    $registration['mail'] = [
      'name' => t('Email'),
      'description' => t('The email address for the registration.'),
    ];
    $registration['state'] = [
      'name' => t('Status'),
      'description' => t('The registration status.'),
    ];
    $registration['type'] = [
      'name' => t('Type ID'),
      'description' => t('The registration type ID.'),
    ];
    $registration['type-name'] = [
      'name' => t('Type name'),
      'description' => t('The registration type name.'),
    ];

    // Chained tokens for registrations.
    $registration['author'] = [
      'name' => t('Author'),
      'type' => 'user',
    ];
    $registration['created'] = [
      'name' => t("Date created"),
      'type' => 'date',
    ];
    $registration['changed'] = [
      'name' => t("Date changed"),
      'description' => t("The date the registration was most recently updated."),
      'type' => 'date',
    ];
    $registration['completed'] = [
      'name' => t("Date completed"),
      'description' => t("The date the registration was completed."),
      'type' => 'date',
    ];
    $registration['entity'] = [
      'name' => t('Host entity'),
      'description' => t('The host entity for the registration.'),
    ];
    $registration['user'] = [
      'name' => t('User'),
      'type' => 'user',
    ];

    return [
      'types' => $types,
      'tokens' => ['registration' => $registration],
    ];
  }
}

/**
 * Implements hook_tokens().
 */
function registration_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  if (isset($options['langcode'])) {
    $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = LanguageInterface::LANGCODE_DEFAULT;
  }

  $replacements = [];

  // The registration tokens.
  if ($type == 'registration' && !empty($data['registration'])) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $data['registration'];

    // Simple tokens.
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          $replacements[$original] = $registration->id();
          break;

        case 'count':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration->getSpacesReserved();
          }
          break;

        case 'label':
          $replacements[$original] = $registration->label();
          break;

        case 'mail':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $replacements[$original] = $registration->getEmail();
          }
          break;

        case 'state':
          $replacements[$original] = $registration->getState()?->label();
          break;

        case 'type':
          $replacements[$original] = $registration->getType()->id();
          break;

        case 'type-name':
          $replacements[$original] = $registration->getType()->label();
          break;

        // Default values for the chained tokens handled below.
        case 'author':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $account = $registration->getAuthor() ? $registration->getAuthor() : User::load(0);
            $bubbleable_metadata->addCacheableDependency($account);
            $replacements[$original] = $account->label();
          }
          break;

        case 'created':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getCreatedTime(), 'medium', '', NULL, $langcode);
          }
          break;

        case 'changed':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getChangedTime(), 'medium', '', NULL, $langcode);
          }
          break;

        case 'completed':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            if ($registration->isComplete()) {
              $date_format = DateFormat::load('medium');
              $bubbleable_metadata->addCacheableDependency($date_format);
              $replacements[$original] = \Drupal::service('date.formatter')->format($registration->getCompletedTime(), 'medium', '', NULL, $langcode);
            }
          }
          break;

        case 'entity':
          if ($entity = $registration->getHostEntity()?->getEntity()) {
            $bubbleable_metadata->addCacheableDependency($entity);
            $replacements[$original] = $entity->label();
          }
          break;

        case 'user':
          // Defer to the token module when installed.
          if (!\Drupal::moduleHandler()->moduleExists('token')) {
            $account = $registration->getUser() ? $registration->getUser() : User::load(0);
            $bubbleable_metadata->addCacheableDependency($account);
            $replacements[$original] = $account->label();
          }
          break;
      }
    }

    // Defer to the token module when installed.
    if (!\Drupal::moduleHandler()->moduleExists('token')) {
      // Author tokens at [registration:author:*].
      if ($author_tokens = $token_service->findWithPrefix($tokens, 'author')) {
        $data = ['user' => $registration->getAuthor()];
        $replacements += $token_service->generate('user', $author_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date created tokens at [registration:created:*].
      if ($created_tokens = $token_service->findWithPrefix($tokens, 'created')) {
        $data = ['date' => $registration->getCreatedTime()];
        $replacements += $token_service->generate('date', $created_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date changed tokens at [registration:changed:*].
      if ($changed_tokens = $token_service->findWithPrefix($tokens, 'changed')) {
        $data = ['date' => $registration->getChangedTime()];
        $replacements += $token_service->generate('date', $changed_tokens, $data, $options, $bubbleable_metadata);
      }

      // Date completed tokens at [registration:completed:*].
      if ($completed_tokens = $token_service->findWithPrefix($tokens, 'completed')) {
        $data = ['date' => $registration->getCompletedTime()];
        $replacements += $token_service->generate('date', $completed_tokens, $data, $options, $bubbleable_metadata);
      }

      // User tokens at [registration:user:*].
      if ($user_tokens = $token_service->findWithPrefix($tokens, 'user')) {
        $data = ['user' => $registration->getUser()];
        $replacements += $token_service->generate('user', $user_tokens, $data, $options, $bubbleable_metadata);
      }
    }

    // Host entity tokens at [registration:entity:*].
    if ($entity_tokens = $token_service->findWithPrefix($tokens, 'entity')) {
      if ($entity = $registration->getHostEntity()?->getEntity()) {
        $entity_type_id = $entity->getEntityTypeId();
        $data = [$entity_type_id => $entity];
        $replacements += $token_service->generate($entity_type_id, $entity_tokens, $data, $options, $bubbleable_metadata);
      }
    }
  }

  return $replacements;
}
