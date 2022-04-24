<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_link' formatter.
 *
 * @FieldFormatter(
 *   id = "registration_link",
 *   label = @Translation("Registration link"),
 *   field_types = {
 *     "registration",
 *   }
 * )
 */
class RegistrationLinkFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationLinkFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();

    $options['label'] = '';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t("Optional label to use when displaying the registration title or link. Leave blank to use the parent event's label."),
      '#default_value' => $this->getSetting('label'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($label = $this->getSetting('label')) {
      $summary[] = $this->t('Registration label: @label', [
        '@label' => $label,
      ]);
    }
    else {
      $summary[] = $this->t('Registration label: Parent label');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $cache_entities = [];
    if ($host_entity = $items->getEntity()) {
      $settings = $this->registrationManager->getSettingsForHost($host_entity);
      $cache_entities[] = $settings;
      if (isset($items, $items[0])) {
        if ($id = $items[0]->getValue()['registration_type']) {
          $registration_type = $this->entityTypeManager->getStorage('registration_type')->load($id);
          if ($registration_type) {
            $cache_entities[] = $registration_type;
            if ($this->registrationManager->isEnabledForRegistration($host_entity, $settings)) {
              $entity_type_id = $host_entity->getEntityTypeId();
              $url = Url::fromRoute("entity.$entity_type_id.register", [
                $entity_type_id => $host_entity->id(),
              ]);
              $label = $this->getSetting('label') ?: $registration_type->label();
              $elements[] = [
                '#markup' => Link::fromTextAndUrl($label, $url)->toString(),
              ];
            }
          }
        }
      }
      $this->registrationManager->addCacheableDependencies(
        $elements,
        $host_entity,
        $cache_entities
      );
    }
    return $elements;
  }

}
