<?php

namespace Drupal\registration\Entity;

use Drupal;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
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
 *       "duplicate" = "Drupal\registration\Form\RegistrationTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "registration_type",
 *   admin_permission = "administer registration_type",
 *   bundle_of = "registration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "workflow",
 *     "defaultState",
 *     "heldExpireTime",
 *     "heldExpireState"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/registration/registration-types/add",
 *     "edit-form" = "/admin/structure/registration/registration-types/{registration_type}/edit",
 *     "duplicate-form" = "/admin/structure/registration/registration-types/{registration_type}/duplicate",
 *     "delete-form" = "/admin/structure/registration/registration-types/{registration_type}/delete",
 *     "collection" = "/admin/structure/registration/registration-types"
 *   }
 * )
 */
class RegistrationType extends ConfigEntityBundleBase implements RegistrationTypeInterface {

  /**
   * The registration type workflow ID.
   *
   * @var string
   */
  protected string $workflow = 'registration';

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
  public function getWorkflowId(): string {
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): WorkflowInterface|null {
    $storage = Drupal::service('entity_type.manager')->getStorage('workflow');
    return $storage->load($this->getWorkflowId());
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflowId($workflow_id): static {
    $this->workflow = $workflow_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultState(): string {
    return $this->defaultState;
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
