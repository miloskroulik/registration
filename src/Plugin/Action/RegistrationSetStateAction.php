<?php

namespace Drupal\registration\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action to set the registration state.
 *
 * @Action(
 *   id = "registration_views_set_state_action",
 *   label = @Translation("Set the Registration State"),
 *   type = "registration"
 * )
 */
class RegistrationSetStateAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an RegistrationSetStateAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'registration_state' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['registration_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $this->getStateOptions(),
      '#default_value' => $this->configuration['registration_state'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['registration_state'] = $form_state->getValue('registration_state');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $module_name = $this->entityTypeManager
      ->getDefinition($this->getPluginDefinition()['type'])
      ->getProvider();
    return ['module' => [$module_name]];
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $registration_state = $this->configuration['registration_state'];

    /** @var \Drupal\registration\Entity\RegistrationInterface $object */
    if ($object->getState()->id() != $registration_state) {
      if ($object->getState()->canTransitionTo($registration_state)) {
        $object->set('state', $registration_state);
        $object->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);
    /** @var \Drupal\registration\Entity\RegistrationInterface $object */
    $type = $object->getType()->id();
    $access = $account->hasPermission("edit $type registration state");
    $result = AccessResult::allowedIf($access)
      ->cachePerPermissions()
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Gets the available registration state options from the workflow.
   */
  protected function getStateOptions(): array {
    $states = [];
    $workflow = $this->entityTypeManager->getStorage('workflow')->load('registration');
    if ($workflow) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isShownOnForm()) {
          $states[$id] = $state->label();
        }
      }
    }
    return $states;
  }

  /**
   * Loads the current account object, if it does not exist yet.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account interface instance, if available.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Returns the current account object.
   */
  protected function prepareUser(?AccountInterface $account = NULL): AccountInterface {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    return $account;
  }

}
