<?php

namespace Drupal\registration_inline_entity_form\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;

/**
 * Defines the inline form for registration settings.
 */
class RegistrationSettingsInlineForm extends EntityInlineForm {

  /**
   * {@inheritdoc}
   */
  public function entityFormValidate(array &$entity_form, FormStateInterface $form_state) {
    parent::entityFormValidate($entity_form, $form_state);

    $send_reminder = $form_state->getValue(array_merge($entity_form['#parents'], [
      'send_reminder',
      'value',
    ]));
    $reminder_date = $form_state->getValue(array_merge($entity_form['#parents'], [
      'reminder_date',
      0,
      'value',
    ]));
    $reminder_template = $form_state->getValue(array_merge($entity_form['#parents'], [
      'reminder_template',
      0,
      'value',
    ]));
    $redirect = $form_state->getValue(array_merge($entity_form['#parents'], [
      'confirmation_redirect',
      0,
      'value',
    ]));

    // If sending a reminder, ensure date and template are set.
    if ($send_reminder && empty($reminder_date)) {
      $form_state->setError(
        $entity_form, $this->t('If sending a reminder, provide a date and template.'));
    }
    if ($send_reminder && empty($reminder_template)) {
      $form_state->setError(
        $entity_form, $this->t('If sending a reminder, provide a date and template.'));
    }

    // Ensure reminder date is not in the past when "send_reminder" is TRUE:
    if ($send_reminder && !empty($reminder_date)) {
      if ($reminder_date instanceof DrupalDateTime) {
        // Ensure dates are compared using the storage timezone for both.
        $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
        $reminder_date->setTimezone($storage_timezone);
        $now = new DrupalDateTime('now', $storage_timezone);
        if ($reminder_date <= $now) {
          $form_state->setError($entity_form, $this->t('Reminder must be in the future.'));
        }
      }
    }

    // If a redirect is set must either be external or start with a slash.
    if (!empty($redirect)) {
      if (!UrlHelper::isExternal($redirect) && ($redirect[0] != '/')) {
        $form_state->setError($entity_form, $this->t('Confirmation redirect path must be a valid URL. Internal paths must start with a forward slash.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels(): array {
    return [
      'singular' => $this->t('setting'),
      'plural' => $this->t('settings'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles): array {
    $fields = parent::getTableFields($bundles);
    $fields['label']['label'] = $this->t('Title');
    $fields['capacity'] = [
      'type' => 'field',
      'label' => $this->t('Capacity'),
      'weight' => 10,
    ];
    $fields['status'] = [
      'type' => 'field',
      'label' => $this->t('Status'),
      'weight' => 100,
      'display_options' => [
        'settings' => [
          'format' => 'custom',
          'format_custom_true' => $this->t('Enabled'),
          'format_custom_false' => $this->t('Disabled'),
        ],
      ],
    ];

    return $fields;
  }

}
