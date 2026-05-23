<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the Save & Edit functionality in FormatterForm.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\custom_formatters\Form\FormatterForm;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Save & Edit button on the formatter entity form.
 *
 * Verifies that the "Save & Edit" button is present in the form actions,
 * includes the correct submit handler chain, redirects to the edit form
 * for both new and existing formatters, and ignores the ?destination
 * query parameter that Drupal adds when navigating from the collection page.
 *
 * These tests run as kernel tests (not functional) to ensure code coverage
 * is collected, since functional tests execute form submissions via HTTP
 * to a separate PHP process where pcov cannot track coverage.
 *
 * @see \Drupal\custom_formatters\Form\FormatterForm::actions()
 * @see \Drupal\custom_formatters\Form\FormatterForm::saveAndEdit()
 *
 * @group custom_formatters
 */
class FormatterFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['custom_formatters', 'field', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('custom_formatters');
  }

  /**
   * Tests that the form actions contain both Save and Save & Edit buttons.
   *
   * Verifies that the Save & Edit button exists, has the correct label,
   * and includes both ::submitForm and ::saveAndEdit in its #submit
   * handler chain, following the standard Drupal entity form pattern.
   */
  public function testActionsContainsSaveAndEditButton(): void {
    $formatter = $this->createFormatter('test_actions');
    $formatter->save();

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('formatter', 'edit');
    $form_object->setEntity($formatter);

    $form = \Drupal::formBuilder()->getForm($form_object);

    $this->assertArrayHasKey('submit', $form['actions']);
    $this->assertArrayHasKey('save_and_edit', $form['actions']);
    $this->assertSame('Save & Edit', (string) $form['actions']['save_and_edit']['#value']);
    $this->assertContains('::submitForm', $form['actions']['save_and_edit']['#submit']);
    $this->assertContains('::saveAndEdit', $form['actions']['save_and_edit']['#submit']);
  }

  /**
   * Tests Save & Edit on an existing formatter redirects to the edit form.
   *
   * Creates a formatter, submits updated values via Save & Edit, then
   * verifies that the redirect targets the edit form route with the
   * correct formatter parameter, that destination override is disabled,
   * and that the updated label is persisted in storage.
   */
  public function testSaveAndEditExistingFormatterRedirectsToEditForm(): void {
    $formatter = $this->createFormatter('test_existing');
    $formatter->save();

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('formatter', 'edit');
    assert($form_object instanceof FormatterForm);
    $form_object->setEntity($formatter);

    $form = \Drupal::formBuilder()->getForm($form_object);
    $form_state = new FormState();
    $form_state->setValues([
      'label' => 'Updated Label',
      'id' => 'test_existing',
      'type' => 'html_token',
      'status' => TRUE,
      'description' => '',
      'field_types' => 'text',
      'data' => '[node:title]',
    ]);

    $form_object->submitForm($form, $form_state);
    $form_object->saveAndEdit($form, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertNotNull($redirect);
    $this->assertEquals('entity.formatter.edit_form', $redirect->getRouteName());
    $this->assertEquals('test_existing', $redirect->getRouteParameters()['formatter']);
    $this->assertTrue($form_state->getIgnoreDestination());

    $reloaded = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->load('test_existing');
    $this->assertEquals('Updated Label', $reloaded->label());
  }

  /**
   * Tests Save & Edit on a new formatter creates it and redirects to edit.
   *
   * Creates an unsaved formatter entity, submits via Save & Edit, then
   * verifies that the redirect targets the new entity's edit form and
   * that destination override is disabled.
   */
  public function testSaveAndEditNewFormatterRedirectsToEditForm(): void {
    $formatter = $this->createFormatter('test_new');

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('formatter', 'default');
    assert($form_object instanceof FormatterForm);
    $form_object->setEntity($formatter);

    $form = \Drupal::formBuilder()->getForm($form_object);
    $form_state = new FormState();
    $form_state->setValues([
      'label' => 'New Formatter',
      'id' => 'test_new',
      'type' => 'html_token',
      'status' => TRUE,
      'description' => '',
      'field_types' => 'text',
      'data' => '[node:title]',
    ]);

    $form_object->submitForm($form, $form_state);
    $form_object->saveAndEdit($form, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertNotNull($redirect);
    $this->assertEquals('entity.formatter.edit_form', $redirect->getRouteName());
    $this->assertEquals('test_new', $redirect->getRouteParameters()['formatter']);
    $this->assertTrue($form_state->getIgnoreDestination());
  }

  /**
   * Creates a formatter entity with basic properties.
   *
   * @param string $id
   *   The formatter machine name.
   *
   * @return \Drupal\custom_formatters\FormatterInterface
   *   The unsaved formatter entity.
   */
  private function createFormatter(string $id): FormatterInterface {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => $id,
        'label' => 'Test ' . $id,
        'type' => 'html_token',
        'field_types' => ['string'],
        'data' => '[node:title]',
      ]);
    assert($formatter instanceof FormatterInterface);
    return $formatter;
  }

}
