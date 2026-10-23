<?php

namespace Drupal\Tests\webform_submission_search_api\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\flag\FlagCountManagerInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_submission_search_api\Plugin\search_api\processor\WebformSubmissionVotes;

/**
 * Tests the vote count processor.
 *
 * @group webform_submission_search_api
 */
class WebformSubmissionVotesProcessorTest extends UnitTestCase {

  /**
   * Runs addFieldValues() and returns the values added to the field.
   *
   * @param array $counts
   *   The flag counts returned by the flag count service.
   *
   * @return array
   *   The values added to the field.
   */
  protected function emitted(array $counts): array {
    $submission = $this->createMock(WebformSubmissionInterface::class);

    $flag_count = $this->createMock(FlagCountManagerInterface::class);
    $flag_count->method('getEntityFlagCounts')
      ->with($submission)
      ->willReturn($counts);
    $container = new ContainerBuilder();
    $container->set('flag.count', $flag_count);
    \Drupal::setContainer($container);

    $object = $this->createMock(ComplexDataInterface::class);
    $object->method('getValue')->willReturn($submission);

    $values = [];
    $field = $this->createMock(FieldInterface::class);
    $field->method('addValue')->willReturnCallback(function ($value) use (&$values) {
      $values[] = $value;
    });

    $item = $this->createMock(ItemInterface::class);
    $item->method('getOriginalObject')->willReturn($object);
    $item->method('getFields')->willReturn(['f' => $field]);

    $helper = $this->createMock(FieldsHelperInterface::class);
    $helper->expects($this->once())
      ->method('filterForPropertyPath')
      ->with(['f' => $field], NULL, 'search_api_webform_submission_vote_count')
      ->willReturn(['f' => $field]);

    $processor = new WebformSubmissionVotes([], 'x', []);
    $processor->setFieldsHelper($helper);
    $processor->addFieldValues($item);
    return $values;
  }

  /**
   * Tests the property definitions.
   */
  public function testPropertyDefinitions(): void {
    $processor = new WebformSubmissionVotes([], 'x', []);
    $processor->setStringTranslation($this->getStringTranslationStub());

    $properties = $processor->getPropertyDefinitions(NULL);
    $this->assertSame(['search_api_webform_submission_vote_count'], array_keys($properties));
    $this->assertSame('integer', $properties['search_api_webform_submission_vote_count']->getDataType(), 'Vote count is an integer so it sorts numerically.');

    $datasource = $this->createMock(DatasourceInterface::class);
    $this->assertSame([], $processor->getPropertyDefinitions($datasource), 'Datasource properties are empty.');
  }

  /**
   * Tests that no flaggings emit a count of 0.
   */
  public function testNoFlaggings(): void {
    $this->assertSame([0], $this->emitted([]));
  }

  /**
   * Tests that other flags without an upvote emit a count of 0.
   */
  public function testOtherFlagsOnly(): void {
    $this->assertSame([0], $this->emitted(['outdated' => '2']));
  }

  /**
   * Tests that the upvote count is cast to an integer.
   */
  public function testUpvoteCountIsInteger(): void {
    $this->assertSame([10], $this->emitted(['upvote' => '10']), 'Numeric strings from the database become integers.');
    $this->assertSame([3], $this->emitted(['upvote' => '3', 'outdated' => '1']));
  }

}
