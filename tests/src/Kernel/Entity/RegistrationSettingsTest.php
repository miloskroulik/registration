<?php

namespace Drupal\Tests\registration\Kernel\Entity;

use Drupal\Tests\registration\Kernel\RegistrationKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\registration\Entity\Registration;

/**
 * Tests the Registration Settings entity.
 *
 * @coversDefaultClass \Drupal\registration\Entity\RegistrationSettings
 *
 * @group registration
 */
class RegistrationSettingsTest extends RegistrationKernelTestBase {

  /**
   * @covers ::getHostEntity
   * @covers ::getHostEntityId
   * @covers ::getHostEntityTypeId
   * @covers ::getLangcode
   * @covers ::getSetting
   */
  public function testRegistrationSettings() {
    $node = Node::create([
      'type' => 'event',
      'title' => 'My event',
      'event_registration' => 'conference',
    ]);
    $node->save();
    $node = $this->reloadEntity($node);

    $registration = Registration::create([
      'type' => 'conference',
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
    $registration->save();
    $settings = $registration->getHostEntity()->getSettings();

    $this->assertEquals($node->id(), $settings->getHostEntity()->id());
    $this->assertEquals($node->id(), $settings->getHostEntityId());
    $this->assertEquals($node->getEntityTypeId(), $settings->getHostEntityTypeId());
    $this->assertEquals('en', $settings->getLangcode());
    // Default settings from the registration_test module.
    $this->assertTrue((bool) $settings->getSetting('status'));
    $this->assertEquals(5, $settings->getSetting('capacity'));
    $this->assertEquals(2, $settings->getSetting('maximum_spaces'));
    // Settings for fields without an explicit default set.
    $this->assertNull($settings->getSetting('multiple_registrations'));
    $this->assertNull($settings->getSetting('send_reminder'));
    $this->assertNull($settings->getSetting('open'));
    $this->assertNull($settings->getSetting('close'));
    $this->assertNull($settings->getSetting('reminder_date'));
    $this->assertNull($settings->getSetting('reminder_template'));
    $this->assertNull($settings->getSetting('from_address'));
    $this->assertNull($settings->getSetting('confirmation'));
    $this->assertNull($settings->getSetting('confirmation_redirect'));
  }

}
