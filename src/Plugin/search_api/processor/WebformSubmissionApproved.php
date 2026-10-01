<?php

namespace Drupal\webform_submission_search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Indexes the "approved" checkbox of a Webform Submission as 0 or 1.
 *
 * A missing value is always indexed as 0 so that a "= 0" filter matches
 * submissions that never stored the element.
 *
 * @SearchApiProcessor(
 *   id = "webform_submission_approved",
 *   label = @Translation("Approved flag of Webform Submission"),
 *   description = @Translation("Switching on will enable indexing the approved flag of a Webform Submission as 0 or 1"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class WebformSubmissionApproved extends ProcessorPluginBase {

  /**
   * The webform element holding the flag.
   */
  const ELEMENT = 'approved';

  /**
   * The Search API property path.
   */
  const PROPERTY_PATH = 'search_api_webform_submission_approved';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Approved'),
        'description' => $this->t('Whether the Webform Submission is approved (0 or 1).'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];
      $properties[self::PROPERTY_PATH] = new ProcessorProperty($definition);
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof WebformSubmissionInterface) {
      return;
    }

    // Always emit 0 or 1; an empty value would not match a "= 0" filter.
    $value = $entity->getElementData(self::ELEMENT) ? 1 : 0;

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, self::PROPERTY_PATH);
    foreach ($fields as $field) {
      $field->addValue($value);
    }
  }

}
