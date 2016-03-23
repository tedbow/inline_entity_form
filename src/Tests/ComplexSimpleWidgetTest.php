<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\Tests\ComplexSimpleWidgetTest.
 */


namespace Drupal\inline_entity_form\Tests;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigStorage;

/**
 * IEF complex field widgets combined with simple field widgets tests.
 *
 * @group inline_entity_form
 */
class ComplexSimpleWidgetTest extends InlineEntityFormTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'inline_entity_form_test',
    'field',
    'field_ui',
  ];

  protected function setUp() {
    parent::setUp();

    $this->user = $this->createUser([
      'create ief_complex_simple content',
      'create ief_simple_single content',
      'edit any ief_simple_single content',
      'create ief_test_custom content',
      'edit any ief_test_custom content',
      'delete any ief_simple_single content',
      'delete any ief_test_custom content',
      'view own unpublished content',
    ]);
    $this->drupalLogin($this->user);
    $this->fieldConfigStorage = $this->container->get('entity_type.manager')->getStorage('field_config');
  }


  public function testSimpleInComplex() {
    $outer_required_options = [
      TRUE,
      FALSE,
    ];
    $cardinality_options = [
      1 => 1,
      2 => 2,
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED => 3,
    ];
    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = $this->fieldStorageConfigStorage->load('node.ief_complex_outer');
    /** @var FieldConfig $field_config */
    $field_config = $this->fieldConfigStorage->load('node.ief_complex_simple.ief_complex_outer');
    foreach ($outer_required_options as $outer_required_option) {
      $field_config->setRequired($outer_required_option);
      $field_config->save();
      foreach ($cardinality_options as $cardinality => $limit) {
        $field_storage->setCardinality($cardinality);
        $field_storage->save();
        $this->drupalGet('node/add/ief_complex_simple');
        $host_title = 'Host node cardinality: ' . $cardinality;
        $edit = ['title[0][value]' => $host_title];
        if (!$outer_required_option) {
          // @todo Title only field only show up if it is required. Is this expected?
          $this->assertText('Complex Outer', 'Complex Inline entity field widget title found.');
          // Field should not be available before ajax submit.
          $this->assertNoFieldByName('ief_complex_outer[form][inline_entity_form][title][0][value]', NULL);
          // Now submit 'Add new node' button.
          $this->drupalPostAjaxForm(NULL, [], $this->getButtonName('//input[@type="submit" and @value="Add new node" and @data-drupal-selector="edit-ief-complex-outer-actions-ief-add"]'));
        }
        $this->assertFieldByName('ief_complex_outer[form][inline_entity_form][title][0][value]', NULL);
        // Simple widget is required so should always show up. No need for add submit.
        $this->assertFieldByName('ief_complex_outer[form][inline_entity_form][single][0][inline_entity_form][title][0][value]', NULL);
        //$this->drupalPostForm(NULL, $edit, t('Save'));
      }

    }
  }

}
