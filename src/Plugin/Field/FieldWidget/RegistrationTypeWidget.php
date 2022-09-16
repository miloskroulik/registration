<?php

namespace Drupal\registration\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'registration_type' widget.
 *
 * @FieldWidget(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   field_types = {
 *     "registration"
 *   }
 * )
 */
class RegistrationTypeWidget extends WidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected EntityTypeBundleInfo $entityTypeBundleInfo;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hide_register_tab' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $element['hide_register_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Register Tab'),
      '#description' => $this->t('Hide the tab on the content displaying the registration form. The form can still be embedded or linked to by changing the field display settings.'),
      '#default_value' => (bool) $this->getSetting('hide_register_tab'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('hide_register_tab')) {
      $summary[] = $this->t('Hide the Register tab: Yes');
    }
    else {
      $summary[] = $this->t('Hide the Register tab: No');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $bundle = $bundle_info[$entity_bundle]['label'];

    $default_value = $items[$delta]->get('registration_type')->getValue();
    $element['registration_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Registration type'),
      '#options' => $this->getRegistrationTypeOptions(),
      '#default_value' => $default_value,
      '#description' => $this->t('Select what type of registrations should be enabled for this @type. Depending on the display settings, it will appear as either string, registration link, or form.', [
        '@type' => $bundle,
      ]),
    ];

    return $element;
  }

  /**
   * Returns an array of registration type options.
   *
   * @return array
   *   The array keyed by registration type machine name.
   */
  protected function getRegistrationTypeOptions(): array {
    $options = ['' => $this->t('-- Disable Registrations --')];
    $entities = $this->entityTypeManager->getStorage('registration_type')->loadMultiple();
    foreach ($entities as $id => $entity) {
      $options[$id] = $entity->label();
    }
    return $options;
  }

}
