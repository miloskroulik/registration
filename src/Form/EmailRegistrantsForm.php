<?php

namespace Drupal\registration\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\registration\RegistrationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the broadcast to email registrants form.
 */
class EmailRegistrantsForm extends FormBase {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The host entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $hostEntity;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The registration service.
   *
   * @var \Drupal\registration\RegistrationServiceInterface
   */
  protected RegistrationServiceInterface $registration;

  /**
   * Creates a EmailRegistrantsForm object.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\registration\RegistrationServiceInterface $registration_service
   *   The registration service.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RegistrationServiceInterface $registration_service) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->registration = $registration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): EmailRegistrantsForm {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('registration.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'registration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $route_match = $this->getRouteMatch();
    $this->hostEntity = $this->registration->getEntityFromParameters($route_match->getParameters());

    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $this->entity = $storage->loadSettingsForEntity($this->getHostEntity());

    $form = [];
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#required' => TRUE,
      //'#default_value' => $this->getRegistrationSetting('status'),
    ];
    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      //'#default_value' => $this->getRegistrationSetting('capacity'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];
    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview'),
      '#button_type' => 'seconddary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save values to the settings entity.
    $entity = $this->getHostEntity();
    $values = $form_state->getValues();
    $this->messenger()->addStatus($this->t('The settings have been saved.'));
  }

  /**
   * Gets the settings entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The settings entity.
   */
  protected function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Gets the host entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The host entity.
   */
  protected function getHostEntity(): EntityInterface {
    return $this->hostEntity;
  }

}
