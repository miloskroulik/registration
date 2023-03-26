<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the registration administrative UI.
 *
 * @group registration
 */
class RegistrationAdminTest extends RegistrationBrowserTestBase {

  /**
   * Tests viewing a registration.
   */
  public function testView() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
    ]);
    $registration->save();

    $this->drupalGet('/registration/' . $registration->id());
    $this->assertSession()->pageTextContains('Registration #' . $registration->id());
    $this->assertSession()->pageTextContains('Pending');
  }

  /**
   * Tests editing a registration.
   */
  public function testEdit() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'langcode' => 'en',
    ]);
    $registration->save();

    $this->drupalGet('/registration/' . $registration->id() . '/edit');
    $this->assertSession()->pageTextContains('Edit Registration #' . $registration->id());
    $this->getSession()->getPage()->pressButton('Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');
  }

  /**
   * Tests deleting a registration.
   */
  public function testDelete() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($user);

    /** @var \Drupal\registration\RegistrationSettingsStorage $settings_storage */
    $settings_storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $settings_storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    /** @var \Drupal\registration\RegistrationStorage $registration_storage */
    $registration_storage = $this->entityTypeManager->getStorage('registration');
    $registration = $registration_storage->create([
      'workflow' => 'registration',
      'state' => 'pending',
      'type' => 'conference',
      'entity_type_id' => 'user',
      'entity_id' => $user->id(),
      'user_uid' => 1,
      'langcode' => 'en',
    ]);
    $registration->save();

    $this->drupalGet('/registration/' . $registration->id() . '/delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the registration Registration #' . $registration->id());
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('has been deleted.');
  }

}
