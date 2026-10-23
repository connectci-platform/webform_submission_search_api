<?php

namespace Drupal\webform_submission_search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API Processor for indexing Webform Submission vote count.
 *
 * @SearchApiProcessor(
 *   id = "webform_submission_vote_count",
 *   label = @Translation("Vote Count of Webform Submission"),
 *   description = @Translation("Switching on will enable indexing the vote count of a Webform Submission"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class WebformSubmissionVotes extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Vote Count'),
        'description' => $this->t('Vote Count for Webform Submission.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['search_api_webform_submission_vote_count'] = new ProcessorProperty($definition);

    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $webform_submission = $item->getOriginalObject()->getValue();

    $flag_upvote_count = \Drupal::service('flag.count')->getEntityFlagCounts($webform_submission);
    $flag_upvote_set = $flag_upvote_count['upvote'] ?? 0;
    $flag_upvote_count = $flag_upvote_set ? (int) $flag_upvote_count['upvote'] : 0;

    if ($flag_upvote_count !== NULL) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'search_api_webform_submission_vote_count');
      foreach ($fields as $field) {
        $field->addValue($flag_upvote_count);
      }
    }
  }

}
