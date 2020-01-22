<?php

namespace Drupal\ebi_ols\Controller;

use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 *
 */
class ReportController {

  /**
   *
   */
  public function obsoleteOverview() {
    $markup = '';
    $rows = [];
    $header = [t('Entity'), t('Bundle'), t('Field name'), t('Ontology')];
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $entity_fields = $entityFieldManager->getFieldMapByFieldType('string');
    foreach ($entity_fields as $entity_name => $fields) {
      foreach ($fields as $field_name => $value) {
        foreach ($value['bundles'] as $bundle) {
          $form_display = \Drupal::entityTypeManager()
            ->getStorage('entity_form_display')
            ->load($entity_name . '.' . $bundle . '.default');
          if (!empty($form_display)) {
            $form_display_settings = $form_display->getComponent($field_name);
            if ($form_display_settings['type'] == 'ebi_ols_textfield') {
              $ontology = $form_display_settings['settings']['ontology'];
              $terms = [];
              try {
                $response = \Drupal::httpClient()->get(EBI_OLS_ENDPOINT . '/search?rows=10000&obsoletes=true&q=*&type=class&ontology=' . $ontology);
                $body = $response->getBody();
                if (empty($body)) {
                  \Drupal::messenger()->addMessage(t('An error occurred with EBI Ontology Lookup Service.'), 'error');
                  return FALSE;
                } else {
                  $decoded = json_decode($body);
                  foreach ($decoded->response->docs as $index => $doc) {
                    $terms[] = $ontology . ':' . $doc->iri;
                  }
                  $entity_ids = \Drupal::entityQuery($entity_name)
                    ->condition($field_name, $terms, 'IN')
                    ->execute();
                  if (count($entity_ids) > 0) {
                    foreach ($entity_ids as $rid => $id) {
                      $url = Url::fromUri('entity:' . $entity_name . '/' . $id);
                      $link = new Link($url->toString(), $url);
                      $rows[] = array($link->toString(), $bundle, $field_name, $ontology);
                    }
                  }
                }
              } catch (RequestException $e) {
                \Drupal::messenger()->addMessage(t('An error occurred with EBI Ontology Lookup Service.'), 'error');
                return FALSE;
              }
            }
          }
        }
      }
    }
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    $markup .= \Drupal::service('renderer')->render($table);
    $build = [
      '#markup' => $markup,
    ];
    return $build;
  }

}
