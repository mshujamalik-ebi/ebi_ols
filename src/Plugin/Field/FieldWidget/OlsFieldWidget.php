<?php
namespace Drupal\ebi_ols\Plugin\Field\FieldWidget;;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;

/**
* @FieldWidget(
*   id = "ebi_ols_textfield",
*   label = @Translation("OLS Textfield"),
*   field_types = {
*     "string"
*   }
* )
*/
class OlsFieldWidget extends StringTextfieldWidget {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'ontology' => '',
        'ancestor' => '',
      ] + parent::defaultSettings();
  }
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $options = [];
    try {
      $response = \Drupal::httpClient()->get(EBI_OLS_ENDPOINT . '/ontologies?size=10000000');
      $data = (string) $response->getBody();
      if (empty($data)) {
        \Drupal::messenger()->addMessage(t('An error occurred with EBI Ontology Lookup Service.'), 'error');
        return FALSE;
      }
      else {
        $decoded = Json::decode($data);
        $ontologies = $decoded['_embedded']['ontologies'];
        foreach ($ontologies as $ontology) {
          $options[$ontology['config']['namespace']] = $ontology['config']['preferredPrefix'] . ': ' . $ontology['config']['title'];
        }
      }
    }
    catch (RequestException $e) {
      \Drupal::messenger()->addMessage(t('An error occurred with EBI Ontology Lookup Service.'), 'error');
      return FALSE;
    }
    $element['ontology'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => t('Ontology in OLS'),
      '#description' => t('Don\'t change it after you save settings. Otherwise the obelete report will not work properly. '),
      '#default_value' => $this->getSetting('ontology'),
      '#required' => TRUE,
    ];
    $element['ancestor'] = [
      '#type' => 'textfield',
      '#title' => t('Ontology ancestor'),
      '#description' => t('You can restrict a search to all children of a given term, for example "EDAM Topic": http://edamontology.org/topic_0003. '),
      '#default_value' => $this->getSetting('ancestor'),
    ];
    return $element;
  }
  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $summary[] = t('Ontology: @ontology', ['@ontology' => $this->getSetting('ontology')]);
    $ancestor = $this->getSetting('ancestor');
    if (!empty($ancestor)) {
      $summary[] = t('Ancestor: @ancestor', ['@ancestor' => $ancestor]);
    }

    return $summary;
  }
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'ebi_ols/default';
    $main_widget = parent::formElement($items, $delta, $element, $form, $form_state);
    //$element = $main_widget['value'];
    $main_widget['value']['#attributes']['class'][] = 'ebi-ols-autocomplete';
    $main_widget['value']['#attributes']['ontology'] = $this->getSetting('ontology');
    $main_widget['value']['#attributes']['ancestor'] = $this->getSetting('ancestor');
    return $main_widget;
  }

}
