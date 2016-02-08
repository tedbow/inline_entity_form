<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Tests\InlineEntityFormWebTest.
 */

namespace Drupal\inline_entity_form\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the IEF element on a custom form.
 *
 * @group inline_entity_form
 */
class InlineEntityFormWebTest extends InlineEntityFormTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['inline_entity_form_test'];

  /**
   * User with permissions to create content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Field config storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $fieldStorageConfigStorage;

  /**
   * Prepares environment for
   */
  protected function setUp() {
    parent::setUp();

    $this->user = $this->createUser([
      'create ief_simple_single content',
      'edit any ief_simple_single content',
      'edit any ief_test_custom content',
      'view own unpublished content',
    ]);

    $this->fieldStorageConfigStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('field_storage_config');
  }

  /**
   * Tests IEF on a custom form.
   */
  public function testCustomFormIEF() {
    $this->drupalGet('ief-test');
    $this->assertText(t('Title'), 'Title field found on the form.');

    $edit = ['inline_entity_form[title][0][value]' => $this->randomString()];
    $this->drupalPostForm('ief-test', $edit, t('Save'));
    $message = t('Created @entity_type @label.', ['@entity_type' => t('Content'), '@label' => $edit['inline_entity_form[title][0][value]']]);
    $this->assertText($message, 'Status message found on the page.');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.manager')->getStorage('node')->load(1);
    $this->assertEqual($node->label(), $edit['inline_entity_form[title][0][value]'], 'Node title correctly saved to the database.');
    $this->assertEqual($node->bundle(), 'ief_test_custom', 'Correct bundle used when creating the new node.');
  }

  /**
   * Tests simple IEF widget with single-value field.
   */
  public function testSimpleSingle() {
    $cardinality_options = [
      1 => 1,
      2 => 2,
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED => 3,
    ];
    foreach ($cardinality_options as $cardinality => $limit) {
      /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
      $field_storage = $this->fieldStorageConfigStorage->load('node.single');
      $field_storage->setCardinality($cardinality);
      $field_storage->save();

      $this->drupalLogin($this->user);
      $this->drupalGet('node/add/ief_simple_single');

      $this->assertText('Single node', 'Inline entity field widget title found.');
      $this->assertText('Reference a single node.', 'Inline entity field description found.');

      $add_more_xpath = '//input[@data-drupal-selector="edit-single-add-more"]';
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        $this->assertFieldByXPath($add_more_xpath, NULL,'Add more button exists');
      }
      else {
        $this->assertNoFieldByXPath($add_more_xpath, NULL, 'Add more button does NOT exist');
      }

      $edit = ['title[0][value]' => 'Host node'];
      for ($item_number = 0; $item_number < $limit; $item_number++) {
        $edit["single[$item_number][inline_entity_form][title][0][value]"] = 'Child node nr.' . $item_number;
        if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
          $next_item_number = $item_number + 1;
          $this->assertNoFieldByName("single[$next_item_number][inline_entity_form][title][0][value]", NULL, "Item $next_item_number does not appear before 'Add More' clicked");
          if ($item_number < $limit - 1) {
            $this->drupalPostAjaxForm(NULL, $edit, 'single_add_more');
            // This needed because the first time "add another item" is clicked it does not work
            // see https://www.drupal.org/node/2664626
            if ($item_number == 0) {
              $this->drupalPostAjaxForm(NULL, $edit, 'single_add_more');
            }

            $this->assertFieldByName("single[$next_item_number][inline_entity_form][title][0][value]", NULL, "Item $next_item_number does  appear after 'Add More' clicked");
          }

        }
      }
      $this->drupalPostForm(NULL, $edit, t('Save'));

      for ($item_number = 0; $item_number < $limit; $item_number++) {
        $this->assertText('Child node nr.' . $item_number, 'Label of referenced entity found.');
      }
    }
  }

}
