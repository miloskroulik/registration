<?php

namespace Drupal\registration\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): RegistrationLinkFormatter {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $options = parent::defaultSettings();

    $options['label'] = '';
    $options['show_reason'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t("Optional label to use when displaying the registration title or link. Leave blank to use the parent event's label."),
      '#default_value' => $this->getSetting('label'),
    ];
    $form['show_reason'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a reason when the link is hidden'),
      '#description' => $this->t("Displays a short message when registration is not available and the link is hidden."),
      '#default_value' => $this->getSetting('show_reason'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($label = $this->getSetting('label')) {
      $summary[] = $this->t('Registration label: @label', [
        '@label' => $label,
      ]);
    }
    else {
      $summary[] = $this->t('Registration label: Parent label');
    }
    if ($show_reason = $this->getSetting('show_reason')) {
      $summary[] = $this->t('Show reason when hidden: True');
    }
    else {
      $summary[] = $this->t('Show reason when hidden: False');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $cache_entities = [];
    if ($entity = $items->getEntity()) {
      /** @var \Drupal\registration\HostEntityInterface $host_entity */
      $host_entity = $this->entityTypeManager
        ->getHandler($entity->getEntityTypeId(), 'registration_host_entity')
        ->createHostEntity($entity, $langcode);
      $settings = $host_entity->getSettings();
      $cache_entities[] = $settings;
      if (isset($items, $items[0])) {
        if ($id = $items[0]->getValue()['registration_type']) {
          $registration_type = $this->entityTypeManager
            ->getStorage('registration_type')
            ->load($id);
          if ($registration_type) {
            $cache_entities[] = $registration_type;

            $validation_result = $host_entity->isAvailableForRegistration(TRUE);
            $validation_result->getCacheableMetadata()->applyTo($elements);

            if ($validation_result->isValid()) {
              $entity_type_id = $host_entity->getEntityTypeId();
              $url = Url::fromRoute("entity.$entity_type_id.registration.register", [
                $entity_type_id => $host_entity->id(),
              ]);
              $label = $this->getSetting('label') ?: $registration_type->label();
              $elements[] = [
                '#markup' => Link::fromTextAndUrl($label, $url)->toString(),
              ];
            }
            elseif ($this->getSetting('show_reason')) {
              $elements[] = [
                '#markup' => $validation_result->getReason(),
              ];
            }
          }
        }
      }
      $host_entity->addCacheableDependencies(
        $elements,
        $cache_entities
      );
    }
    return $elements;
  }

}
