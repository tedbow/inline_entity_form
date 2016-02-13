<?php

/**
 * Contains \Drupal\inline_entity_form\Form\NodeInlineForm.
 */

namespace Drupal\inline_entity_form\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Node inline form handler.
 */
class NodeInlineForm extends EntityInlineForm {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels() {
    $labels = [
      'singular' => $this->t('node'),
      'plural' => $this->t('nodes'),
    ];
    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);

    $fields['status'] = [
      'type' => 'field',
      'label' => $this->t('Status'),
      'weight' => 100,
      'display_options' => [
        'settings' => [
          'format' => 'custom',
          'format_custom_false' => $this->t('Unpublished'),
          'format_custom_true' => $this->t('Published'),
        ],
      ],
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    $entity_form =  parent::entityForm($entity_form, $form_state);

    foreach (Element::children($entity_form) as $key) {
      if (isset($entity_form[$key]['#group'])) {
        // Unset element if it is in 'advanced' group or
        // its parent group is in 'advanced'
        if ($entity_form[$entity_form[$key]['#group']] == 'advanced'
            || (isset($entity_form[$entity_form[$key]['#group']]['#group'])
            && $entity_form[$entity_form[$key]['#group']]['#group'] == 'advanced')) {
          unset($entity_form[$key]);
        }
      }
    }
    return $entity_form;
  }


}
