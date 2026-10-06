<?php

namespace Drupal\Tests\webform_submission_search_api\Unit;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_submission_search_api\Plugin\search_api\processor\WebformSubmissionApproved;
use Drupal\webform_submission_search_api\Plugin\search_api\processor\WebformSubmissionPrivate;

/**
 * Tests the approved and private flag processors.
 *
 * @group webform_submission_search_api
 */
class WebformSubmissionFlagProcessorTest extends UnitTestCase {

  /**
   * Runs addFieldValues() and returns the values added to the field.
   *
   * @param string $class
   *   The processor class.
   * @param string $path
   *   The property path.
   * @param array $data
   *   The submission data.
   *
   * @return array
   *   The values added to the field.
   */
  protected function emitted(string $class, string $path, array $data): array {
    $submission = $this->createMock(WebformSubmissionInterface::class);
    $submission->method('getElementData')
      ->willReturnCallback(fn($key) => $data[$key] ?? NULL);

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
      ->with(['f' => $field], NULL, $path)
      ->willReturn(['f' => $field]);

    $processor = new $class([], 'x', []);
    $processor->setFieldsHelper($helper);
    $processor->addFieldValues($item);
    return $values;
  }

  /**
   * Tests the private processor.
   */
  public function testPrivate(): void {
    $class = WebformSubmissionPrivate::class;
    $path = 'search_api_webform_submission_private';
    $this->assertSame([0], $this->emitted($class, $path, []), 'Missing private emits 0.');
    $this->assertSame([0], $this->emitted($class, $path, ['private' => '0']));
    $this->assertSame([0], $this->emitted($class, $path, ['private' => '']));
    $this->assertSame([1], $this->emitted($class, $path, ['private' => '1']));
    $this->assertSame([1], $this->emitted($class, $path, ['private' => 1]));
    $this->assertSame([0], $this->emitted($class, $path, ['private' => '2']), 'Only 1 counts as private, as in isPublic().');
  }

  /**
   * Tests the approved processor.
   */
  public function testApproved(): void {
    $class = WebformSubmissionApproved::class;
    $path = 'search_api_webform_submission_approved';
    $this->assertSame([0], $this->emitted($class, $path, []), 'Missing approved emits 0.');
    $this->assertSame([0], $this->emitted($class, $path, ['approved' => '0']));
    $this->assertSame([1], $this->emitted($class, $path, ['approved' => '1']));
    $this->assertSame([0], $this->emitted($class, $path, ['approved' => 'yes']), 'Only 1 counts as approved, as in isPublic().');
  }

}
