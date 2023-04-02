<?php

namespace Drupal\Tests\registration\Functional;

/**
 * Tests the email host entity registrants page.
 *
 * @group registration
 */
class EmailRegistrantsTest extends RegistrationBrowserTestBase {

  /**
   * Tests send.
   */
  public function testSend() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

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

    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

  /**
   * Tests preview.
   */
  public function testPreview() {
    $user = $this->drupalCreateUser();
    $user->set('field_registration', 'conference');
    $user->save();

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

    $this->drupalGet('user/' . $user->id() . '/registrations/broadcast');
    $edit = [
      'subject' => 'This is a test subject',
      'message[value]' => 'This is a test message.',
    ];
    $this->submitForm($edit, 'Preview');
    $this->assertSession()->pageTextContains('This is a test subject');
    $this->assertSession()->pageTextContains('This is a test message.');
    $this->assertSession()->hiddenFieldExists('subject');
    $this->assertSession()->hiddenFieldValueEquals('subject', 'This is a test subject');
    $this->assertSession()->hiddenFieldExists('message');
    $this->getSession()->getPage()->pressButton('Edit message');

    $this->assertSession()->addressEquals('user/' . $user->id() . '/registrations/broadcast');
    $this->getSession()->getPage()->pressButton('Send');
    $this->assertSession()->pageTextContains('Registration broadcast sent to 1 recipient.');
  }

}
