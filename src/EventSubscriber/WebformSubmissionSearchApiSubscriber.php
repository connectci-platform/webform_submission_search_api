<?php

namespace Drupal\webform_submission_search_api\EventSubscriber;

use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\webform_submission_search_api\WebformSubmissionSearchApiReindexService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class WebformSubmissionSearchApiSubscriber.
 */
class WebformSubmissionSearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\webform_submission_search_api\webformSubmissionSearchApiReindexService
   */
  protected $webformSubmissionSearchApiReindex;

  /**
   * Constructs a new FlagSearchApiSubscriber object.
   *
   * @param \Drupal\webform_submission_search_api\WebformSubmissionSearchApiReindexService $webform_submission_search_api_reindex_service
   *   webformSubmissionSearchApiReindexService.
   */
  public function __construct(WebformSubmissionSearchApiReindexService $webform_submission_search_api_reindex_service) {
    $this->webformSubmissionSearchApiReindex = $webform_submission_search_api_reindex_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after FlagCountManager (priority -100) has updated the flag counts,
    // so the vote count that gets indexed is current.
    $events['flag.entity_flagged'] = ['flagEntityFlagged', -200];
    $events['flag.entity_unflagged'] = ['flagEntityUnflagged', -200];

    return $events;
  }

  /**
   * Method is called whenever the flag.entity_flagged event is dispatched.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   Event.
   */
  public function flagEntityFlagged(FlaggingEvent $event) {
    if ($event->getFlagging()->getFlagId() == 'upvote') {
      $this->webformSubmissionSearchApiReindex->reindexItem($event->getFlagging());
    }
  }

  /**
   * Method is called whenever the flag.entity_unflagged event is dispatched.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   Event.
   */
  public function flagEntityUnflagged(UnflaggingEvent $event) {
    $flaggings = $event->getFlaggings();
    /** @var \Drupal\flag\FlaggingInterface $flagging */
    $flagging = reset($flaggings);
    if ($flagging->getFlagId() == 'upvote') {
      $this->webformSubmissionSearchApiReindex->reindexItem($flagging);
    }
  }

}
