<?php

namespace Drupal\Tests\registration_inline_entity_form\Functional;

use Drupal\Tests\registration\Functional\RegistrationBrowserTestBase;

/**
 * Tests the inline entity form for registration.
 *
 * @group registration
 */
class RegistrationInlineEntityFormTest extends RegistrationBrowserTestBase {

  /**
   * Modules to enable.
   *
   * Note that when a child class declares its own $modules list, that list
   * doesn't override this one, it just extends it.
   *
   * @var array
   */
  protected static $modules = [
    'inline_entity_form',
    'field_ui',
    'node',
    'registration_inline_entity_form',
  ];

  /**
   * Tests a registration field using the inline entity form to edit settings.
   */
  public function testRegistrationInlineEntityForm() {
    // Create a test content type.
    $this->drupalCreateContentType(['type' => 'test_node_type']);

    // Login as a user who can administer node fields.
    $user = $this->drupalCreateUser([
      'administer node fields',
      'administer node form display',
      'bypass node access',
      'create conference registration self',
    ]);
    $this->drupalLogin($user);

    // Create a new registration field on the content type.
    $edit = [
      'new_storage_type' => 'registration',
      'label' => 'Registration',
      'field_name' => 'registration',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/add-field');
    $this->submitForm($edit, 'Save and continue');
    $this->assertSession()->statusMessageContains('Your settings have been saved.', 'status');

    $edit = [];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/node.test_node_type.field_registration/storage');
    $this->submitForm($edit, 'Save field settings');
    $this->assertSession()->statusMessageContains('Updated field', 'status');

    $edit = [
      'set_default_value' => TRUE,
      'default_value_input[field_registration][0][registration_type]' => 'conference',
      'default_value_input[registration_settings][status][value]' => TRUE,
      'default_value_input[registration_settings][capacity][0][value]' => 1,
      'default_value_input[registration_settings][multiple_registrations][value]' => TRUE,
      'default_value_input[registration_settings][from_address][0][value]' => 'webmaster@example.org',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/fields/node.test_node_type.field_registration');
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->statusMessageContains('Saved', 'status');

    // Update the form display to use the inline entity form for the new field.
    $edit = [
      'fields[field_registration][type]' => 'inline_entity_form_settings',
    ];
    $this->drupalGet('admin/structure/types/manage/test_node_type/form-display');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Your settings have been saved.', 'status');

    // Create a new node with inline settings.
    $edit = [
      'title[0][value]' => 'Example 1',
      'field_registration[0][registration_type]' => 'conference',
      'field_registration[0][inline_entity_form][status][value]' => TRUE,
      'field_registration[0][inline_entity_form][capacity][0][value]' => 5,
      'field_registration[0][inline_entity_form][multiple_registrations][value]' => TRUE,
      'field_registration[0][inline_entity_form][from_address][0][value]' => 'webmaster@example.org',
    ];
    $this->drupalGet('node/add/test_node_type');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageExists('status');
    $this->drupalGet('node/1/register');
    $this->assertSession()->buttonExists('Save Registration');

    // Create a new registration.
    $edit = [];
    $this->drupalGet('node/1/register');
    $this->submitForm($edit, 'Save Registration');
    $this->assertSession()->statusMessageExists('status');
  }

}
