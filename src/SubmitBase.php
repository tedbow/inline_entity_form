<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\SubmitBase.
 */


namespace Drupal\inline_entity_form;


use Drupal\Core\Form\FormStateInterface;

/**
 * Class SubmitBase
 *
 * @package Drupal\inline_entity_form
 */
abstract class SubmitBase {

  /**
   * Attaches the #ief_element_submit functionality to the given form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function attach(&$form, FormStateInterface $form_state) {
    // Entity form actions.
    foreach (['submit', 'publish', 'unpublish'] as $action) {
      if (!empty($form['actions'][$action])) {
        static::addCallback($form['actions'][$action], $form);
      }
    }
    // Generic submit button.
    if (!empty($form['submit'])) {
      static::addCallback($form['submit'], $form);
    }
  }

  public static function formAlter(array &$form, FormStateInterface $form_state) {
    if (static::formApplies($form_state) && !static::isAttached($form, $form_state)) {
      static::attach($form, $form_state);
      $form_state->setTemporaryValue(static::getBuildKey(), $form['#build_id']);
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return bool
   */
  protected static function isAttached(array $form, FormStateInterface $form_state) {
    // attach() is called for each IEF form element, but the callbacks only
    // need to be added once per form build.
    $build_key = static::getBuildKey();
    if ($form_state->getTemporaryValue($build_key) == $form['#build_id']) {
      return TRUE;
    }
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
  protected static function addCallback(&$element, $complete_form) {
    if (empty($element['#submit'])) {
      // Drupal runs either the button-level callbacks or the form-level ones.
      // Having no button-level callbacks indicates that the form has relied
      // on form-level callbacks, which now need to be transferred.
      $element['#submit'] = $complete_form['#submit'];
    }

    $element['#submit'] = array_merge([[get_called_class(), 'trigger']], $element['#submit']);

  }

  /**
   * @return string
   */
  protected static function getBuildKey() {
    $build_key = get_called_class() . '-' . 'ief_build_id';
    return $build_key;
  }

  protected static function formApplies(FormStateInterface $form_state) {
    return !empty($form_state->get('inline_entity_form'));
  }

  public static function trigger($form, FormStateInterface $form_state) {

  }



}
