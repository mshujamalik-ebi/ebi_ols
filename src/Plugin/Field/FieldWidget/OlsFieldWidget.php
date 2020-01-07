<?php
namespace Drupal\ebi_ols\Plugin\Field\FieldWidget;;

use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;

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
      $body = $response->getBody();
      if (empty($body)) {
        \Drupal::messenger()->addMessage(t('An error occurred with EBI Ontology Lookup Service.'), 'error');
        return FALSE;
      }
      else {
        $decoded = json_decode($body);
        $ontologies = $decoded->_embedded->ontologies;
        foreach ($ontologies as $ontology) {
          $options[$ontology->config->namespace] = $ontology->config->preferredPrefix . ': ' . $ontology->config->title;
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
    $main_widget['value']['#element_validate'] = [
      [static::class, 'validate'],
    ];
    return $main_widget;
  }
  /**
   * Validate the text field.
   */
  public static function validate($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if ($value) {
      $pieces = explode(":", $value);
      if (isset($pieces[1]) && isset($pieces[2])) {
        $iri = $pieces[1] . ':' . $pieces[2];
        $ols_url = EBI_OLS_ENDPOINT . '/ontologies/' . $pieces[0] . '/terms/' . urlencode(urlencode($iri));
        try {
          $response = \Drupal::httpClient()->get($ols_url);
          $body = $response->getBody();
          $data = json_decode($body);
          if (isset($data->error)) {
            $message = t('Ontology error: ') . t($data->message);
            $form_state->setError($element, $message);
          } elseif ($data->is_obsolete) {
            $message = $value . t(' is obsolete.');
            if (property_exists($data, 'annotation')) {
              $replace = '';
              if (property_exists($data->annotation, 'replacedBy')) {
                $replace = implode(",", $data->annotation->replacedBy);
              }
              if ($replace != '') {
                $message .= ' Replaced by ' . $replace;
              }
              $consider = '';
              if (property_exists($data->annotation, 'consider')) {
                $consider = implode(',', $data->annotation->consider);
              }
            }
            if ($consider != '') {
              $message .= ' Please consider using ' . $consider;
            }
            $form_state->setError($element, t($message));
          }
        }
        catch(RequestException $e) {
          $form_state->setError($element, t($e->getMessage()));
        }
      }
      else {
        $form_state->setError($element, t($value . t(' is not an ontology id.')));
      }
    }
  }
}
