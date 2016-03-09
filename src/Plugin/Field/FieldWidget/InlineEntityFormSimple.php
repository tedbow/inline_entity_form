<?php

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Simple inline widget.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_simple",
 *   label = @Translation("Inline entity form - Simple"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = false
 * )
 */
class InlineEntityFormSimple extends InlineEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if (!$this->canBuildElement($element, $form_state)) {
      return $element;
    }

    $element['#type'] = 'fieldset';
    $this->setIefId(sha1($items->getName() . '-ief-single-' . $delta));
    $entity = NULL;
    if ($items->get($delta)->target_id) {
      $entity = $items->get($delta)->entity;
      if (!$entity) {
        $element['warning']['#markup'] = $this->t('Unable to load the referenced entity.');
        return $element;
      }
    }

    $op = isset($entity) ? 'edit' : 'add';
    $language = $items->getParent()->getValue()->language()->getId();
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'inline_entity_form'
    ]);
    $bundle = reset($this->getFieldSetting('handler_settings')['target_bundles']);

    if ($op == 'edit') {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      if (!$entity->access('update')) {
        $element = [
          '#entity_type' => $this->getFieldSetting('target_type'),
          '#bundle' => $bundle,
          '#language' => $language,
          '#default_value' => $entity,
          '#op' => 'edit',

          '#ief_row_delta' => $delta,
          // Used by Field API and controller methods to find the relevant
          // values in $form_state.
          '#parents' => $parents,
          // Identifies the IEF widget to which the form belongs.
          '#ief_id' => $this->getIefId(),
        ];
        $element['entity_label'] = [
          '#type' => 'markup',
          '#markup' => $entity->label(),
        ];
        return $element;

      }
    }
    $element['inline_entity_form'] = $this->getInlineEntityForm($op, $bundle, $language, $delta, $parents, $entity, TRUE);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $element = parent::formMultipleElements($items, $form, $form_state);

    // If we're using ulimited cardinality we don't display one empty item. Form
    // validation will kick in if left empty which esentially means people won't
    // be able to submit w/o creating another entity.
    if (!$form_state->isSubmitted() && $element['#cardinality'] == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && $element['#max_delta'] > 0) {
      $max = $element['#max_delta'];
      unset($element[$max]);
      $element['#max_delta'] = $max - 1;
      $items->removeItem($max);
      // Decrement the items count.
      $field_name = $element['#field_name'];
      $parents = $element[0]['#field_parents'];
      $field_state = static::getWidgetState($parents, $field_name, $form_state);
      $field_state['items_count']--;
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }

    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], array($field_name));
    $submitted_values = $form_state->getValue($path);

    $values = [];
    foreach ($items as $delta => $value) {
      $this->setIefId(sha1($items->getName() . '-ief-single-' . $delta));

      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      if (!$entity = $form_state->get(['inline_entity_form', $this->getIefId(), 'entity'])) {
        return;
      }

      $weight = isset($submitted_values[$delta]['_weight']) ? $submitted_values[$delta]['_weight'] : 0;

      $values[$weight] = ['entity' => $entity];
    }

    // Sort items base on weights.
    ksort($values);
    $values = array_values($values);

    // Let the widget massage the submitted values.
    $values = $this->massageFormValues($values, $form, $form_state);

    // Assign the values and remove the empty ones.
    $items->setValue($values);
    $items->filterEmptyItems();

    // Put delta mapping in $form_state, so that flagErrors() can use it.
    $field_name = $this->fieldDefinition->getName();
    $field_state = WidgetBase::getWidgetState($form['#parents'], $field_name, $form_state);
    foreach ($items as $delta => $item) {
      $field_state['original_deltas'][$delta] = isset($item->_original_delta) ? $item->_original_delta : $delta;
      unset($item->_original_delta, $item->_weight);
    }

    WidgetBase::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (!$field_definition->isRequired()) {
      return FALSE;
    }

    $handler_settings = $field_definition->getSettings()['handler_settings'];
    // Entity types without bundles will throw notices on next condition so let's
    // stop before they do. We should support this kind of entities too. See
    // https://www.drupal.org/node/2569193 and remove this check once that issue
    // lands.
    if (empty($handler_settings['target_bundles'])) {
      return FALSE;
    }

    if (count($handler_settings['target_bundles']) != 1) {
      return FALSE;
    }

    return TRUE;
  }


}
