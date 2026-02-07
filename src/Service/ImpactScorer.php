<?php

namespace Drupal\ai_content_impact_analyzer\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to score content impact using AI.
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
   *   The content text to analyze.
   *
   * @return array
   *   An array containing the score (0-100) and a summary.
   */
  public function calculateScore(string $text): array {
    // In a real hackathon project, this would call Mistral AI via an API.
    // Here we simulate it based on text length and keywords.
    $word_count = str_word_count(strip_tags($text));
    $score = min(100, max(0, $word_count / 5)); // Simple heuristic.

    // Bonus for "impact" words.
    if (stripos($text, 'community') !== FALSE) {
      $score += 10;
    }
    if (stripos($text, 'drupal') !== FALSE) {
      $score += 10;
    }

    $score = min(100, $score);

    $this->loggerFactory->get('ai_content_impact_analyzer')->info('Calculated impact score: @score for text length @length', [
      '@score' => $score,
      '@length' => $word_count,
    ]);

    return [
      'score' => $score,
      'summary' => $score > 80 ? 'High Impact' : ($score > 50 ? 'Moderate Impact' : 'Needs Improvement'),
    ];
  }

}
