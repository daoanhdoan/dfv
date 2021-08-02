<?php

namespace Drupal\dfv\Plugin\views\row;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\row\Fields;

/**
 * DFV row plugin.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "dfv",
 *   title = @Translation("DFV inline fields"),
 *   help = @Translation("Displays the fields with an optional template."),
 *   theme = "views_view_fields",
 *   register_theme = FALSE,
 *   display_types = {"dfv"}
 * )
 */
class Dfv extends Fields {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['separator'] = ['default' => '-'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Expand the description of the 'Inline field' checkboxes.
    $form['inline']['#description'] .= '<br />' . t("<strong>Note:</strong> In 'dfv' displays, all fields will be displayed inline unless an explicit selection of inline fields is made here.");
  }

  /**
   * {@inheritdoc}
   */
  public function preRender($row) {
    // Force all fields to be inline by default.
    if (empty($this->options['inline'])) {
      $fields = $this->view->getHandlers('field', $this->displayHandler->display['id']);
      $names = array_keys($fields);
      $this->options['inline'] = array_combine($names, $names);
    }

    return parent::preRender($row);
  }

}
