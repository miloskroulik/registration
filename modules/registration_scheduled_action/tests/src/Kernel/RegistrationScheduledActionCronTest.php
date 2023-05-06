<?php

namespace Drupal\Tests\registration_scheduled_action\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\registration\Traits\NodeCreationTrait;
use Drupal\Tests\registration\Traits\RegistrationCreationTrait;

/**
 * Tests registration state transitions.
 *
 * @coversDefaultClass \Drupal\registration_scheduled_action\Cron\RegistrationSchedule
 *
 * @group registration
 */
class RegistrationScheduledActionCronTest extends RegistrationScheduledActionKernelTestBase {

  use NodeCreationTrait;
  use RegistrationCreationTrait;

  /**
   * @covers ::run
   */
  public function testScheduledActionCronFutureEvent() {
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $date = new DrupalDateTime('now', $storage_timezone);

    // Retrieve the test action. It sends email out one hour before the
    // registration close date.
    $scheduled_action = $this->entityTypeManager->getStorage('registration_scheduled_action')->load('test_future');
    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $plugin = $scheduled_action->getPlugin();
    $collection = $plugin->getKeyValueStoreCollectionName();
    $key_value_store = $this->keyValueFactory->get($collection);

    // Cron should process the test action for a close one hour away.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+1 hour')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertTrue($key_value_store->has($key));
    $this->assertEquals('processed', $key_value_store->get($key));

    // Cron should not process the test action for a close two hours away.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+2 hours')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));

    // Cron should process the test action for a close 5 minutes away.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+5 minutes')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertTrue($key_value_store->has($key));
    $this->assertEquals('processed', $key_value_store->get($key));

    // Cron should not process the test action for a close in the past.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('-15 minutes')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));

    // Cron should not process the test action for a disabled action.
    $scheduled_action->setEnabled(FALSE);
    $scheduled_action->save();
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+1 hour')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));
  }

  /**
   * @covers ::run
   */
  public function testScheduledActionCronPastEvent() {
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $date = new DrupalDateTime('now', $storage_timezone);

    // Retrieve the test action. It sends email out two days after the
    // registration close date.
    $scheduled_action = $this->entityTypeManager->getStorage('registration_scheduled_action')->load('test_past');
    /** @var \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action */
    $plugin = $scheduled_action->getPlugin();
    $collection = $plugin->getKeyValueStoreCollectionName();
    $key_value_store = $this->keyValueFactory->get($collection);

    // Cron should not process the test action for a close in the future.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('+1 hour')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));

    // Cron should not process the test action for a close one day ago.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('-1 day')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));

    // Cron should process the test action for a close two days ago.
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('-2 days')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertTrue($key_value_store->has($key));
    $this->assertEquals('processed', $key_value_store->get($key));

    // Cron should not process the test action for a disabled action.
    $scheduled_action->setEnabled(FALSE);
    $scheduled_action->save();
    $node = $this->createAndSaveNode();
    $registration = $this->createAndSaveRegistration($node);
    $host_entity = $registration->getHostEntity();
    $settings = $host_entity->getSettings();
    $php_date = $date->getPhpDateTime();
    $close = $php_date->modify('-2 days')->format($storage_format);
    $settings->set('close', $close);
    $settings->save();
    $key = $scheduled_action->getKeyValueStoreKeyName($settings->id());
    $this->cron->run();
    $this->assertFalse($key_value_store->has($key));
  }

}
