<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\InlineFormState.
 */


namespace Drupal\inline_entity_form;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

class InlineFormState extends FormState{

  /**
   * InlineFormState constructor.
   */
  public function __construct(EntityFormInterface $controller, FormStateInterface $form_state, ContentEntityInterface $entity, $operation, $parents) {
    $this->addBuildInfo('callback_object', $controller);
    $this->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $this->addBuildInfo('form_id', $controller->getFormID());
    $this->addBuildInfo('args', array());

    // Copy values to child form.
    $this->setCompleteForm($form_state->getCompleteForm());
    $this->setUserInput($form_state->getUserInput());

    $this->setValuesFromParentState($form_state, $parents);

    $this->setStorage($form_state->getStorage());
    $this->set('form_display', entity_get_form_display($entity->getEntityTypeId(), $entity->bundle(), $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $this->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $this->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $this->setRebuildInfo($rebuild_info);

    $this->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $this->set('langcode', $entity->language()->getId());

    $this->set('field', $form_state->get('field'));
    $this->setTriggeringElement($form_state->getTriggeringElement());
    $this->setSubmitHandlers($form_state->getSubmitHandlers());
  }

  protected function setValuesFromParentState(FormStateInterface $form_state, $parents) {
    $form_state_values = $this->extractArraySequence($form_state->getValues(), $parents);
    $this->setValues($form_state_values);
  }

  /**
   * Extracts part of array based on keys in the list.
   *
   * Returned array will be a subset of the original, containing only
   * values whose keys match items from the list.
   *
   * @param array $array
   *   Original array.
   * @param array $list
   *   List of keys to be used for extraction.
   *
   * @return array
   *   Extracted array.
   */
   public static function extractArraySequence($array, $list) {
    if ($list) {
      if (isset($array[$list[0]])) {
        return [
          $list[0] => static::extractArraySequence($array[$list[0]], array_slice($list, 1)),
        ];
      }
      else {
        return [];
      }
    }
    return $array;
  }
}
