<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_settings' widget.
 *
 * This widget exists solely to provide a form that site builders can use to
 * provide settings default values. This provides a mechanism for giving
 * translators the ability to translate fields such as default reminder
 * templates and other text fields. The standard "entity form" is used instead
 * of this widget when site administrators edit the actual per-host-entity
 * settings values. The entity form class is noted below. By using the
 * entity form as a substrate for this widget, translators will get access
 * to any text fields that are added to the Registration Settings fieldable
 * entity. A custom form element for the translators is provided so they
 * can translate the field configuration default values.
 *
 * @FieldWidget(
 *   id = "registration_settings",
 *   label = @Translation("Registration settings"),
 *   field_types = {
 *     "registration_settings"
 *   }
 * )
 *
 * @see \Drupal\registration\Form\RegistrationSettingsForm
 * @see \Drupal\registration\FormElement\RegistrationSettings
 */
class RegistrationSettingsWidget extends WidgetBase {

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_register_tab' => FALSE,
      'status' => 0,
      'capacity' => 0,
      'open' => '',
      'close' => '',
      'send_reminder' => 0,
      'reminder_date' => '',
      'reminder_template' => '',
      'maximum_spaces' => 1,
      'multiple_registrations' => 0,
      'from_address' => \Drupal::config('system.site')->get('mail'),
      'confirmation' => 'Registration has been saved.',
      'confirmation_redirect' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationSettingsWidget {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFormBuilder = $container->get('entity.form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    // Build an array of settings values using any existing stored values,
    // and then filling in any missing settings from field defaults.
    $value = $items[$delta]->get('value')->getValue();
    $item = unserialize($value);
    if (!is_array($item)) {
      $item = [];
    }
    $item = array_merge_recursive($item, $this->getSettings());

    // Build the settings entity form using a stub entity. This initializes
    // it with all the fields present from its form display, but no defaults.
    $entity = $this->entityTypeManager->getStorage('registration_settings')->create($item);
    $entity_form = $this->entityFormBuilder->getForm($entity);

    // Clean the form of internal keys.
    unset($entity_form['actions']);
    $clean_keys = $form_state->getCleanValueKeys();
    foreach ($clean_keys as $key) {
      unset($entity_form[$key]);
    }

    // Copy entity form elements to this form with defaults set.
    // Only copy properties that should be set in the Form API.
    // Ignore properties set by entity form field processors and
    // "after build" functions that were called.
    $properties = [
      '#type',
      '#default_value',
      '#date_date_element',
      '#date_time_element',
      '#date_year_range',
      '#date_timezone',
      '#description',
      '#title',
      '#format',
      '#size',
      '#min',
      '#max',
      '#maxlength',
      '#options',
      '#placeholder',
      '#required',
      '#rows',
    ];
    foreach (Element::children($entity_form) as $key) {
      if (isset($entity_form[$key]['widget'])) {
        // Access the widget.
        $widget_base = $entity_form[$key]['widget'];
        $base_field = $widget_base[0] ?? $widget_base;

        // If there is a #type at the top level, copy
        // form elements from the top level.
        if (isset($base_field['#type'])) {
          $element[$key] = [];
          foreach ($properties as $property) {
            if (isset($base_field[$property])) {
              $element[$key][$property] = $base_field[$property];
            }
          }
          $element[$key]['#weight'] = $entity_form[$key]['#weight'];
          if (empty($element[$key]['#title'])) {
            $element[$key]['#title'] = $base_field['#title'];
          }
        }

        // The elements are one level down.
        else {
          foreach (Element::children($base_field) as $base_key) {
            $element[$key] = [];
            foreach ($properties as $property) {
              if (isset($base_field[$base_key][$property])) {
                $element[$key][$property] = $base_field[$base_key][$property];
              }
            }
            $element[$key]['#weight'] = $entity_form[$key]['#weight'];
            if (empty($element[$key]['#title'])) {
              $element[$key]['#title'] = $base_field['#title'];
            }
          }
        }

        // Grab the token tree if an element was created and there is a tree.
        if (!empty($element[$key]) && !empty($entity_form[$key]['token_tree'])) {
          $element[$key]['token_tree'] = $entity_form[$key]['token_tree'];
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as $index => $value) {
      // Map any datetime objects to string values. Ensure times are stored as
      // UTC and in the correct format.
      $value = array_map(function (mixed $item) {
        if ($item instanceof DrupalDateTime) {
          $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
          $item->setTimezone($storage_timezone);
          return $item->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        }
        else {
          return $item;
        }
      }, $value);

      // Serialize the data since the only property for the widget is 'value'.
      $serial = serialize(array_filter($value, function (mixed $data) {
        // Filter out null values from the array.
        return !is_null($data);
      }));

      $values[$index] = empty($serial) ? NULL : $serial;
    }
    return $values;
  }

}
