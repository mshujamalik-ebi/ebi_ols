<?php

namespace Drupal\ebi_ols\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * @FieldFormatter(
 *   id = "ebi_ols",
 *   label = @Translation("Ontology Lookup Service"),
 *   description = @Translation("Use the Ontolody Lookup Service to display Ontology terms"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */

class OlsFieldFormatter extends FormatterBase{
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'obsolete_notice' => TRUE,
      ] + parent::defaultSettings();
  }
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $link = Link::fromTextAndUrl('https://www.ebi.ac.uk/ols/docs/api', Url::fromUri('https://www.ebi.ac.uk/ols/docs/api#_search_parameters'));
    $form['obsolete_notice'] = [
      '#title' => $this->t('Show obsolete notice'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('obsolete_notice'),
      '#description' => $this->t('Show a red "obsolete" after the obsoleted term. See @link.', ['@link' => \Drupal::service('renderer')->render(($link->toRenderable()))]),
    ];

    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $obsolete_notice = $this->getSetting('obsolete_notice');
    if ($obsolete_notice) {
      $summary[] = $this->t('Give the obsoleted term a red notice.');
    }
    return $summary;
  }
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];
    $elements['#attached']['library'][] = 'ebi_ols/default';
    $obsolete_notice = $this->getSetting('obsolete_notice');
    foreach ($items as $delta => $item) {
      $pieces = explode(":", $item->value);
      if (count($pieces) == 3) {
        $iri = $pieces[1] . ':' . $pieces[2];
        $ols_url = EBI_OLS_ENDPOINT . '/ontologies/' . $pieces[0] . '/terms/' . urlencode(urlencode($iri));
        $elements[$delta] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#attributes' => [
            'href' => $iri,
            'class' => ['ebi_ols_formatter_default'],
            'ols_url' => $ols_url,
          ],
          '#value' => $item->value,
        ];
        if ($obsolete_notice) {
          $elements[$delta]['#attributes']['class'][] = 'obsolete_notice';
        }
      }
      else {
        $elements[$delta] = [
          //'#theme' => 'ebi_ols_formatter_default',
          //'#item' => $item->value,
          '#markup' => $item->value
        ];
      }
    }
    return $elements;
  }
}
