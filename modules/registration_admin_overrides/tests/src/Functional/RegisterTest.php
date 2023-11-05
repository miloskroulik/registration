<?php

namespace Drupal\Tests\registration_admin_overrides\Functional;

/**
 * Tests register.
 *
 * @group registration
 */
class RegisterTest extends RegistrationAdminOverridesBrowserTestBase {

  /**
   * Tests doing a new registration.
   */
  public function testRegister() {
    $this->drupalLogout();

    $this->adminUser->set('field_registration', 'conference');
    $this->adminUser->save();

    $handler = $this->entityTypeManager->getHandler('registration', 'host_entity');
    $host_entity = $handler->createHostEntity($this->adminUser);

    /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
    $storage = $this->entityTypeManager->getStorage('registration_settings');
    $settings = $storage->loadSettingsForHostEntity($host_entity);
    $settings->set('status', TRUE);
    $settings->save();

    // Register self.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');
    $this->drupalLogout();

    // No more room.
    $settings->set('capacity', 1);
    $settings->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');
    $this->drupalLogout();

    // Override the capacity.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');
    $this->drupalLogout();

    // Before open and no room.
    $settings->set('open', '2220-01-01T00:00:00');
    $settings->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContains('is not open yet');
    $this->drupalLogout();

    // Override the capacity. Registration is still before open.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContains('is not open yet');
    $this->drupalLogout();

    // Override the capacity and open date.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
      'registration override open',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->assertSession()->pageTextNotContains('is not open yet');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');
    $this->drupalLogout();

    // After close and no room.
    $settings->set('open', NULL);
    $settings->set('close', '2020-01-01T00:00:00');
    $settings->save();
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContains('is closed');
    $this->drupalLogout();

    // Override the capacity. Registration is still after close.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContains('is closed');
    $this->drupalLogout();

    // Override the capacity and close date.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
      'registration override close',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonExists('Save Registration');
    $this->assertSession()->pageTextNotContains('insufficient spaces remaining');
    $this->assertSession()->pageTextNotContains('is closed');
    $this->submitForm([], 'Save Registration');
    $this->assertSession()->pageTextContains('Registration has been saved.');
    $this->drupalLogout();

    // Override the capacity and close date, but the registration type is not
    // configured to allow overrides of those settings.
    $registration_type = $this->entityTypeManager->getStorage('registration_type')->load('conference');
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'capacity', FALSE);
    $registration_type->setThirdPartySetting('registration_admin_overrides', 'close', FALSE);
    $registration_type->save();

    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration self',
      'administer registration',
      'registration override capacity',
      'registration override close',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContains('insufficient spaces remaining');
    $this->assertSession()->pageTextContains('is closed');
    $this->drupalLogout();
  }

}
