# AI Content Impact Analyzer

A Drupal module that analyzes and scores content impact using multi-dimensional heuristics. Built for the Drupal AI Hackathon 2026, this module evaluates how effectively your content drives community value by examining word count, keyword relevance, readability, media richness, internal linking, and content freshness.

## What It Does

The AI Content Impact Analyzer automatically scores every node on your Drupal site across six dimensions and produces a single 0-100 impact score:

- **Word Count** -- Longer, more substantive content scores higher (up to 20 points).
- **Keyword Relevance** -- Presence of high-value keywords like "community," "accessibility," and "open source" adds bonus points (up to 15 points per keyword).
- **Readability** -- Measures average sentence length; content with clear, digestible sentences (10-20 words per sentence) scores highest (up to 15 points).
- **Media Richness** -- Detects `<img>` tags and embedded media (`<iframe>`, `<video>`, `<audio>`) to reward visually enriched content (up to 15 points).
- **Internal Linking** -- Counts internal links to other pages on your site, encouraging interconnected content (up to 10 points).
- **Freshness Factor** -- Optionally factors in how recently content was created or updated. Newer content receives a freshness bonus that decays over time (up to 10 points).

Scores are categorized as:
- **High Impact** (above 80): Content is well-optimized and drives strong community engagement.
- **Moderate Impact** (51-80): Content has good foundations but could be improved.
- **Needs Improvement** (50 or below): Content would benefit from more depth, media, or structural refinement.

## How Scoring Works

The scoring engine (`ImpactScorer` service) strips HTML tags for text analysis, then computes a weighted score across all dimensions. Each dimension contributes independently to the total, which is capped at 100. The breakdown is returned alongside the overall score so editors can see exactly where their content excels or needs work.

```
Total Score = min(100, word_count_score + keyword_bonus + readability_score
              + media_score + linking_score + freshness_score)
```

## Installation

1. Clone or download this module into your Drupal modules directory:
   ```bash
   cd /path/to/drupal/modules/custom
   git clone https://github.com/<your-org>/drupal-ai-content-impact-analyzer.git
   ```

2. Enable the module via Drush or the Drupal admin UI:
   ```bash
   drush en ai_content_impact_analyzer -y
   ```

3. Clear caches:
   ```bash
   drush cr
   ```

## Configuration

- Navigate to **Admin > Configuration > AI > Impact Analyzer** (`/admin/config/ai/impact-analyzer`) to view the Impact Dashboard.
- The dashboard lists all nodes with their computed impact scores and status labels.
- The module automatically scores content on every node insert and update via `hook_entity_insert()` and `hook_entity_update()`.
- Use the **"Analyze Content Impact (AI)"** bulk action on the Content admin page to trigger scoring for selected nodes on demand.

No additional configuration forms are required -- the module works out of the box with sensible defaults.

## Architecture Overview

```
drupal_ai_content_impact_analyzer/
  drupal_ai_content_impact_analyzer.info.yml    # Module metadata
  drupal_ai_content_impact_analyzer.services.yml # Service definitions
  drupal_ai_content_impact_analyzer.routing.yml  # Dashboard route
  drupal_ai_content_impact_analyzer.module       # Hook implementations
  src/
    Service/
      ImpactScorer.php          # Core scoring engine (6 dimensions)
    Controller/
      DashboardController.php   # Admin dashboard showing all node scores
    Plugin/
      Action/
        AnalyzeImpact.php       # Bulk action plugin for VBO integration
  tests/
    src/
      Unit/
        ImpactScorerTest.php    # PHPUnit tests for the scoring engine
```

**Key components:**

- **ImpactScorer** (`Service/ImpactScorer.php`): The heart of the module. A Drupal service registered as `ai_content_impact_analyzer.impact_scorer` that accepts raw text (and optional metadata) and returns a structured score array with per-dimension breakdowns.
- **DashboardController** (`Controller/DashboardController.php`): Renders a table at `/admin/config/ai/impact-analyzer` displaying every node's title, impact score, and status.
- **AnalyzeImpact** (`Plugin/Action/AnalyzeImpact.php`): An Action plugin that integrates with Drupal's bulk operations, allowing editors to score selected nodes from the admin content listing.
- **Module hooks**: Automatically trigger scoring whenever a node is created or updated, logging results for audit and debugging.

## Extending the Scorer

The `ImpactScorer::calculateScore()` method is designed to be extended. To add a new scoring dimension:

1. **Add a private scoring method** to `ImpactScorer.php`:
   ```php
   private function scoreSentiment(string $text): int {
     // Your logic here.
     return $score; // 0 to max points for this dimension.
   }
   ```

2. **Call it from `calculateScore()`** and add the result to the total:
   ```php
   $score += $this->scoreSentiment($clean_text);
   ```

3. **Include it in the breakdown array** returned by `calculateScore()`:
   ```php
   'sentiment' => $this->scoreSentiment($clean_text),
   ```

4. **Write a test** in `tests/src/Unit/ImpactScorerTest.php` to validate the new dimension.

You can also **subclass ImpactScorer** and override it in `services.yml` to provide a completely custom scoring strategy without modifying the original service.

## Running Tests

```bash
# From the module root directory
./vendor/bin/phpunit --configuration phpunit.xml.dist
```

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
