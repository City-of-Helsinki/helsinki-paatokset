<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\paatokset_search\SearchManager;
use Elastic\Elasticsearch\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller class for decisions search page.
 */
class SearchController extends ControllerBase {

  public function __construct(
    private readonly SearchManager $searchManager,
    #[Autowire('paatokest_search.elastic_client')]
    private readonly Client $elasticClient,
  ) {
  }

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    $baseBuild = $this->searchManager->build('decisions', ['paatokset-search--decisions']);
    $description = [
      $this->t('The City of Helsinki primarily conducts its decision-making  in Finnish and Swedish. Although you can browse this service in English, the decision-making documents themselves are available in Finnish and Swedish only.', [], ['context' => 'Decisions search']),
      $this->t('Enter a keyword in the search field to find a specific decision. Results can be filtered by date, topic and decision-maker.', [], ['context' => 'Decisions search']),
      $this->t('If several decisions have been made on the same issue, the search result will initially show only the best match. To view the entire decision-making history associated with the issue, click to open the decision.', [], ['context' => 'Decisions search']),
    ];

    $build = array_merge($baseBuild, [
      '#description' => [
        '#type' => 'container',
        '#children' => array_map(fn ($text) => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $text,
        ], $description),
      ],
      '#theme' => 'decisions_search',
    ]);

    $operatorGuideUrl = $this->searchManager->getOperatorGuideUrl();
    if (!empty($operatorGuideUrl)) {
      $build['#description']['#children'][] = [
        '#type' => 'link',
        '#title' => $this->t('Read the instructions for refining your search.', [], ['context' => 'Decisions search']),
        '#url' => Url::fromUserInput($operatorGuideUrl),
      ];
    }

    return $build;
  }

  /**
   * Get autocomplete suggestions.
   *
   * @throws \Elastic\Elasticsearch\Exception\ElasticsearchException
   */
  public function autocomplete(Request $request): JsonResponse {
    if (!($q = $request->query->get('q'))) {
      throw new BadRequestHttpException("q parameter is required");
    }

    // See the autocomplete query in the React search app.
    $response = $this->elasticClient->search([
      'index' => 'paatokset_decisions',
      'body' => json_encode([
        "_source" => 'false',
        "aggs" => [
          "total_issues" => [
            "cardinality" => [
              "field" => "unique_issue_id",
              "precision_threshold" => 10000,
            ],
          ],
        ],
        "fields" => ["subject"],
        "collapse" => ["field" => "subject.keyword"],
        "from" => 0,
        "size" => 5,
        "query" => [
          "function_score" => [
            "functions" => [
            [
              "gauss" => [
                "meeting_date" => [
                  "decay" => 0.5,
                  "origin" => "now",
                  "scale" => "365d",
                ],
              ],
              "weight" => 50,
            ],
            ],
            "boost_mode" => "sum",
            "score_mode" => "sum",
            "query" => [
              "bool" => [
                "filter" => [
                [
                  "exists" => [
                    "field" => "meeting_date",
                  ],
                ],
                ],
                "minimum_should_match" => 1,
                "should" => [
                [
                  "multi_match" => [
                    "fields" => ["subject^5", "issue_subject^2"],
                    "fuzziness" => 1,
                    "operator" => "or",
                    "query" => $q,
                    "type" => "best_fields",
                  ],
                ],
                [
                  "multi_match" => [
                    "fields" => ["subject^5", "issue_subject^2"],
                    "boost" => 2,
                    "operator" => "or",
                    "query" => $q,
                    "type" => "phrase",
                  ],
                ],
                [
                  "match" => [
                    "subject" => [
                      "boost" => 3,
                      "operator" => "and",
                      "query" => $q,
                    ],
                  ],
                ],
                [
                  "constant_score" => [
                    "boost" => 1,
                    "filter" => [
                      "match" => [
                        "decision_content" => $q,
                      ],
                    ],
                  ],
                ],
                [
                  "constant_score" => [
                    "boost" => 1,
                    "filter" => [
                      "match" => [
                        "decision_motion" => $q,
                      ],
                    ],
                  ],
                ],
                ],
              ],
            ],
          ],
        ],
        "sort" => [
          ["_score" => "desc"],
          ["meeting_date" => "desc"],
        ],
      ]),
    ]);

    $suggestions = [];

    foreach ($response->asObject()->hits?->hits as $hit) {
      $subject = array_first($hit->fields->subject);
      $suggestions[] = [
        'label' => $subject,
        'value' => $subject,
      ];
    }

    return new JsonResponse($suggestions);
  }

}
