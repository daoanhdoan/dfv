<?php

namespace Drupal\dfv;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\WidgetBaseInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_embed\Plugin\EmbedType\Entity;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ErefDependentUtil.
 *
 * @package Drupal\eref_dependent\Util
 */
class DfvUtil implements TrustedCallbackInterface
{

  use StringTranslationTrait;

  /**
   * Drupal Container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public $container;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  public $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  public $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  public $fieldTypePluginManager;

  /**
   * ErefDependentUtil constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The services container.
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    $this->entityFieldManager = $container->get('entity_field.manager');
    $this->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $this->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
  }

  /**
   * Helper function to return all editable fields from one bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $field_types_ids
   *   Array of field types ids if you want to get specifics field types.
   *
   * @return array
   *   Array of fields ['type' => 'description']
   */
  public function getBundleEditableFields($entityType, $bundle, array $field_types_ids = [])
  {

    if (empty($entityType) || empty($bundle)) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $field_types = $this->fieldTypePluginManager->getDefinitions();
    $options = [];
    foreach ($fields as $field_name => $field_storage) {

      // Do not show: non-configurable field storages but title.
      $field_type = $field_storage->getType();
      if (($field_storage instanceof FieldConfigInterface)
      ) {
        if (count($field_types_ids) == 0 || in_array($field_type, $field_types_ids)) {
          $options[$field_name] = $this->t('@type: @field', [
            '@type' => $field_types[$field_type]['label'],
            '@field' => $field_storage->getLabel() . " [$field_name]",
          ]);
        }
      }

    }
    asort($options);

    return $options;
  }

  public function getApplicableViewsOptions($type = 'dfv_display')
  {
    $displays = Views::getApplicableViews($type);
    $options = ["" => t('- None -')];
    if (!empty($displays)) {
      $view_storage = $this->entityTypeManager->getStorage('view');
      foreach ($displays as $data) {
        list($view_id, $display_id) = $data;
        $view = $view_storage->load($view_id);
        $display = $view->get('display');
        $options[$view_id . ':' . $display_id] = $view_id . ' - ' . $display[$display_id]['display_title'];
      }
    }
    return $options;
  }

  public static function getViewResultOptions($view_name, $display_id, $args = [])
  {
    $view = Views::getView($view_name);
    $view->setArguments(!empty($parent_field_value) ? [$parent_field_value] : []);
    $view->setDisplay($display_id);
    $options = array(
      'ids' => array(),
      'string' => '',
      'match' => 'contains',
    );
    $view->getDisplay()->setOption('dfv_options', $options);
    // Make sure the query is not cached.
    $view->preExecute();
    $view->build();
    $options = [];
    if ($view->execute()) {
      $render_array = $view->style_plugin->render();
      foreach ($render_array as $key => $value) {
        $options[$key] = Html::decodeEntities(strip_tags($value));
      }
    }

    if ($options) {
      static::applyXssInArray($options);
    }

    return $options;
  }

