<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\SubmitBase.
 */


namespace Drupal\inline_entity_form;


use Drupal\Core\Form\FormStateInterface;

class SubmitBase {
  protected static function isAttached(array &$form, FormStateInterface $form_state) {
    // attach() is called for each IEF form element, but the callbacks only
    // need to be added once per form build.
    $build_key = get_called_class() . '-' . 'ief_build_id';
    if ($form_state->getTemporaryValue($build_key) == $form['#build_id']) {
      return TRUE;
    }
    $form_state->setTemporaryValue($build_key, $form['#build_id']);
    return FALSE;
  }

  /**
   * Adds the trigger callback to the given submit element.
   *
   * @param array $element
   *   The submit element.
   * @param array $complete_form
   *   The complete form.
   */
  public static function addCallback(&$element, $complete_form) {
    if (empty($element['#submit'])) {
      // Drupal runs either the button-level callbacks or the form-level ones.
      // Having no button-level callbacks indicates that the form has relied
      // on form-level callbacks, which now need to be transferred.
      $element['#submit'] = $complete_form['#submit'];
    }

    $element['#submit'] = array_merge([[get_called_class(), 'trigger']], $element['#submit']);
    // Used to distinguish between an inline form submit and main form submit.
    $element['#ief_submit_trigger']  = TRUE;
    $element['#ief_submit_trigger_all'] = TRUE;
  }

}
