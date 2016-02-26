<?php

namespace Drupal\inline_entity_form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class ElementSubmit extends SubmitBase {

  public static function trigger($form, FormStateInterface $form_state) {
    $triggered_element = $form_state->getTriggeringElement();
    if (!empty($triggered_element['#ief_submit_trigger_all'])) {
      // The parent form was submitted, process all IEFs and their children.
      static::doSubmit($form, $form_state);
    }
    else {
      // A specific element was submitted, process it and all of its children.
      $array_parents = $triggered_element['#array_parents'];
      $array_parents = array_slice($array_parents, 0, -2);
      $element = NestedArray::getValue($form, $array_parents);
      static::doSubmit($element, $form_state);
    }
  }

  /**
   * Submits elements by calling their #ief_element_submit callbacks.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected static function doSubmit($element, FormStateInterface $form_state) {
    // Recurse through all children.
    foreach (Element::children($element) as $key) {
      if (!empty($element[$key])) {
        static::doSubmit($element[$key], $form_state);
      }
    }

    // If there are callbacks on this level, run them.
    if (!empty($element['#ief_element_submit'])) {
      foreach ($element['#ief_element_submit'] as $callback) {
        call_user_func_array($callback, [&$element, &$form_state]);
      }
    }
  }

  protected static function addCallback(&$element, $complete_form) {
    parent::addCallback($element, $complete_form);
    // Used to distinguish between an inline form submit and main form submit.
    $element['#ief_submit_trigger']  = TRUE;
    $element['#ief_submit_trigger_all'] = TRUE;
  }


}
