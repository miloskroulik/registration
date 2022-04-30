<?php

namespace Drupal\registration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Defines the product type entity class.
 *
 * @ConfigEntityType(
 *   id = "registration_type",
 *   label = @Translation("Registration type"),
 *   label_collection = @Translation("Registration types"),
 *   label_singular = @Translation("registration type"),
 *   label_plural = @Translation("registration types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count registration type",
 *     plural = "@count registration types",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "list_builder" = "Drupal\registration\RegistrationTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\registration\Form\RegistrationTypeForm",
 *       "edit" = "Drupal\registration\Form\RegistrationTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "registration_type",
 *   admin_permission = "administer registration types",
 *   bundle_of = "registration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "workflow_id",
 *     "defaultState",
 *     "heldExpireTime",
 *     "heldExpireState"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/registration-types/add",
 *     "edit-form" = "/admin/structure/registration-types/{registration_type}/edit",
 *     "delete-form" = "/admin/structure/registration-types/{registration_type}/delete",
 *     "collection" = "/admin/structure/registration-types"
 *   }
 * )
 */
class RegistrationType extends ConfigEntityBundleBase implements RegistrationTypeInterface {

  /**
   * The registration type workflow ID.
   *
   * @var string
   */
  protected string $workflow_id = 'registration';

  /**
   * The default registration state.
   *
   * @var string
   */
  protected string $defaultState = 'pending';

  /**
   * How long a registration can be held (in hours) before it expires.
   *
   * Defaults to one hour. Zero means the hold never expires.
   *
   * @var int
   */
  protected int $heldExpireTime = 1;

  /**
   * The state a registration will be put into when its hold expires.
   *
   * @var string
   */
  protected string $heldExpireState = 'canceled';

  /**
   * {@inheritdoc}
   */
  public function getActiveStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isActive()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveOrHeldStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isActive() || $state->isHeld()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldStates(): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isHeld()) {
          $states[$id] = $state;
        }
      }
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatesToShowOnForm(StateInterface $default_state = NULL): array {
    $states = [];

    if ($workflow = $this->getWorkflow()) {
      $all_states = $workflow->getTypePlugin()->getStates();
      foreach ($all_states as $id => $state) {
        /** @var \Drupal\registration\RegistrationState $state */
        if ($state->isShownOnForm()) {
          $states[$id] = $state;
        }
      }
    }

    // Ensure the default state is included, if set.
    if ($default_state) {
      $states[$default_state->id()] = $default_state;
    }

    return $states;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): string {
    return $this->workflow_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface|null {
    $storage = \Drupal::entityTypeManager()->getStorage('workflow');
    return $storage->load($this->getWorkflowId());
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflowId($workflow_id): static {
    $this->workflow_id = $workflow_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultState(): string {
    if ($this->isNew()) {
      // Default new registration types to the global default for the workflow.
      $workflow = $this->getWorkflow();
      $configuration = $workflow->getTypePlugin()->getConfiguration();
      return $configuration['default_registration_state'];
    }
    else {
      return $this->defaultState;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultState($state): RegistrationTypeInterface {
    $this->defaultState = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldExpirationTime(): int {
    return $this->heldExpireTime;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeldExpirationTime($time): RegistrationTypeInterface {
    $this->heldExpireTime = $time;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeldExpirationState(): string {
    return $this->heldExpireState;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeldExpirationState($state): RegistrationTypeInterface {
    $this->heldExpireState = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): RegistrationTypeInterface {
    parent::calculateDependencies();

    // The registration type must depend on the module that provides the workflow.
    $workflow_plugin = $this->getWorkflow()->getTypePlugin();
    $this->calculatePluginDependencies($workflow_plugin);

    return $this;
  }

}
