<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests register.
 *
 * @group registration
 */
class RegisterTest extends RegistrationBrowserTestBase {

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
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');

    // Already registered.
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $this->assertSession()->buttonNotExists('Save Registration');
    $this->assertSession()->pageTextContainsOnce('You are already registered for this event.');
    $this->drupalLogout();

    // Register other person (anonymous). Must provide email address.
    $user = $this->drupalCreateUser([
      'access user profiles',
      'create conference registration other anonymous',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $edit = [
      'anon_mail[0][value]' => $this->randomMachineName() . '@example.com',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->assertSession()->pageTextContains($this->adminUser->getDisplayName());
    $this->assertSession()->pageTextNotContains($user->getDisplayName());
    $this->drupalLogout();

    // Redirect without token.
    $settings->set('confirmation_redirect', '/user/' . $user->id());
    $settings->save();

    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $edit = [
      'anon_mail[0][value]' => $this->randomMachineName() . '@example.com',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->assertSession()->pageTextContains($user->getDisplayName());
    $this->drupalLogout();

    // Redirect with token.
    $settings->set('confirmation_redirect', '/user/[registration:author_uid]');
    $settings->save();

    $this->drupalLogin($user);
    $this->drupalGet('user/' . $this->adminUser->id() . '/register');
    $edit = [
      'anon_mail[0][value]' => $this->randomMachineName() . '@example.com',
    ];
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status', 'Registration has been saved.');
    $this->assertSession()->pageTextContains($user->getDisplayName());
    $this->drupalLogout();
  }

}
