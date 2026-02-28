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
   * The ImpactScorer instance under test.
   *
   * @var \Drupal\ai_content_impact_analyzer\Service\ImpactScorer
   */
  protected ImpactScorer $scorer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $this->scorer = new ImpactScorer($logger_factory);
  }

  /**
   * Tests that very short text receives a low score and "Needs Improvement".
   */
  public function testShortTextScoresLow(): void {
    $result = $this->scorer->calculateScore('Short text');

    $this->assertLessThanOrEqual(50, $result['score']);
    $this->assertEquals('Needs Improvement', $result['summary']);
    $this->assertArrayHasKey('breakdown', $result);
    $this->assertEquals(0, $result['breakdown']['media']);
    $this->assertEquals(0, $result['breakdown']['linking']);
    $this->assertEquals(0, $result['breakdown']['freshness']);
  }

  /**
   * Tests that long text with impact keywords scores high.
   */
  public function testLongTextWithKeywordsScoresHigh(): void {
    // Generate 600 words of content with impact keywords.
    $long_text = str_repeat(
      'drupal community accessibility sustainability open source governance. ',
      100
    );

    $result = $this->scorer->calculateScore($long_text);

    $this->assertGreaterThan(80, $result['score']);
    $this->assertEquals('High Impact', $result['summary']);
    $this->assertGreaterThan(0, $result['breakdown']['word_count']);
    $this->assertGreaterThan(0, $result['breakdown']['keywords']);
  }

  /**
   * Tests readability scoring with ideal sentence lengths (10-20 words).
   */
  public function testReadabilityWithIdealSentenceLength(): void {
    // Each sentence has roughly 12 words -- within the ideal 10-20 range.
    $text = 'The Drupal community is building great tools for the open web today. '
      . 'Content creators need robust scoring tools that help them improve quality. '
      . 'This module provides automated analysis of every piece of published content. '
      . 'Editors can see exactly where their articles need the most improvement now. '
      . 'The scoring engine evaluates multiple dimensions to produce a fair result.';

    $result = $this->scorer->calculateScore($text);

    $this->assertEquals(15, $result['breakdown']['readability']);
  }

  /**
   * Tests readability scoring with very short sentences (poor readability).
   */
  public function testReadabilityWithVeryShortSentences(): void {
    // Sentences averaging about 2-3 words.
    $text = 'Hello. World. Short. Tiny. Text. Here. Now. Done. Yes. No.';

    $result = $this->scorer->calculateScore($text);

    // Very short sentences should score low on readability (3 points).
    $this->assertLessThanOrEqual(5, $result['breakdown']['readability']);
  }

  /**
   * Tests media richness scoring with images and embeds.
   */
  public function testMediaRichnessScoring(): void {
    $html = '<p>Great content here.</p>'
      . '<img src="photo1.jpg" alt="Photo 1">'
      . '<img src="photo2.jpg" alt="Photo 2">'
      . '<video src="demo.mp4"></video>'
      . '<iframe src="https://youtube.com/embed/abc"></iframe>';

    $result = $this->scorer->calculateScore($html);

    // 2 images = 6 points, 2 embeds (video + iframe) = 6 points = 12 total.
    $this->assertEquals(12, $result['breakdown']['media']);
  }

  /**
   * Tests internal linking scoring with relative and absolute links.
   */
  public function testInternalLinkingScoring(): void {
    $html = '<p>Check out our <a href="/about">about page</a> and '
      . '<a href="/blog/post-1">latest blog post</a>. '
      . 'Also see <a href="https://example.com/docs">our docs</a> '
      . 'and <a href="https://external.org">an external site</a>.</p>';

    $result = $this->scorer->calculateScore($html, ['base_url' => 'example.com']);

    // 2 relative links (/about, /blog/post-1) + 1 base_url match = 3 links = 6 points.
    $this->assertEquals(6, $result['breakdown']['linking']);
  }

  /**
   * Tests freshness scoring with a recently updated timestamp.
   */
  public function testFreshnessWithRecentContent(): void {
    // Content updated 2 days ago.
    $two_days_ago = time() - (2 * 86400);

    $result = $this->scorer->calculateScore('Some content here.', [
      'changed' => $two_days_ago,
    ]);

    // Within 7 days should give full 10 points.
    $this->assertEquals(10, $result['breakdown']['freshness']);
  }

  /**
   * Tests freshness scoring with old content (no freshness bonus).
   */
  public function testFreshnessWithOldContent(): void {
    // Content last updated 200 days ago (beyond 180-day threshold).
    $old_timestamp = time() - (200 * 86400);

    $result = $this->scorer->calculateScore('Some content here.', [
      'changed' => $old_timestamp,
    ]);

    $this->assertEquals(0, $result['breakdown']['freshness']);
  }

  /**
   * Tests that the total score never exceeds 100.
   */
  public function testScoreNeverExceeds100(): void {
    // Construct content that maxes out every dimension.
    $keywords = 'drupal community accessibility sustainability open source governance';
    $sentences = [];
    for ($i = 0; $i < 50; $i++) {
      $sentences[] = "This is a well-structured sentence about $keywords and web content.";
    }
    $text = implode(' ', $sentences);

    // Add media and links.
    $text .= '<img src="a.jpg"><img src="b.jpg"><img src="c.jpg">';
    $text .= '<video src="v.mp4"></video><iframe src="e.html"></iframe>';
    $text .= '<a href="/page1">link1</a><a href="/page2">link2</a>'
      . '<a href="/page3">link3</a><a href="/page4">link4</a><a href="/page5">link5</a>';

    $result = $this->scorer->calculateScore($text, [
      'changed' => time(),
    ]);

    $this->assertLessThanOrEqual(100, $result['score']);
    $this->assertGreaterThanOrEqual(0, $result['score']);
  }

  /**
   * Tests that the breakdown array contains all expected dimension keys.
   */
  public function testBreakdownContainsAllDimensions(): void {
    $result = $this->scorer->calculateScore('Any text content.');

    $expected_keys = [
      'word_count',
      'keywords',
      'readability',
      'media',
      'linking',
      'freshness',
    ];

    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $result['breakdown'],
        "Breakdown should contain the '$key' dimension.");
    }
  }

  /**
   * Tests moderate impact range (score between 51 and 80).
   */
  public function testModerateImpactRange(): void {
    // Enough keywords to push past 50, but not enough for 80+.
    // "community" (10) + "drupal" (10) + "accessibility" (15) = 35 from keywords.
    // ~150 words = 6 word count points.
    // Good sentence structure = 15 readability.
    // Total should be around 56.
    $sentences = [];
    for ($i = 0; $i < 10; $i++) {
      $sentences[] = 'The drupal community values accessibility in all web projects today.';
    }
    $text = implode(' ', $sentences);

    $result = $this->scorer->calculateScore($text);

    $this->assertGreaterThan(50, $result['score']);
    $this->assertLessThanOrEqual(80, $result['score']);
    $this->assertEquals('Moderate Impact', $result['summary']);
  }

}
