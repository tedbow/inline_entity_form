<?php

/**
 * Contains \Drupal\inline_entity_form\Form\EntityInlineForm.
 */

namespace Drupal\inline_entity_form\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\InlineFormInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\inline_entity_form\InlineFormState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic entity inline form handler.
 */
class EntityInlineForm implements InlineFormInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type managed by this handler.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the inline entity form controller.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EntityTypeInterface $entity_type) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $entity_type
    );
  }

  /**
   * Build from form.
   *
   * @param $entity_form
   * @param $entity
   * @param $inline_form_state
   */
  protected static function buildEntity(&$entity_form, ContentEntityInterface $entity, $inline_form_state) {

    self::copyFormValuesToEntity($entity, $entity_form, $inline_form_state);

    // Invoke all specified builders for copying form values to entity
    // properties.
    if (isset($entity_form['#entity_builders'])) {
      foreach ($entity_form['#entity_builders'] as $function) {
        call_user_func_array($function, [
          $entity->getEntityTypeId(),
          $entity,
          &$entity_form,
          &$inline_form_state
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeLabels() {
    $lowercase_label = $this->entityType->getLowercaseLabel();
    return [
      'singular' => $lowercase_label,
      'plural' => t('@entity_type entities', ['@entity_type' => $lowercase_label]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLabel(EntityInterface $entity) {
    return $entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entityType->id());
    $label_key = $this->entityType->getKey('label');
    $label_field_label = t('Label');
    if ($label_key && isset($definitions[$label_key])) {
      $label_field_label = $definitions[$label_key]->getLabel();
    }
    $bundle_key = $this->entityType->getKey('bundle');
    $bundle_field_label = t('Type');
    if ($bundle_key && isset($definitions[$bundle_key])) {
      $bundle_field_label = $definitions[$bundle_key]->getLabel();
    }

    $fields = [];
    $fields['label'] = [
      'type' => 'label',
      'label' => $label_field_label,
      'weight' => 1,
    ];
    if (count($bundles) > 1) {
      $fields[$bundle_key] = [
        'type' => 'field',
        'label' => $bundle_field_label,
        'weight' => 2,
      ];
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    $operation = 'default';

    $form_state->set(['inline_entity_form', $entity_form['#ief_id'], 'entity_form'], $this);

    $this->buildForm($entity_form, $form_state, $operation);

    $entity_form['#element_validate'][] = [get_class($this), 'entityFormValidate'];

    $entity_form['#ief_element_submit'][] = [get_class($this), 'entityFormSubmit'];
    $entity_form['#ief_element_submit'][] = [get_class($this), 'submitCleanFormState'];

    // Allow other modules to alter the form.
    $this->moduleHandler->alter('inline_entity_form_entity_form', $entity_form, $form_state);

    return $entity_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function entityFormValidate($entity_form, FormStateInterface $form_state) {
    // We only do full entity validation if entire entity is to be saved, which
    // means it should be complete. Don't validate for other requests (like file
    // uploads, etc.).
    $triggering_element = $form_state->getTriggeringElement();
    $validate = TRUE;
    if (empty($triggering_element['#ief_submit_all'])) {
      $element_name = end($triggering_element['#array_parents']);
      $validate = in_array($element_name, ['ief_add_save', 'ief_edit_save']);
    }

    if ($validate) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $entity_form['#entity'];
      $operation = 'default';

      /** @var \Drupal\Core\Entity\EntityFormInterface $controller */
      $controller = $form_state->get(['inline_entity_form', $entity_form['#ief_id'], 'entity_form']);
      $inline_form_state = new InlineFormState($form_state, $entity, $operation, $entity_form['#parents']);
      static::buildEntity($entity_form, $entity, $form_state);
      static::getFormDisplay($entity, $operation)->validateFormValues($entity, $entity_form, $form_state);

      // TODO - this is field-only part of the code. Figure out how to refactor.
      if ($inline_form_state->has(['inline_entity_form', $entity_form['#ief_id']])) {
        $form_state->set(['inline_entity_form', $entity_form['#ief_id'], 'entity'], $entity);
      }

      foreach($inline_form_state->getErrors() as $name => $message) {
        // $name may be unknown in $form_state and
        // $form_state->setErrorByName($name, $message) may suppress the error message.
        $form_state->setError($triggering_element, $message);
      }
    }

    // Unset un-triggered conditional fields errors
    $errors = $form_state->getErrors();
    $conditional_fields_untriggered_dependents = $form_state->get('conditional_fields_untriggered_dependents');
    if ($errors && !empty($conditional_fields_untriggered_dependents)) {
      foreach ($conditional_fields_untriggered_dependents as $untriggered_dependents) {
        if (!empty($untriggered_dependents['errors'])) {
          foreach (array_keys($untriggered_dependents['errors']) as $key) {
            unset($errors[$key]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    /** @var ContentEntityInterface $entity */
    $entity = $entity_form['#entity'];
    $operation = 'default';
    /** @var EntityInlineForm $controller */
    $controller = $form_state->get(['inline_entity_form', $entity_form['#ief_id'], 'entity_form']);
    //$controller->setEntity($entity);

    $inline_form_state = new InlineFormState($form_state, $entity, $operation, $entity_form['#parents']);

    // @todo why was this being copied?
    //$child_form = $entity_form;
    //$child_form['#ief_parents'] = $entity_form['#parents'];

    $inline_form_state->cleanValues();
    $entity = $entity_form['#entity'];
    static::buildEntity($entity_form, $entity, $inline_form_state);

    // The entity was already validated in entityFormValidate().
    $entity->setValidationRequired(FALSE);

    if ($entity_form['#save_entity']) {
      $entity->save();
    }
    // TODO - this is field-only part of the code. Figure out how to refactor.
    if ($inline_form_state->has(['inline_entity_form', $entity_form['#ief_id']])) {
      $form_state->set(['inline_entity_form', $entity_form['#ief_id'], 'entity'], $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($ids, $context) {
    $storage_handler = $this->entityTypeManager->getStorage($this->entityType->id());
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
  }

  /**
   * Cleans up the form state for a submitted entity form.
   *
   * After field_attach_submit() has run and the form has been closed, the form
   * state still contains field data in $form_state->get('field'). Unless that
   * data is removed, the next form with the same #parents (reopened add form,
   * for example) will contain data (i.e. uploaded files) from the previous form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public static function submitCleanFormState(&$entity_form, FormStateInterface $form_state) {
    $info = \Drupal::entityTypeManager()->getDefinition($entity_form['#entity_type']);
    if (!$info->get('field_ui_base_route')) {
      // The entity type is not fieldable, nothing to cleanup.
      return;
    }

    $bundle = $entity_form['#entity']->bundle();
    $instances = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_form['#entity_type'], $bundle);
    foreach ($instances as $instance) {
      $field_name = $instance->getName();
      if (!empty($entity_form[$field_name]['#parents'])) {
        $parents = $entity_form[$field_name]['#parents'];
        array_pop($parents);
        if (!empty($parents)) {
          $field_state = array();
          WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
        }
      }
    }
  }

  /**
   * Build the entity form
   * @param array $entity_form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $entity
   * @param $operation
   */
  protected function buildForm(&$entity_form, FormStateInterface $form_state, $operation) {
    /** @var ContentEntityInterface $entity */
    $entity = $entity_form['#entity'];
    $form_display = static::getFormDisplay($entity, $operation);
    $inline_form_state = new InlineFormState($form_state, $entity_form['#entity'], $operation, $entity_form['#parents']);
    $form_display->buildForm($entity, $entity_form, $inline_form_state);

    if (!$entity_form['#display_actions']) {
      unset($entity_form['actions']);
    }
  }

  /**
   * Get form display for entity operation.
   *
   * @param ContentEntityInterface $entity
   * @param string $operation
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected static function getFormDisplay(ContentEntityInterface $entity, $operation) {
    $form_display = EntityFormDisplay::collectRenderDisplay($entity, $operation);
    return $form_display;
  }

  /**
   * Copies top-level form values to entity properties
   *
   * This should not change existing entity properties that are not being edited
   * by this form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // First, extract values from widgets.
    $extracted = static::getFormDisplay($entity, 'default')->extractFormValues($entity, $form, $form_state);

    // Then extract the values of fields that are not rendered through widgets,
    // by simply copying from top-level form values. This leaves the fields
    // that are not being edited within this form untouched.
    foreach ($form_state->getValues() as $name => $values) {
      if ($entity->hasField($name) && !isset($extracted[$name])) {
        $entity->set($name, $values);
      }
    }
  }

  /**
   * Extracts nested portion of array based on keys in the list.
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
  public static function extractNestedValues($array, $list) {
    if ($list) {
      if (isset($array[$list[0]])) {
        return static::extractNestedValues($array[$list[0]], array_slice($list, 1));
      }
      else {
        return [];
      }
    }
    return $array;
  }

}