  /**
   * Performs Xss filter in all settings.
   *
   * @param array $array
   *   The settings array.
   */
  public static function applyXssInArray(array &$array)
  {
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        static::applyXssInArray($value);
      } elseif (is_string($value)) {
        $array[$key] = Xss::filterAdmin($value);
      }
    }
  }

  /**
   * Helper function to set ajax field property.
   *
   * @param $element
   * @param WidgetBaseInterface $widget
   * @param array $children
   * @param \Drupal\Core\Field\FieldConfigInterface FieldConfigInterface $field
   */
  public static function setAjaxProperty(&$element, WidgetBaseInterface $widget, $children = [], FieldConfigInterface $field)
  {
    $ajax = array(
      'callback' => 'dfv_update_dependent_field_callback',
      'event' => 'change',
      'progress' => [
        'type' => 'throbber',
      ],
      'br_children' => $children
    );

    if (!Element::children($element)) {
      $trigger_element = &$element;
    }
    else {
      $property_names = $field->getFieldStorageDefinition()->getPropertyNames();
      $key_column = $property_names[0];
      if (isset($element[$key_column])) {
        $trigger_element = &$element[$key_column];
      }
    }

    switch ($widget->getPluginId()) {
      case 'entity_reference_autocomplete':
      case 'entity_reference_autocomplete_tags':
      case 'entity_reference_revisions_autocomplete':
        $ajax['event'] = 'autocompleteclose';
        break;
      case 'options_buttons':
      case 'options_select':
        if (isset($element['#multiple']) && $element['#multiple']) {
          $element['#validated'] = TRUE;
        }
        break;
    }

    $trigger_element['#ajax'] = $ajax;
  }

  public static function setValueForElement(&$form_field, $field_type, $widget_type, $options)
  {
    if ($widget_type == 'options_select') {
      $options = is_array($options) ? array('_none' => t('- None -')) + $options : array('_none' => t('- None -'));
    }
    switch ($field_type) {
      case 'text_with_summary':
        $set = static::setMultiValueForElement($form_field, 'value', $options);
        if (!$set) {
          NestedArray::setValue($form_field, ['widget', 0, 'value', '#value'], implode(', ', $options));
        }
        break;
      case 'string':
      case 'text':
        $set = static::setMultiValueForElement($form_field, 'value', $options);
        if (!$set) {
          NestedArray::setValue($form_field, ['widget', 0, 'value', '#value'], implode(', ', $options));
        }
        break;
      case 'list_string':
        NestedArray::setValue($form_field, ['widget', '#options'], $options);
        break;
      case 'entity_reference':
        if ($widget_type == 'options_select') {
          NestedArray::setValue($form_field, ['widget', '#options'], $options);
        } else {
          $set = static::setMultiValueForElement($form_field, 'target_id', $options);
          if (!$set) {
            NestedArray::setValue($form_field, ['widget', 0, 'target_id', '#value'], array_pop($options));
          }
        }
        break;
      case 'commerce_customer_profile_reference':
        if ($widget_type == 'options_select') {
          NestedArray::setValue($form_field, ['widget', '#options'], $options);
        }
        break;
    }
  }

  public static function setMultiValueForElement(&$form_field, $value_key, $options)
  {
    if ((isset($form_field['widget']['#cardinality']) && $form_field['widget']['#cardinality'] == 1) || !$options) {
      return FALSE;
    }

    $delta = 0;
    foreach ($options as $value) {
      if ($delta) {
        $form_field['widget'][$delta] = $form_field['widget'][$delta - 1];
      }

      if ($value_key == NULL) {
        NestedArray::setValue($form_field, ['widget', $delta, '#value'], $value);
      } else {
        NestedArray::setValue($form_field, ['widget', $delta, $value_key, '#value'], $value);
      }

      $delta++;
    }
    return TRUE;
  }

  public static function &findFormFieldElement(array &$element, $child)
  {
    $ref = NULL;
    if (isset($element[$child]) || array_key_exists($child, $element)) {
      $ref = &$element[$child];
      return $ref;
    } else {
      if ($childs = Element::children($element)) {
        foreach ($childs as $children) {
          $ref = &DfvUtil::findFormFieldElement($element[$children], $child);
          if ($ref) {
            return $ref;
          }
        }
      }
    }
    return $ref;
  }

  public static function getDefaultValueElement($form_field, FieldConfigInterface $field)
  {
    $default_value = [];
    $property_names = $field->getFieldStorageDefinition()->getPropertyNames();
    $key_column = $property_names[0];
    $widget = !empty($form_field['widget']) ? $form_field['widget'] : $form_field;
    if (isset($widget['#default_value'])) {
      $default_value = $widget['#default_value'];
    }
    else {
      if (isset($widget[$key_column])){
        if ($widget[$key_column]['#type'] == 'entity_autocomplete' && !empty($widget[$key_column]['#tags']) && !empty($widget[$key_column]['#default_value'])) {
          foreach ($widget[$key_column]['#default_value'] as $index => $value) {
            if ($value instanceof EntityInterface) {
              $default_value[] = $value->id();
            }
          }
        }
        else {
          $default_value = $widget[$key_column]['#default_value'];
        }
      }
      elseif(Element::children($widget)) {
        foreach (Element::children($widget) as $delta) {
          $children = $widget[$delta];
          if (!empty($children[$key_column]['#default_value'])) {
            if ($children[$key_column]['#type'] == 'entity_autocomplete' && !empty($children[$key_column]['#default_value'])) {
              if ($children[$key_column]['#default_value'] instanceof EntityInterface) {
                $default_value[] = $children[$key_column]['#default_value']->id();
              }
            }
            else {
              $default_value = array_merge($default_value, is_array($children[$key_column]['#default_value']) ? $children[$key_column]['#default_value'] : [$children[$key_column]['#default_value']] );
            }
          }
          elseif (!empty($children['#default_value'])) {
            $default_value = array_merge($default_value, is_array($children['#default_value']) ? $children['#default_value'] : [$children['#default_value']]);
          }
        }
      }
    }

    return $default_value;
  }

  /**
   * @inheritDoc
   */
  public static function getFormDisplay($entity_type, $bundle, $display = 'default')
  {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    $display = $storage->load("{$entity_type}.{$bundle}.{$display}");
    return $display;
  }

  public static function setFormFieldDefaultValue(&$element, FieldConfigInterface $field, $context) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $context['items']->getEntity();
    $field_definitions = $entity->getFieldDefinitions();
    $values = DfvUtil::getDefaultValueElement($element, $field);
    if (!empty($values) || $context['widget']->getPluginId() != 'options_select') {
      //return;
    }

    $parent_field_name = $field->getThirdPartySetting('dfv', 'parent_field');
    $array_parents = !empty($element['#array_parents']) ? array_merge($element['#array_parents'], [$parent_field_name]) : [$parent_field_name];

    $parent_element = [];
    while (!empty($array_parents) && empty($parent_element)) {
      $parent_element = NestedArray::getValue($context['form'], $array_parents);
      array_pop($array_parents);
      if (!empty($array_parents)) {
        $array_parents[] = $parent_field_name;
      }
    }

    if ($parent_element) {
      $values = DfvUtil::getDefaultValueElement($parent_element, $field_definitions[$parent_field_name]);
      if (!empty($values)) {
        $args = [];
        if (is_array($values)) {
          $args = implode(",", $args);
        }

        // Get values from the view.
        $args = !empty($args) ? [$args] : [];
        $views = $field->getThirdPartySetting('dfv', 'views');
        list($view_name, $display_id) = explode(":", $views);
        $options = DfvUtil::getViewResultOptions($view_name, $display_id, $args);
        if ($options) {
          DfvUtil::setValueForElement($element, $field, $context['widget']->getPluginId(), $options);
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  public static function trustedCallbacks()
  {
    return ['preRenderDfvFieldWidget'];
  }

  /**
   * @inheritDoc
   */
  public static function preRenderDfvFieldWidget($element) {
    $array_parents = !empty($element['#parents']) ? $element['#parents']: [$element['#field_name']];
    $element['#prefix'] = '<div id="dfv-edit-' . Html::getId(implode("-", $array_parents)) . '-wrapper" class="dfv-element-wrapper">' . (!empty($element['#prefix']) ? $element['#prefix'] : "");
    $element['#suffix'] = (!empty($element['#suffix']) ? $element['#suffix'] : "") . '</div>';
    return $element;
  }

  public static function hiddenElementLabel(&$element)
  {
    if (isset($element['#title_display'])) {
      $element['#title_display'] = 'invisible';
    }
    if ($childs = Element::children($element)) {
      foreach ($childs as $child) {
        if (!empty($element[$child]['#type']) && in_array($element[$child]['#type'], array('checkboxes', 'radios'))) {
          $element[$child]['#title_display'] = 'invisible';
          continue;
        }
        static::hiddenElementLabel($element[$child]);
      }
    }
  }
}
