<?php

namespace Drupal\ai_content_impact_analyzer\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ai_content_impact_analyzer\Service\ImpactScorer;

/**
 * Analyzes the impact of a node.
 *
 * @Action(
 *   id = "ai_content_impact_analyzer_analyze",
 *   label = @Translation("Analyze Content Impact (AI)"),
 *   type = "node"
 * )
 */
class AnalyzeImpact extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The impact scorer service.
   *
   * @var \Drupal\ai_content_impact_analyzer\Service\ImpactScorer
   */
  protected $scorer;

  /**
   * Constructs a new AnalyzeImpact object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_content_impact_analyzer\Service\ImpactScorer $scorer
   *   The impact scorer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImpactScorer $scorer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->scorer = $scorer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_content_impact_analyzer.impact_scorer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity || !$entity->hasField('body')) {
      return;
    }

    $text = $entity->get('body')->value;
    $result = $this->scorer->calculateScore($text ?? '');

    $this->messenger()->addMessage($this->t('AI Impact Analysis: @summary (Score: @score)', [
      '@summary' => $result['summary'],
      '@score' => $result['score'],
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
