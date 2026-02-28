<?php

namespace Drupal\ai_content_impact_analyzer\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to score content impact using multi-dimensional heuristics.
 *
 * Evaluates content across six dimensions: word count, keyword relevance,
 * readability (sentence length), media richness (images/embeds), internal
 * linking, and freshness factor.
 */
class ImpactScorer {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new ImpactScorer.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Calculates the impact score of a given text.
   *
   * @param string $text
   *   The content text (may contain HTML) to analyze.
   * @param array $options
   *   Optional metadata for scoring:
   *   - 'created': Unix timestamp of content creation.
   *   - 'changed': Unix timestamp of last update.
   *   - 'base_url': Base URL for detecting internal links (e.g., 'example.com').
   *
   * @return array
   *   An associative array containing:
   *   - 'score': The total impact score (0-100).
   *   - 'summary': A human-readable label (High Impact, Moderate Impact, or
   *     Needs Improvement).
   *   - 'breakdown': Per-dimension score breakdown.
   */
  public function calculateScore(string $text, array $options = []): array {
    $clean_text = strip_tags($text);

    // Dimension 1: Word count score (0-20 points).
    $word_count_score = $this->scoreWordCount($clean_text);

    // Dimension 2: Keyword relevance bonus (0-65 points).
    $keyword_score = $this->scoreKeywords($clean_text);

    // Dimension 3: Readability score based on sentence length (0-15 points).
    $readability_score = $this->scoreReadability($clean_text);

    // Dimension 4: Media richness -- images and embeds (0-15 points).
    $media_score = $this->scoreMediaRichness($text);

    // Dimension 5: Internal linking (0-10 points).
    $linking_score = $this->scoreInternalLinking($text, $options['base_url'] ?? '');

    // Dimension 6: Freshness factor (0-10 points).
    $freshness_score = $this->scoreFreshness($options);

    $total = $word_count_score + $keyword_score + $readability_score
      + $media_score + $linking_score + $freshness_score;
    $total = min(100, max(0, $total));

    $summary = $total > 80 ? 'High Impact' : ($total > 50 ? 'Moderate Impact' : 'Needs Improvement');

    $this->loggerFactory->get('ai_content_impact_analyzer')->info(
      'Calculated impact score: @score for text length @length',
      [
        '@score' => $total,
        '@length' => str_word_count($clean_text),
      ]
    );

    return [
      'score' => $total,
      'summary' => $summary,
      'breakdown' => [
        'word_count' => $word_count_score,
        'keywords' => $keyword_score,
        'readability' => $readability_score,
        'media' => $media_score,
        'linking' => $linking_score,
        'freshness' => $freshness_score,
      ],
    ];
  }

  /**
   * Scores content based on word count.
   *
   * Longer content tends to be more comprehensive. Caps at 20 points
   * (reached at 500+ words).
   *
   * @param string $clean_text
   *   The plain text (HTML stripped).
   *
   * @return int
   *   Score from 0 to 20.
   */
  private function scoreWordCount(string $clean_text): int {
    $word_count = str_word_count($clean_text);
    // 500+ words = full 20 points.
    return (int) min(20, ($word_count / 500) * 20);
  }

  /**
   * Scores content based on presence of high-value keywords.
   *
   * Each keyword adds a fixed bonus when found in the text.
   *
   * @param string $clean_text
   *   The plain text (HTML stripped).
   *
   * @return int
   *   Keyword bonus score (uncapped, but total is capped by caller).
   */
  private function scoreKeywords(string $clean_text): int {
    $impact_keywords = [
      'community' => 10,
      'drupal' => 10,
      'accessibility' => 15,
      'sustainability' => 15,
      'open source' => 10,
      'governance' => 5,
    ];

    $score = 0;
    foreach ($impact_keywords as $keyword => $bonus) {
      if (stripos($clean_text, $keyword) !== FALSE) {
        $score += $bonus;
      }
    }

    return $score;
  }

