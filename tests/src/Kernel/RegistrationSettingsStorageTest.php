<?php

namespace Drupal\Tests\registration\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\registration\Traits\NodeCreateTrait;

/**
 * Tests registration settings storage.
 *
 * @coversDefaultClass \Drupal\registration\RegistrationSettingsStorage
 *
 * @group registration
 */
class RegistrationSettingsStorageTest extends RegistrationKernelTestBase implements ServiceModifierInterface {

  use NodeCreateTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'language'];

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->createUser();
    $this->setCurrentUser($admin_user);

    $this->node = $this->createAndSaveNode();

    $this->installConfig(['language']);

    // Install Spanish as an alternate language.
    $es = ConfigurableLanguage::createFromLangcode('es');
    $es->save();

    // Make Spanish the default site language.
    $this->container
      ->get('config.factory')
      ->getEditable('system.site')
      ->set('default_langcode', 'es')
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Set up an override that returns an empty default for the event
    // registration field when Spanish is active.
    $service_definition = $container->getDefinition('registration.field_manager');
    $service_definition->setClass(RegistrationFieldManagerOverride::class);
  }

  /**
   * @covers ::loadSettingsForHostEntity
   */
  public function testSettingsStorage() {
    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($this->node);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');

    // Load settings for the site default language (Spanish).
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $this->assertEquals('es', $settings->getLangcode());
    $this->assertTrue($settings->isNew());

    // The previously retrieved settings were not saved.
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $this->assertEquals('es', $settings->getLangcode());
    $this->assertTrue($settings->isNew());

    // Should return previously saved settings.
    $settings->save();
    $settings2 = $storage->loadSettingsForHostEntity($host_entity);
    $this->assertFalse($settings2->isNew());
    $this->assertEquals($settings->id(), $settings2->id());

    // Fallback settings when the default settings are empty.
    // @see \Drupal\Tests\registration\Kernel\RegistrationFieldManagerOverride
    $this->assertFalse((bool) $settings->getSetting('status'));
    $this->assertEquals(0, $settings->getSetting('capacity'));
    $this->assertEquals(1, $settings->getSetting('maximum_spaces'));

    // Load settings for a specific language.
    $settings = $storage->loadSettingsForHostEntity($host_entity, 'en');
    $this->assertEquals('en', $settings->getLangcode());
    $this->assertTrue($settings->isNew());

    // The previously retrieved settings were not saved.
    $settings = $storage->loadSettingsForHostEntity($host_entity, 'en');
    $this->assertEquals('en', $settings->getLangcode());
    $this->assertTrue($settings->isNew());

    // Should return previously saved settings.
    $settings->save();
    $settings2 = $storage->loadSettingsForHostEntity($host_entity, 'en');
    $this->assertFalse($settings2->isNew());
    $this->assertEquals($settings->id(), $settings2->id());

    // Default settings from the registration_test module.
    $this->assertTrue((bool) $settings->getSetting('status'));
    $this->assertEquals(5, $settings->getSetting('capacity'));
    $this->assertEquals(2, $settings->getSetting('maximum_spaces'));
  }

}
