<?php
/**
 * @file
 * Contains \Drupal\inline_entity_form\WidgetSave.
 */

namespace Drupal\inline_entity_form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class to contain widget level static form callbacks.
 */
class WidgetSave {

  /**
   * Saves all IEF entities stored in the form state.
   *
   * @param array $form
   *  Form array structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The current form state.
   */
  public static function saveFormStateEntities(array $form, FormStateInterface $form_state) {
    $inline_form_states = $form_state->get('inline_entity_form');
    foreach ($inline_form_states as $inline_form_state) {
      if (!empty($inline_form_state['entities'])) {
        $entities = &$inline_form_state['entities'];
        foreach ($entities as &$entity_info) {
          if ($entity_info['needs_save'] == TRUE && $entity_info['entity']) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $entity = $entity_info['entity'];
            $entity->save();
            $entity_info['needs_save'] = FALSE;
          }
        }
      }
    }
  }

}