  /**
   * Scores content readability based on average sentence length.
   *
   * The ideal average sentence length is 10-20 words. Content that hits
   * this range scores the full 15 points. Very short sentences (< 5 words
   * avg) or very long sentences (> 35 words avg) receive fewer points.
   *
   * @param string $clean_text
   *   The plain text (HTML stripped).
   *
   * @return int
   *   Score from 0 to 15.
   */
  private function scoreReadability(string $clean_text): int {
    // Split into sentences using common punctuation.
    $sentences = preg_split('/[.!?]+/', $clean_text, -1, PREG_SPLIT_NO_EMPTY);
    $sentences = array_filter($sentences, function ($s) {
      return str_word_count(trim($s)) > 0;
    });

    if (empty($sentences)) {
      return 0;
    }

    $total_words = 0;
    foreach ($sentences as $sentence) {
      $total_words += str_word_count(trim($sentence));
    }

    $avg_sentence_length = $total_words / count($sentences);

    // Ideal range: 10-20 words per sentence = full 15 points.
    if ($avg_sentence_length >= 10 && $avg_sentence_length <= 20) {
      return 15;
    }

    // Slightly outside ideal: 5-10 or 20-30 words.
    if ($avg_sentence_length >= 5 && $avg_sentence_length < 10) {
      return 10;
    }
    if ($avg_sentence_length > 20 && $avg_sentence_length <= 30) {
      return 10;
    }

    // Far from ideal: < 5 or > 30 words.
    if ($avg_sentence_length > 30 && $avg_sentence_length <= 35) {
      return 5;
    }

    // Very poor readability.
    return 3;
  }

  /**
   * Scores content based on presence of images and embedded media.
   *
   * Rewards content that includes visual and multimedia elements:
   * - Each <img> tag: 3 points (up to 9).
   * - Each <iframe>, <video>, or <audio> tag: 3 points (up to 6).
   * Maximum: 15 points.
   *
   * @param string $html
   *   The raw HTML content.
   *
   * @return int
   *   Score from 0 to 15.
   */
  private function scoreMediaRichness(string $html): int {
    $score = 0;

    // Count images (up to 3 for 9 points).
    $img_count = preg_match_all('/<img\b/i', $html);
    $score += min(9, $img_count * 3);

    // Count embeds: iframe, video, audio (up to 2 for 6 points).
    $embed_count = preg_match_all('/<(iframe|video|audio)\b/i', $html);
    $score += min(6, $embed_count * 3);

    return min(15, $score);
  }

  /**
   * Scores content based on internal links.
   *
   * Encourages interconnected content by rewarding links to other pages
   * on the same site. Each internal link adds 2 points, up to 10 points.
   *
   * A link is considered "internal" if:
   * - It starts with "/" (relative link), or
   * - Its href contains the provided base_url.
   *
   * @param string $html
   *   The raw HTML content.
   * @param string $base_url
   *   The site base URL for detecting internal links.
   *
   * @return int
   *   Score from 0 to 10.
   */
  private function scoreInternalLinking(string $html, string $base_url = ''): int {
    // Match all href attributes in anchor tags.
    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);

    if (empty($matches[1])) {
      return 0;
    }

    $internal_count = 0;
    foreach ($matches[1] as $href) {
      $href = trim($href);
      // Relative links are internal.
      if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
        $internal_count++;
      }
      // Links containing the base URL are internal.
      elseif (!empty($base_url) && stripos($href, $base_url) !== FALSE) {
        $internal_count++;
      }
    }

    return min(10, $internal_count * 2);
  }

  /**
   * Scores content freshness based on creation/update timestamps.
   *
   * Newer content receives higher scores. The freshness bonus decays
   * linearly over 180 days (6 months), after which no freshness bonus
   * is awarded.
   *
   * @param array $options
   *   Options array that may contain 'changed' or 'created' timestamps.
   *
   * @return int
   *   Score from 0 to 10.
   */
  private function scoreFreshness(array $options): int {
    $timestamp = $options['changed'] ?? $options['created'] ?? NULL;

    if ($timestamp === NULL) {
      return 0;
    }

    $now = time();
    $age_days = ($now - (int) $timestamp) / 86400;

    if ($age_days < 0) {
      return 10;
    }

    // Full score for content updated within the last week.
    if ($age_days <= 7) {
      return 10;
    }

    // Linear decay from 10 to 0 over 180 days.
    $max_age = 180;
    if ($age_days >= $max_age) {
      return 0;
    }

    return (int) round(10 * (1 - $age_days / $max_age));
  }

}
