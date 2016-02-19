<?php

/**
 * @file
 * Contains \Drupal\Tests\inline_entity_form\Unit\EntityInlineFormTest.
 */

namespace Drupal\Tests\inline_entity_form\Unit;

use Drupal\inline_entity_form\InlineFormState;

class EntityInlineFormTest extends \PHPUnit_Framework_TestCase {

  protected $entityForm;

  protected $formState;

  protected $contentEntity;
  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityForm = $this->getMock('\Drupal\Core\Entity\EntityFormInterface');

  }


  /**
   * @dataProvider providerTestExtractArraySequence
   */
  public function testExtractArraySequence($array, $list, $expected) {
    $inline_form_state = $this->getMock('\Drupal\inline_entity_form\InlineFormState');
    //$inline_form_state->method()
    $this->assertEquals($expected, InlineFormState::extractArraySequence($array, $list));
  }

  /**
   * Provides arrays to test EntityInlineForm::extractArraySequence().
   */
  public function providerTestExtractArraySequence() {
    $data = [];
    $data[] = [
      ['a' => ['b' => ['c' => 0]]],
      ['a', 'b', 'c'],
      ['a' => ['b' => ['c' => 0]]],
    ];
    $data[] = [
      ['a' => ['b' => ['c' => 0]]],
      ['a', 'b'],
      ['a' => ['b' => ['c' => 0]]],
    ];
    $data[] = [
      ['a' => ['b' => ['c' => 0]]],
      ['a'],
      ['a' => ['b' => ['c' => 0]]],
    ];
    $data[] = [
      ['a' => ['b' => ['c' => 0]]],
      ['d'],
      [],
    ];

    return $data;
  }

}
