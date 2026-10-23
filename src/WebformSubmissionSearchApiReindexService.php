<?php

namespace Drupal\webform_submission_search_api;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Utility\Error;
use Drupal\flag\FlaggingInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Utility\PostRequestIndexingInterface;
use Drupal\search_api\Utility\Utility;
use Psr\Log\LoggerInterface;

/**
 * Class WebformSubmissionSearchApiReindexService.
 */
class WebformSubmissionSearchApiReindexService implements WebformSubmissionSearchApiReindexServiceInterface {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Search API post request indexing service.
   *
   * @var \Drupal\search_api\Utility\PostRequestIndexingInterface
   */
  protected $postRequestIndexing;

  /**
   * Constructs a new WebformSubmissionSearchApiReindexService object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\search_api\Utility\PostRequestIndexingInterface $post_request_indexing
   *   The Search API post request indexing service.
   */
  public function __construct(LoggerInterface $logger, PostRequestIndexingInterface $post_request_indexing) {
    $this->logger = $logger;
    $this->postRequestIndexing = $post_request_indexing;
  }

  /**
   * Reindex Item.
   *
   * @param \Drupal\flag\FlaggingInterface $entity
   *   Flagging.
   */
  public function reindexItem(FlaggingInterface $entity) {
    $datasource_id = 'entity:' . $entity->getFlaggableType();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $content_flagged */
    $content_flagged = $entity->getFlaggable();
    $indexes = $this->getIndexesForEntity($content_flagged);

    $entity_id = $entity->getFlaggableId();

    $updated_item_ids = $content_flagged->getTranslationLanguages();
    $combine_id = function ($langcode) use ($entity_id) {
      return $entity_id . ':' . $langcode;
    };
    $updated_item_ids = array_map($combine_id, array_keys($updated_item_ids));
    foreach ($indexes as $index) {
      if ($updated_item_ids) {
        // Always track the change, so cron picks the item up if the immediate
        // indexing below is skipped or fails.
        $index->trackItemsUpdated($datasource_id, $updated_item_ids);
        // With "index_directly", Search API only indexes at the end of the
        // request, after the flag link's redirect has been sent, so the page
        // the user lands on would still show the old vote count and order.
        // Index now so the redirect target is current, and drop the item from
        // the end-of-request pass so it is not indexed twice. Failures are
        // logged and never rethrown, since this runs inside the flagging save
        // and an exception would roll back the vote. After a failure the
        // end-of-request pass remains as a fallback.
        if ($index->status() && !$index->isReadOnly() && $index->getOption('index_directly')) {
          $combined_ids = array_map(function ($item_id) use ($datasource_id) {
            return Utility::createCombinedId($datasource_id, $item_id);
          }, $updated_item_ids);
          try {
            $items = $index->loadItemsMultiple($combined_ids);
            if ($items) {
              $index->indexSpecificItems($items);
            }
            $this->postRequestIndexing->removeFromIndexing($index->id(), $combined_ids);
          }
          catch (\Exception $e) {
            Error::logException($this->logger, $e);
          }
        }
      }
    }
  }

  /**
   * Gets the Search API indexes that contain the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   The indexes.
   */
  protected function getIndexesForEntity(ContentEntityInterface $entity): array {
    return ContentEntity::getIndexesForEntity($entity);
  }

}
