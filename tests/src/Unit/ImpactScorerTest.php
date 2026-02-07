<?php

namespace Drupal\Tests\ai_content_impact_analyzer\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Drupal\ai_content_impact_analyzer\Service\ImpactScorer;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Tests the ImpactScorer service.
 */
#[Group('ai_content_impact_analyzer')]
class ImpactScorerTest extends TestCase {

  /**
   * Tests calculateScore.
   */
  public function testCalculateScore() {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $scorer = new ImpactScorer($logger_factory);

    // Test low score.
    $result = $scorer->calculateScore('Short text');
    $this->assertLessThan(50, $result['score']);
    $this->assertEquals('Needs Improvement', $result['summary']);

    // Test high score with keywords.
    $long_text = str_repeat('drupal community accessibility sustainability open source governance ', 100);
    $result = $scorer->calculateScore($long_text);
    $this->assertGreaterThan(80, $result['score']);
    $this->assertEquals('High Impact', $result['summary']);
  }

}
