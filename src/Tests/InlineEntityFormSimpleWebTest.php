<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Tests\InlineEntityFormWebTest.
 */

namespace Drupal\inline_entity_form\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Tests the IEF simple widget.
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
   * Tests simple IEF widget with single-value field.
   */
  public function testSimpleSingle() {
    $cardinality_options = [
      1 => 1,
      2 => 2,
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED => 1,
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

      $edit = ['title[0][value]' => 'Host node'];
      for ($item_number = 0; $item_number < $limit; $item_number++) {
        $edit["single[$item_number][inline_entity_form][title][0][value]"] = 'Child node nr.' . $item_number;
      }

      $this->drupalPostForm('node/add/ief_simple_single', $edit, t('Save'));

      for ($item_number = 0; $item_number < $limit; $item_number++) {
        $this->assertText('Child node nr.' . $item_number, 'Label of referenced entity found.');
      }
    }
  }

}
