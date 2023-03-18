<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Provides a base class for Registration kernel tests.
 */
abstract class RegistrationKernelTestBase extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'datetime',
    'node',
    'registration',
    'registration_test',
    'workflows',
  ];

  /**
   * The registration type.
   *
   * @var \Drupal\registration\Entity\RegistrationTypeInterface
   */
  protected RegistrationTypeInterface $regType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('registration');
    $this->installEntitySchema('registration_settings');
    $this->installEntitySchema('workflow');

    $workflow = Workflow::create([
      'id' => 'registration',
      'label' => 'Registration',
      'type' => 'registration',
      'type_settings' => [
        'states' => [
          'pending' => [
            'label' => 'Pending',
            'description' => 'Registration is pending.',
            'active' => TRUE,
            'canceled' => FALSE,
            'held' => FALSE,
            'show_on_form' => FALSE,
            'weight' => 0,
          ],
          'complete' => [
            'label' => 'Complete',
            'description' => 'Registration has been completed.',
            'active' => TRUE,
            'canceled' => FALSE,
            'held' => FALSE,
            'show_on_form' => FALSE,
            'weight' => 1,
          ],
        ],
        'transitions' => [
          'complete' => [
            'label' => 'Complete',
            'to' => 'complete',
            'weight' => 0,
            'from' => [
              'pending',
            ],
          ],
        ],
        'default_registration_state' => 'pending',
        'complete_registration_state' => 'complete',
      ],
    ]);
    $workflow->save();

    $node_type = NodeType::create([
      'type' => 'event',
      'name' => 'Event',
    ]);
    $node_type->save();

    $registration_type = RegistrationType::create([
      'id' => 'conference',
      'label' => 'Conference',
      'workflow' => 'registration',
      'defaultState' => 'pending',
      'heldExpireTime' => 1,
      'heldExpireState' => 'canceled',
    ]);
    $registration_type->save();
    /** @var \Drupal\registration\Entity\RegistrationTypeInterface $registration_type */
    $registration_type = $this->reloadEntity($registration_type);
    $this->regType = $registration_type;
  }

}
