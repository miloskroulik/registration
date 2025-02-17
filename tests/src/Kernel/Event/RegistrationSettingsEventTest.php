<?php

namespace Drupal\Tests\registration\Kernel\Event;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\CronInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests registration settings events.
 *
 * @coversDefaultClass \Drupal\registration\Event\RegistrationSettingsEvent
 *
 * @group registration
 */
class RegistrationSettingsEventTest extends EventTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * The cron interface.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected CronInterface $cron;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'registration_test_open_close',
    'registration_scheduled_action',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cron = $this->container->get('cron');

    // Schedule open and close actions.
    $scheduled_action = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->create([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'dispatch_event_on_open',
        'label' => 'Dispatch event on open',
        'datetime' => [
          'length' => 0,
          'type' => 'minutes',
          'position' => 'after',
        ],
        'plugin' => 'dispatch_event_on_open_action',
        'target_langcode' => 'und',
        'configuration' => [
          'plugin_date' => '',
        ],
      ]);
    $scheduled_action->save();

    $scheduled_action = $this->entityTypeManager
      ->getStorage('registration_scheduled_action')
      ->create([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'dispatch_event_on_close',
        'label' => 'Dispatch event on close',
        'datetime' => [
          'length' => 0,
          'type' => 'minutes',
          'position' => 'after',
        ],
        'plugin' => 'dispatch_event_on_close_action',
        'target_langcode' => 'und',
        'configuration' => [
          'plugin_date' => '',
        ],
      ]);
    $scheduled_action->save();
  }

  /**
   * @covers ::getSettings
   *
   * Event subscribers in the test module do the following:
   *
   * on open - enable registration, increment maximum allowed spaces
   * on close - disable registration, decrement maximum allowed spaces
   *
   * @see \Drupal\registration_test_open_close\EventSubscriber\RegistrationSettingsEventSubscriber
   */
  public function testRegistrationSettingsEvent() {
    $node = $this->createAndSaveNode();

    /** @var \Drupal\registration\HostEntityInterface $host_entity */
    $host_entity = $this->entityTypeManager
      ->getHandler($node->getEntityTypeId(), 'registration_host_entity')
      ->createHostEntity($node);

    // Start with maximum spaces 1 and registration disabled.
    $settings = $host_entity->getSettings();
    $settings->set('maximum_spaces', 1);
    $settings->set('status', 0);
    $settings->save();

    // Run cron. There is no open date so nothing will happen since the
    // settings open subscriber will not be invoked.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Add an open date and run cron. Registration will be opened and
    // maximum spaces will increment.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);
    $settings->set('open', $now_date);
    $settings->save();

    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(2, (int) $settings->getSetting('maximum_spaces'));
    $this->assertTrue((bool) $settings->getSetting('status'));

    // Run cron again. The "open" event only fires once, so the maximum
    // spaces field should not increment again.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(2, (int) $settings->getSetting('maximum_spaces'));
    $this->assertTrue((bool) $settings->getSetting('status'));

    // Add a close date and run cron. Registration will be disabled and
    // maximum spaces will decrement.
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $now = new DrupalDateTime('now', $storage_timezone);
    $now_date = $now->format($storage_format);
    $settings->set('close', $now_date);
    $settings->save();

    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));

    // Run cron again. The "close" event only fires once, so the maximum
    // spaces field should not decrement again.
    $this->cron->run();

    $settings = $this->reloadEntity($settings);
    $this->assertEquals(1, (int) $settings->getSetting('maximum_spaces'));
    $this->assertFalse((bool) $settings->getSetting('status'));
  }

}
