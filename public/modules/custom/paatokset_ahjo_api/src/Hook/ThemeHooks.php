<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paatokset_ahjo_api\Entity\AhjoCase;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;

/**
 * Theme hooks.
 */
class ThemeHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CaseService $caseService,
    private readonly MeetingService $meetingService,
    private readonly PolicymakerService $policymakerService,
  ) {
  }

  /**
   * Implements hook_theme().
   *
   * @phpstan-param array<string, mixed> $existing
   * @phpstan-return array<string, mixed>
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'ahjo_case' => [
        'render element' => 'elements',
        'initial preprocess' => self::class . ':preprocessAhjoCase',
      ],
      'ahjo_initiative' => [
        'render element' => 'elements',
        'template' => 'ahjo-initiative',
        'initial preprocess' => self::class . ':preprocessAhjoInitiative',
      ],
      'organization_path' => [
        'variables' => [
          'organizations' => [],
        ],
        'path' => $path . '/templates/component',
      ],
      'decision_content' => [
        'variables' => [
          'selectedDecision' => NULL,
          'policymaker_is_active' => NULL,
          'selected_class' => NULL,
          'decision_org_name' => NULL,
          'organization_type_name' => NULL,
          'decision_content' => NULL,
          'decision_section' => NULL,
          'vote_results' => NULL,
        ],
      ],
      'case_attachments' => [
        'variables' => [
          'attachments' => NULL,
        ],
      ],
      'decision_navigation' => [
        'variables' => [
          'next_decision' => NULL,
          'previous_decision' => NULL,
        ],
      ],
      'node__meeting' => [
        'render element' => 'elements',
        'template' => 'node--meeting',
        'path' => $path . '/templates/content',
        'base hook' => 'node',
      ],
      'block__frontpage_calendar' => [
        'base hook' => 'entity',
        'template' => 'block/block--frontpage-calendar',
      ],
      'committees_and_boards_listing' => [
        'variables' => [
          'committees_and_boards' => [],
        ],
      ],
      'block__decision_tree' => [
        'base hook' => 'entity',
        'template' => 'block/block--decision-tree',
      ],
      'block__meetings_calendar' => [
        'base hook' => 'entity',
        'template' => 'block/block--meetings-calendar',
      ],
      'policymaker_side_navigation' => [
        'variables' => [
          'items' => [],
          'menu_attributes' => NULL,
          'menu_link_parent' => NULL,
        ],
      ],
      'policymaker_side_navigation_mobile' => [
        'variables' => [
          'items' => [],
          'current_option' => NULL,
        ],
      ],
      'policymaker_browser' => [
        'variables' => [
          'organization' => NULL,
          'children' => NULL,
          'policymakers' => NULL,
          'breadcrumb_org_id' => NULL,
          'breadcrumb_label' => NULL,
          'tags' => NULL,
          'search_link' => NULL,
        ],
      ],
      'policymaker_browser_hero_block' => [
        'variables' => [
          'hero_title' => NULL,
          'first_paragraph_bg' => NULL,
          'hero_description' => NULL,
        ],
        'template' => 'policymaker-browser-hero-block',
      ],
    ];
  }

  /**
   * Prepares variables for ahjo_case templates.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  public function preprocessAhjoCase(array &$variables): void {
    $case = $variables['elements']['#ahjo_case'];
    assert($case instanceof AhjoCase);

    $variables['decision'] = $case->getDefaultDecision();
    $variables['case'] = $case;
  }

  /**
   * Prepares variables for ahjo_initiative templates.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  public function preprocessAhjoInitiative(array &$variables): void {
    $variables['entity'] = $variables['elements']['#ahjo_initiative'];

    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Preprocess meetings calendar block.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_block__meetings_calendar')]
  public function preprocessMeetingsCalendar(array &$variables): void {
    $variables['#attached']['library'][] = 'hdbt_subtheme/meetings-calendar';

    $params = [
      'from' => date("Y-m-d"),
    ];

    // Helpful $content variable for templates.
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
    if (isset($variables['elements']['#block__meetings_calendar'])) {
      $variables['entity'] = $variables['elements']['#block__meetings_calendar'];
    }

    $all_meetings = array_merge(...array_values($this->meetingService->elasticQuery($params)));

    if (count($all_meetings) > 8) {
      $all_meetings = array_slice($all_meetings, 0, 8);
    }

    $meeting_calendar_description = $this->configFactory
      ->get('paatokset_ahjo_api.default_texts')
      ->get('meeting_calendar_description');
    if ($meeting_calendar_description !== NULL) {
      $variables['meeting_calendar_description'] = _paatokset_ahjo_api_render_default_text($meeting_calendar_description);
    }

    $variables['open_motions'] = $this->t('Open agenda');
    $variables['open_minutes'] = $this->t('Open minutes');
    $variables['open_meeting_calendar'] = $this->t('Open meeting calendar');
    $variables['meetings'] = $all_meetings;
  }

  /**
   * Preprocess frontpage calendar block.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_block__frontpage_calendar')]
  public function preprocessFrontpageCalendar(array &$variables): void {
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

    $params = [
      'from' => date("Y-m-d"),
      'not_cancelled' => TRUE,
      'limit' => 8,
    ];

    // Helpful $content variable for templates.
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
    if (isset($variables['elements']['#block__policymaker_calendar'])) {
      $variables['entity'] = $variables['elements']['#block__policymaker_calendar'];
    }

    $all_meetings = array_merge(...array_values($this->meetingService->elasticQuery($params)));

    $meetings_with_color = [];
    foreach ($all_meetings as $meeting) {
      $policymaker = $this->policymakerService->getPolicyMaker($meeting['policymaker']);
      $meeting['organization_color'] = Html::cleanCssIdentifier($policymaker?->getPolicymakerClass() ?? 'color-board');
      $meetings_with_color[] = $meeting;
    }

    $variables['open_motions'] = $this->t('Open agenda');
    $variables['open_decisions'] = $this->t('Open decision announcement');
    $variables['open_minutes'] = $this->t('Open minutes');
    $variables['calendar_link'] = match ($currentLanguage) {
      'fi' => '/fi/kokouskalenteri',
      'sv' => '/sv/moteskalender',
      'en' => '/en/meetings-calendar',
      default => NULL,
    };

    $calendar_notice = $this->configFactory
      ->get('paatokset_ahjo_api.default_texts')
      ->get('calendar_notice_text');
    if ($calendar_notice !== NULL) {
      $variables['calendar_notice'] = $calendar_notice;
    }

    $variables['open_meeting_calendar'] = $this->t('Open meeting calendar');
    $variables['meetings'] = $meetings_with_color;
  }

  /**
   * Preprocess decision tree block.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_block__decision_tree')]
  public function preprocessDecisionTree(array &$variables): void {
    $defaults_config = $this->configFactory->get('paatokset_ahjo_api.default_texts');
    $committees_url = $defaults_config->get('committees_boards_url') ?: '/fi/paattajat';
    $office_holders_url = $defaults_config->get('office_holders_url') ?: '/fi/paattajat';

    // Helpful $content variable for templates.
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
    if (isset($variables['elements']['#block__decision_tree'])) {
      $variables['entity'] = $variables['elements']['#block__decision_tree'];
    }

    $municipality_url = $this->policymakerService->getPolicymaker('00400')?->toUrl()->toString();
    $council_url = $this->policymakerService->getPolicymaker('02900')?->toUrl()->toString();

    $variables['decision_process'] = [
      '0' => [
        'title' => $this->t('Initiation'),
      ],
      '1' => [
        'title' => $this->t('Committees and Boards'),
        'link' => $committees_url,
      ],
      '2' => [
        'title' => $this->t('City board'),
        'link' => $municipality_url,
      ],
      '3' => [
        'title' => $this->t('City council'),
        'link' => $council_url,
      ],
      '4' => [
        'title' => $this->t('Implementation'),
      ],
    ];

    $variables['decisionmaker_process'] = [
      '0' => [
        'title' => $this->t('Initiation'),
      ],
      '1' => [
        'title' => $this->t('Office holder decision'),
        'link' => $office_holders_url,
      ],
      '2' => [
        'title' => $this->t('Implementation'),
      ],
    ];

    $variables['info_1'] = $this->t('Standard decision-making process');
    $variables['info_2'] = $this->t('Standard process for office holder decisions');
  }

  /**
   * Preprocess case nodes.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_node__case__full')]
  public function preprocessCase(array &$variables): void {
    $case = $variables['node'];
    assert($case instanceof CaseBundle);

    // Multiple decisions can relate to a single case. Pick the
    // most recent decision and display it in case node page.
    $decision = array_first($case->getAllDecisions());

    $this->setDecisionVariables($variables, $case, $decision);

    // @fixme should not case all cases with node_list:decision.
    $variables['#cache'] = [
      'tags' => ['node_list:decision'],
      'contexts' => ['languages:language_interface'],
    ];
  }

  /**
   * Preprocess decision nodes.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_node__decision__full')]
  public function preprocessDecision(array &$variables): void {
    $decision = $variables['node'];
    assert($decision instanceof Decision);

    $this->setDecisionVariables($variables, $decision->getCase(), $decision);

    // @fixme should not case all decisions with node_list:decision & node_list:case.
    $variables['#cache'] = [
      'tags' => [
        'node_list:decision',
        'node_list:case',
      ],
    ];
  }

  /**
   * Sets template variables for case and decision pages.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  private function setDecisionVariables(array &$variables, ?CaseBundle $case, ?Decision $decision): void {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $policymaker = $decision?->getPolicymaker($langcode);
    $organization_type = $policymaker?->getOrganizationType();

    // Case migration sets 'NO TITLE' as a default
    // value, if the case has no title in Ahjo.
    $variables['main_heading'] = $case?->get('field_full_title')?->getString() ?: $decision?->getDecisionHeading() ?: '';
    $variables['no_title_for_case'] = $case instanceof CaseBundle && (empty($case->getTitle()) || $case->getTitle() === 'NO TITLE');

    $variables['case'] = $case;
    $variables['selectedDecision'] = $decision;

    $variables['decision_pdf'] = $decision?->getDecisionPdf();
    $variables['policymaker_is_active'] = $policymaker?->isActive() ?? FALSE;
    $variables['decision_section'] = $decision?->getFormattedDecisionSection();
    $variables['decision_org_name'] = !$organization_type?->isCouncilOrBoard() ? ($policymaker?->getPolicymakerName() ?? $decision?->getDecisionMakerOrgName()) : NULL;
    $variables['organization_type_name'] = $this->policymakerService->getPolicymakerTypeFromNode($policymaker) ?? NULL;
    $variables['selected_label'] = $decision ? $this->caseService->formatDecisionLabel($decision, $policymaker) : NULL;
    $variables['selected_class'] = Html::cleanCssIdentifier($policymaker?->getPolicymakerClass() ?? 'color-board');
    $variables['attachments'] = $decision?->getAttachments() ?? [];
    $variables['all_decisions'] = $case ? $this->caseService->getDecisionsList($case) : NULL;

    if ($decision && $adjacent = $case?->getNextDecision($decision)) {
      $variables['next_decision'] = $adjacent;
    }

    if ($decision && $adjacent = $case?->getPrevDecision($decision)) {
      $variables['previous_decision'] = $adjacent;
    }

    $variables['decision_content'] = $decision?->parseContent() ?? [];
    $variables['all_decisions_link'] = $decision?->getDecisionMeetingLink();
    $variables['other_decisions_link'] = $policymaker?->getDecisionsRoute($langcode);
    $variables['vote_results'] = $decision?->getVotingResults() ?? [];
  }

}
