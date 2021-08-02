<?php

namespace Drupal\dfv\Plugin\views\style;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * DFV style plugin.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "dfv",
 *   title = @Translation("DFV list"),
 *   help = @Translation("Returns results as a PHP array of labels and rendered rows."),
 *   theme = "views_view_unformatted",
 *   register_theme = FALSE,
 *   display_types = {"dfv"}
 * )
 */
class Dfv extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['title_field'] = array('default' => NULL);
    $options['key_field'] = array('default' => NULL);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $options = $this->displayHandler->getFieldLabels(TRUE);
    $form['title_field'] = array(
      '#type' => 'select',
      '#title' => t('Title Field'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $this->options['title_field'],
      '#weight' => -3,
    );
    $form['key_field'] = array(
      '#type' => 'select',
      '#title' => t('Key Field'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $this->options['key_field'],
      '#weight' => -3,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (!empty($this->view->live_preview)) {
      return parent::render();
    }

    $title_field = $this->options['title_field'];
    $key_field = $this->options['key_field'];
    if (!$key_field && !$title_field) {
      \Drupal::messenger()->addError(t('Check view settings'));
    }

    $results = [];
    foreach ($this->view->result as $row_index => $row) {
      $key = $this->view->getStyle()->getField($row_index, $key_field)->__toString();
      $results[$key] = $this->view->getStyle()->getField($row_index, $title_field)->__toString();
      $results[$key] = Xss::filterAdmin(preg_replace('/\s\s+/', ' ', str_replace("\n", '', $results[$key])));
    }
    return $results;
  }


  /**
   * Render the display in this style.
   *
  public function render() {
    $options = $this->options;dpm($options);

    // Play nice with View UI 'preview' : if the view is not executed
    // just display the HTML.
    if (empty($options)) {
      return parent::render();
    }

    $sets = $this->renderGrouping($this->view->result, $this->options['grouping']);

    $title_field = $this->options['title_field'];
    $key_field = $this->options['key_field'];
    if (!$key_field && !$title_field) {
      \Drupal::messenger()->addError(t('Check view settings'));
    }

    $results = array();
    foreach ($this->view->result as $row_index => $row) {
      //$results[$this->view->style_plugin->get_field($row_index, $key_field)] = $this->view->rowPlugin->render($row_index, $title_field);
    }

    return $results;
  }*/

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return TRUE;
  }

}
