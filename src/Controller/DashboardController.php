<?php

namespace Drupal\ai_content_impact_analyzer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_content_impact_analyzer\Service\ImpactScorer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Returns responses for AI Content Impact Analyzer routes.
 */
class DashboardController extends ControllerBase {

  /**
   * The impact scorer service.
   *
   * @var \Drupal\ai_content_impact_analyzer\Service\ImpactScorer
   */
  protected $impactScorer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DashboardController.
   *
   * @param \Drupal\ai_content_impact_analyzer\Service\ImpactScorer $impact_scorer
   *   The impact scorer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ImpactScorer $impact_scorer, EntityTypeManagerInterface $entity_type_manager) {
    $this->impactScorer = $impact_scorer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_content_impact_analyzer.impact_scorer'),
      $container->get('entity_type_manager')
    );
  }

  /**
   * Builds the dashboard.
   *
   * @return array
   *   The render array.
   */
  public function build() {
    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('AI Content Impact Dashboard'),
    ];

    $build['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This dashboard showcases the "Play to Impact" vision from the 2026 Drupal AI Hackathon, analyzing how content drives community value.'),
    ];

    // Example nodes to analyze.
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple();
    $rows = [];

    foreach ($nodes as $node) {
      $analysis = $this->impactScorer->calculateScore($node->getTitle() . ' ' . $node->get('body')->value);
      $rows[] = [
        $node->id(),
        $node->label(),
        $analysis['score'],
        $analysis['summary'],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Title'),
        $this->t('Impact Score'),
        $this->t('Status'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No content found to analyze.'),
    ];

    return $build;
  }

}
