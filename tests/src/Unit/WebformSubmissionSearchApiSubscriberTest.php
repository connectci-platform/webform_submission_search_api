<?php

namespace Drupal\Tests\webform_submission_search_api\Unit;

use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\FlagCountManager;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\flag\FlaggingInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_submission_search_api\EventSubscriber\WebformSubmissionSearchApiSubscriber;
use Drupal\webform_submission_search_api\WebformSubmissionSearchApiReindexService;

/**
 * Tests the flag event subscriber.
 *
 * @group webform_submission_search_api
 */
class WebformSubmissionSearchApiSubscriberTest extends UnitTestCase {

  /**
   * Creates a flagging mock for a flag ID.
   *
   * @param string $flag_id
   *   The flag ID.
   *
   * @return \Drupal\flag\FlaggingInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The flagging.
   */
  protected function flagging(string $flag_id): FlaggingInterface {
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn($flag_id);
    return $flagging;
  }

  /**
   * Data provider for flag IDs.
   *
   * @return array
   *   Rows of flag ID and whether a reindex is expected.
   */
  public static function flagIdProvider(): array {
    return [
      'upvote' => ['upvote', TRUE],
      'outdated' => ['outdated', FALSE],
      'not_useful' => ['not_useful', FALSE],
      'inaccurate' => ['inaccurate', FALSE],
    ];
  }

  /**
   * Tests the subscribed events.
   */
  public function testGetSubscribedEvents(): void {
    $events = WebformSubmissionSearchApiSubscriber::getSubscribedEvents();
    $this->assertSame(['flagEntityFlagged', -200], $events['flag.entity_flagged']);
    $this->assertSame(['flagEntityUnflagged', -200], $events['flag.entity_unflagged']);

    // Pin the ordering: the count must be updated before we index it.
    $flag_events = FlagCountManager::getSubscribedEvents();
    $this->assertLessThan($flag_events['flag.entity_flagged'][0][1], $events['flag.entity_flagged'][1]);
    $this->assertLessThan($flag_events['flag.entity_unflagged'][0][1], $events['flag.entity_unflagged'][1]);
  }

  /**
   * Tests that only upvote flagging triggers a reindex.
   *
   * @dataProvider flagIdProvider
   */
  public function testFlagged(string $flag_id, bool $expected): void {
    $flagging = $this->flagging($flag_id);
    $reindex = $this->createMock(WebformSubmissionSearchApiReindexService::class);
    if ($expected) {
      $reindex->expects($this->once())->method('reindexItem')->with($flagging);
    }
    else {
      $reindex->expects($this->never())->method('reindexItem');
    }
    (new WebformSubmissionSearchApiSubscriber($reindex))
      ->flagEntityFlagged(new FlaggingEvent($flagging));
  }

  /**
   * Tests that only upvote unflagging triggers a reindex.
   *
   * @dataProvider flagIdProvider
   */
  public function testUnflagged(string $flag_id, bool $expected): void {
    $flagging = $this->flagging($flag_id);
    $reindex = $this->createMock(WebformSubmissionSearchApiReindexService::class);
    if ($expected) {
      $reindex->expects($this->once())->method('reindexItem')->with($flagging);
    }
    else {
      $reindex->expects($this->never())->method('reindexItem');
    }
    (new WebformSubmissionSearchApiSubscriber($reindex))
      ->flagEntityUnflagged(new UnflaggingEvent([$flagging]));
  }

}
