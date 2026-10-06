<?php

namespace Drupal\webform_submission_search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Indexes the "private" checkbox of a Webform Submission as 0 or 1.
 *
 * Only a stored value of 1 indexes as 1, the same rule as
 * KbResourceRepository::isPublic() in access_cilink. Anything else,
 * including a missing value, indexes as 0.
 *
 * @SearchApiProcessor(
 *   id = "webform_submission_private",
 *   label = @Translation("Private flag of Webform Submission"),
 *   description = @Translation("Switching on will enable indexing the private flag of a Webform Submission as 0 or 1"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class WebformSubmissionPrivate extends ProcessorPluginBase {

  /**
   * The webform element holding the flag.
   */
  const ELEMENT = 'private';

  /**
   * The Search API property path.
   */
  const PROPERTY_PATH = 'search_api_webform_submission_private';

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Private'),
        'description' => $this->t('Whether the Webform Submission is private (0 or 1).'),
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
    $value = (int) $entity->getElementData(self::ELEMENT) === 1 ? 1 : 0;

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, self::PROPERTY_PATH);
    foreach ($fields as $field) {
      $field->addValue($value);
    }
  }

}
