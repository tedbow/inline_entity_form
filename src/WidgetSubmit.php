<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\WidgetSubmit.
 */


namespace Drupal\inline_entity_form;


use Drupal\Core\Form\FormStateInterface;

class WidgetSubmit extends SubmitBase {
  protected static function formApplies(FormStateInterface $form_state) {
    if (parent::formApplies($form_state)) {
      foreach ($form_state->get('inline_entity_form') as $ief_state) {
        if (!empty($ief_state['instance'])) {
          // At least ief state in form_state has a field instance.
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public static function trigger($form, FormStateInterface $form_state) {
    static::handleFormStateEntities($form_state);
  }

  /**
   * Saves all IEF entities stored in the form state.
   *
   * @todo This will currently probably save entities that have already been
   *   saved. 'needs_save' is not removed on $entity->save() in
   *   \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex::extractFormValues
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function handleFormStateEntities(FormStateInterface $form_state) {
    $inline_form_states = $form_state->get('inline_entity_form');
    foreach ($inline_form_states as $inline_form_state) {
      if (!empty($inline_form_state['entities'])) {
        $entities = $inline_form_state['entities'];
        foreach ($entities as $entity_info) {
          if ($entity_info['needs_save'] == TRUE && $entity_info['entity']) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $entity = $entity_info['entity'];
            $entity->save();
            unset($entity_info['needs_save']);
          }
        }
      }
    }
  }


}
