<?php

namespace Drupal\Tests\webform_submission_search_api\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\PostRequestIndexingInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_submission_search_api\WebformSubmissionSearchApiReindexService;
use Psr\Log\LoggerInterface;

/**
 * Tests the immediate reindexing of flagged submissions.
 *
 * @group webform_submission_search_api
 */
class WebformSubmissionSearchApiReindexServiceTest extends UnitTestCase {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The post request indexing service.
   *
   * @var \Drupal\search_api\Utility\PostRequestIndexingInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $postRequestIndexing;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->postRequestIndexing = $this->createMock(PostRequestIndexingInterface::class);
  }

  /**
   * Creates the service under test, returning the given indexes.
   *
   * @param \Drupal\search_api\IndexInterface[] $indexes
   *   The indexes for the flagged entity.
   *
   * @return \Drupal\webform_submission_search_api\WebformSubmissionSearchApiReindexService
   *   The service.
   */
  protected function service(array $indexes): WebformSubmissionSearchApiReindexService {
    return new class($this->logger, $this->postRequestIndexing, $indexes) extends WebformSubmissionSearchApiReindexService {

      /**
       * The indexes to return.
       *
       * @var array
       */
      protected $indexes;

      /**
       * {@inheritdoc}
       */
      public function __construct($logger, $post_request_indexing, array $indexes) {
        parent::__construct($logger, $post_request_indexing);
        $this->indexes = $indexes;
      }

      /**
       * {@inheritdoc}
       */
      protected function getIndexesForEntity(ContentEntityInterface $entity): array {
        return $this->indexes;
      }

    };
  }

  /**
   * Creates a flagging for submission 5 with an English translation only.
   *
   * @return \Drupal\flag\FlaggingInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The flagging.
   */
  protected function flagging(): FlaggingInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getTranslationLanguages')->willReturn(['en' => new \stdClass()]);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlaggableType')->willReturn('webform_submission');
    $flagging->method('getFlaggableId')->willReturn('5');
    $flagging->method('getFlaggable')->willReturn($entity);
    return $flagging;
  }

  /**
   * Creates an index mock.
   *
   * @param bool $status
   *   Whether the index is enabled.
   * @param bool $index_directly
   *   The index_directly option.
   *
   * @return \Drupal\search_api\IndexInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The index.
   */
  protected function index(bool $status, bool $index_directly): IndexInterface {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('kb');
    $index->method('status')->willReturn($status);
    $index->method('isReadOnly')->willReturn(FALSE);
    $index->method('getOption')->with('index_directly')->willReturn($index_directly);
    $index->method('loadItemsMultiple')->willReturn(['item']);
    return $index;
  }

  /**
   * Tests that a disabled index is tracked but not indexed.
   */
  public function testDisabledIndex(): void {
    $index = $this->index(FALSE, TRUE);
    $index->expects($this->once())->method('trackItemsUpdated')
      ->with('entity:webform_submission', ['5:en']);
    $index->expects($this->never())->method('indexSpecificItems');
    $this->postRequestIndexing->expects($this->never())->method('removeFromIndexing');
    $this->service([$index])->reindexItem($this->flagging());
  }

  /**
   * Tests that an index without index_directly is not indexed immediately.
   */
  public function testNoIndexDirectly(): void {
    $index = $this->index(TRUE, FALSE);
    $index->expects($this->once())->method('trackItemsUpdated');
    $index->expects($this->never())->method('indexSpecificItems');
    $this->postRequestIndexing->expects($this->never())->method('removeFromIndexing');
    $this->service([$index])->reindexItem($this->flagging());
  }

  /**
   * Tests that an enabled index is indexed once and removed from the queue.
   */
  public function testIndexedImmediately(): void {
    $index = $this->index(TRUE, TRUE);
    $index->expects($this->once())->method('trackItemsUpdated');
    $index->expects($this->once())->method('indexSpecificItems')->with(['item']);
    $this->postRequestIndexing->expects($this->once())->method('removeFromIndexing')
      ->with('kb', ['entity:webform_submission/5:en']);
    $this->logger->expects($this->never())->method('log');
    $this->service([$index])->reindexItem($this->flagging());
  }

  /**
   * Tests that a backend failure is logged, not rethrown, and falls back.
   */
  public function testBackendFailure(): void {
    $index = $this->index(TRUE, TRUE);
    $index->expects($this->once())->method('trackItemsUpdated');
    $index->method('indexSpecificItems')->willThrowException(new \RuntimeException('Backend down'));
    $this->postRequestIndexing->expects($this->never())->method('removeFromIndexing');
    $this->logger->expects($this->once())->method('log')
      ->with($this->anything(), $this->anything(), $this->anything());
    $this->service([$index])->reindexItem($this->flagging());
  }

}
