<?php

namespace Drupal\Tests\registration_waitlist\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\registration\Entity\RegistrationType;
use Drupal\registration\Entity\RegistrationTypeInterface;

/**
 * Provides a base class for Registration Wait List kernel tests.
 */
abstract class RegistrationWaitListKernelTestBase extends EntityKernelTestBase {

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
    'registration_waitlist',
    'registration_waitlist_test',
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

    $this->installConfig('registration');

    $this->installEntitySchema('node');
    $this->installEntitySchema('registration');
    $this->installEntitySchema('registration_settings');
    $this->installEntitySchema('workflow');

    $storage = $this->entityTypeManager->getStorage('workflow');
    if ($workflow = $storage->load('registration')) {
      $workflow_type = $workflow->getTypePlugin();
      $configuration = $workflow_type->getConfiguration();
      $configuration['states']['waitlist'] = [
        'label' => 'Wait list',
        'active' => FALSE,
        'canceled' => FALSE,
        'held' => FALSE,
        'show_on_form' => TRUE,
        'description' => 'Special state for registrations after capacity is reached.',
        'weight' => 10,
      ];
      $configuration['transitions']['complete']['from'][] = 'waitlist';
      $configuration['transitions']['cancel']['from'][] = 'waitlist';
      $workflow_type->setConfiguration($configuration);
      $workflow->save();
    }

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
