<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Bundle class for decisions.
 */
class Decision extends Node implements AhjoUpdatableInterface {

  /**
   * Memoized result for self::getAllDecisions().
   */
  private ?CaseBundle $case = NULL;

  /**
   * Get diary (case) number.
   */
  public function getDiaryNumber(): string {
    return $this->get('field_diary_number')->getString();
  }

  /**
   * Get decision native id.
   */
  public function getNativeId(): ?string {
    return $this->get('field_decision_native_id')->value;
  }

  /**
   * Get normalized decision native id.
   *
   * Removes brackets from native ID and converts to lowercase.
   * Maybe the migration should do this, since only the UUID looking
   * parts are used, and we are doing a bunch of conversions all over
   * the place?
   */
  public function getNormalizedNativeId(): ?string {
    $nativeId = $this->getNativeId();

    if ($nativeId) {
      return strtolower(str_replace(['{', '}'], '', $nativeId));
    }

    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->getNormalizedNativeId();
  }

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    return Url::fromRoute('paatokset_ahjo_proxy.decisions_single', [
      'id' => $this->getNormalizedNativeId(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public static function getAhjoEndpoint(): string {
    return 'decisions';
  }

  /**
   * Gets decision maker org name that is stored in decision record.
   */
  public function getDecisionMakerOrgName(): ?string {
    if (!$this->get('field_dm_org_name')->isEmpty()) {
      return $this->get('field_dm_org_name')->value;
    }

    return NULL;
  }

  /**
   * Get meeting URL for selected decision.
   *
   * @todo This should use Drupal Links & Route Provider system.
   * Revisit this if these are converted to custom entities.
   * https://www.drupal.org/docs/drupal-apis/entity-api/introduction-to-entity-api-in-drupal-8#s-links-route-provider
   *
   * @return \Drupal\Core\Url|null
   *   Meeting URL, if found.
   */
  public function getDecisionMeetingLink(): ?Url {
    if (!$meetingId = $this->get('field_meeting_id')->value) {
      \Drupal::service('logger.channel.paatokset_ahjo_api')->warning('Decision @id has no meeting ID.', [
        '@id' => $this->id(),
      ]);

      return NULL;
    }

    $meetings = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'meeting',
        'field_meeting_id' => $meetingId,
      ]);

    if ($meeting = array_first($meetings)) {
      assert($meeting instanceof Meeting);
      return $meeting->getMinutesUrl();
    }

    return NULL;
  }

  /**
   * Get formatted section label for decision, including agenda point.
   *
   * @return string|null|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   Formatted section label, if possible to generate.
   */
  public function getFormattedDecisionSection(): string|null|TranslatableMarkup {
    if ($this->get('field_decision_section')->isEmpty()) {
      return NULL;
    }

    $section = $this->get('field_decision_section')->value;

    if ($this->get('field_decision_record')->isEmpty()) {
      return '§ ' . $section;
    }

    $data = json_decode($this->get('field_decision_record')->value, TRUE);

    if (!empty($data) && isset($data['AgendaPoint'])) {
      $section = $section . ' §';
      return new TranslatableMarkup('Case @point. / @section', [
        '@point' => $data['AgendaPoint'],
        '@section' => $section,
      ]);
    }

    return '§ ' . $section;
  }

  /**
   * Get decision attachments.
   *
   * @return array
   *   Array of links.
   */
  public function getAttachments(): array {
    $attachments = [];
    foreach ($this->get('field_decision_attachments') as $field) {
      /** @var \Drupal\Core\Field\FieldItemInterface $field */
      $data = json_decode($field->getString(), TRUE);

      $number = NULL;
      if (isset($data['AttachmentNumber'])) {
        $number = $data['AttachmentNumber'] . '. ';
      }

      $title = $data['Title'] ?? NULL;
      $publicity_class = $data['PublicityClass'] ?? NULL;
      $file_url = $data['FileURI'] ?? NULL;

      // If all relevant info is empty, do not display attachment.
      if (empty($publicity_class) && empty($title) && empty($file_url)) {
        $title = new TranslatableMarkup("There's an error with this attachment. We are resolving the issue as soon as possible.");
        $publicity_class = 'error';
      }
      // Override title if attachment is not public.
      elseif ($publicity_class !== 'Julkinen') {
        if (!empty($data['SecurityReasons'])) {
          $title = new TranslatableMarkup('Confidential: @reasons', [
            '@reasons' => implode(', ', $data['SecurityReasons']),
          ]);
        }
        else {
          $title = new TranslatableMarkup('Confidential');
        }
      }

      $attachments[] = [
        'number' => $number,
        'file_url' => $file_url,
        'title' => $title,
        'publicity_class' => $publicity_class,
      ];
    }

    $publicity_reason = \Drupal::config('paatokset_ahjo_api.default_texts')->get('non_public_attachments_text.value');
    if (!empty($attachments)) {
      return [
        'items' => $attachments,
        'publicity_reason' => ['#markup' => $publicity_reason],
      ];
    }

    return [];
  }

  /**
   * Get voting results as array for selected decision.
   *
   * @return array|null
   *   Array with voting results or NULL.
   */
  public function getVotingResults(): ?array {
    if ($this->get('field_voting_results')->isEmpty()) {
      return NULL;
    }

    $vote_results = [];
    $types = ['Ayes', 'Noes', 'Blank', 'Absent'];

    foreach ($this->get('field_voting_results') as $row) {
      /** @var \Drupal\Core\Field\FieldItemInterface $row */
      $grouped_by_party = [];
      $results = [];
      $json = json_decode($row->getString());
      foreach ($types as $type) {

        if (empty($json->{$type})) {
          continue;
        }

        // Set accordion for each vote type.
        $results[$type] = $json->{$type};

        if (empty($json->{$type}->Voters)) {
          continue;
        }

        // Collate votes by council group and type.
        foreach ($json->{$type}->Voters as $voter) {
          if (empty($voter->CouncilGroup)) {
            $voter->CouncilGroup = (string) new TranslatableMarkup('No council group');
          }

          if (!isset($grouped_by_party[$voter->CouncilGroup])) {
            $grouped_by_party[$voter->CouncilGroup] = [
              'Name' => $voter->CouncilGroup,
              'Ayes' => 0,
            ];
          }
          if (!isset($grouped_by_party[$voter->CouncilGroup][$type])) {
            $grouped_by_party[$voter->CouncilGroup][$type] = 1;
          }
          else {
            $grouped_by_party[$voter->CouncilGroup][$type]++;
          }
        }
      }

      usort($grouped_by_party, function ($a, $b) {
        return strcmp($a['Name'], $b['Name']);
      });

      usort($grouped_by_party, function ($a, $b) {
        return $b['Ayes'] - $a['Ayes'];
      });

      $vote_results[] = [
        'accordions' => $results,
        'by_party' => $grouped_by_party,
      ];
    }

    return $vote_results;
  }

  /**
   * Get active decision's PDF file URI from record of minutes field.
   *
   * @return string|null
   *   URL for PDF.
   */
  public function getDecisionPdf(): ?string {
    // Check for office holder and trustee decisions for minutes PDF URI first.
    if ($minutes_file_uri = $this->getMinutesPdf()) {
      return $minutes_file_uri;
    }

    if ($this->get('field_decision_record')->isEmpty()) {
      return NULL;
    }

    $data = json_decode($this->get('field_decision_record')->value, TRUE);
    if (!empty($data) && isset($data['FileURI'])) {
      return $data['FileURI'];
    }

    return NULL;
  }

  /**
   * Get active decisions Minutes PDF file URI.
   *
   * @return string|null
   *   URL for PDF.
   */
  private function getMinutesPdf(): ?string {
    // Check decision org type first.
    if (!in_array($this->get('field_organization_type')->value, OrganizationType::TRUSTEE_TYPES)) {
      return NULL;
    }

    if ($this->get('field_decision_minutes_pdf')->isEmpty()) {
      return NULL;
    }

    $data = json_decode($this->get('field_decision_minutes_pdf')->value, TRUE);
    if (!empty($data) && isset($data['FileURI'])) {
      return $data['FileURI'];
    }
    return NULL;
  }

  /**
   * Get related case.
   */
  public function getCase(): ?CaseBundle {
    if (isset($this->case)) {
      return $this->case;
    }

    $diaryNumber = $this->getDiaryNumber();

    if (!$diaryNumber) {
      return NULL;
    }

    $cases = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'case',
        'field_diary_number' => $diaryNumber,
      ]);

    if (empty($cases)) {
      return NULL;
    }

    $case = reset($cases);
    assert($case instanceof CaseBundle);

    return $this->case = $case;
  }

  /**
   * Get main heading either from case or decision node.
   *
   * @return string|null
   *   Main heading or NULL if neither case nor decision have been set.
   */
  public function getDecisionHeading(): ?string {
    if (!$this->get('field_decision_case_title')->isEmpty()) {
      return $this->get('field_decision_case_title')->value;
    }

    if (!$this->get('field_full_title')->isEmpty()) {
      return $this->get('field_full_title')->value;
    }

    return $this->label();
  }

  /**
   * Gets policymaker id.
   */
  public function getPolicymakerId(): ?string {
    return $this->get('field_policymaker_id')->value;
  }

  /**
   * Get policymaker.
   */
  public function getPolicymaker(string $langcode): ?Policymaker {
    $policymaker_id = $this->getPolicymakerId();
    if (!$policymaker_id) {
      return NULL;
    }

    $policymakers = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'policymaker',
        'field_policymaker_id' => $policymaker_id,
      ]);

    if (empty($policymakers)) {
      return NULL;
    }

    $policymaker = reset($policymakers);
    assert($policymaker instanceof Policymaker);

    if ($policymaker->hasTranslation($langcode)) {
      return $policymaker->getTranslation($langcode);
    }

    return $policymaker;
  }

  /**
   * Return TRUE if decision content should be hidden.
   */
  public function hideContent(): bool {
    return (bool) $this->get('field_hide_decision_content')->value;
  }

  /**
   * Parse decision content and motion data from HTML.
   *
   * @return array
   *   Render arrays.
   */
  public function parseContent(): array {
    if ($this->hideContent()) {
      $hidden_decisions_text = \Drupal::config('paatokset_ahjo_api.default_texts')->get('hidden_decisions_text.value');
      return [
        'message' => [
          '#prefix' => '<div class="issue__hidden-message">',
          '#suffix' => '</div>',
          '#markup' => $hidden_decisions_text,
        ],
      ];
    }

    $has_case_id = !$this->get('field_diary_number')->isEmpty();
    $content = $this->get('field_decision_content')->value;
    $motion = $this->get('field_decision_motion')->value;
    $history = $this->get('field_decision_history')->value;

    $content_dom = new \DOMDocument();
    if (!empty($content)) {
      @$content_dom->loadHTML($content);
    }
    $content_xpath = new \DOMXPath($content_dom);

    $motion_dom = new \DOMDocument();
    if (!empty($motion)) {
      @$motion_dom->loadHTML($motion);
    }
    $motion_xpath = new \DOMXPath($motion_dom);

    $history_dom = new \DOMDocument();
    if (!empty($history)) {
      @$history_dom->loadHTML($history);
    }
    $history_xpath = new \DOMXPath($history_dom);

    // If content is not set, use motion html instead.
    // Keep $content variable NULL so we can use that for checking later.
    if (empty($content)) {
      $content_xpath = $motion_xpath;
    }

    $output = [];
    $voting_results = $content_xpath->query("//*[contains(@class, 'aanestykset')]");
    if (!empty($voting_results) && $voting_results[0] instanceof \DOMNode) {
      $voting_link_paragraph = $content_dom->createElement('p');
      $voting_link_a = $content_dom->createElement('a', (string) new TranslatableMarkup('See table with voting results'));
      $voting_link_a->setAttribute('href', '#voting-results-accordion');
      $voting_link_a->setAttribute('id', 'open-voting-results');
      $voting_link_paragraph->appendChild($voting_link_a);
      $voting_results[0]->appendChild($voting_link_paragraph);
    }

    $main_content = NULL;
    // Main decision content sections.
    $content_sections = $content_xpath->query("//*[contains(@class, 'SisaltoSektio')]");

    foreach ($content_sections as $section) {
      $main_content .= $section->ownerDocument->saveHTML($section);
    }

    if ($main_content) {
      $output['main'] = [
        '#type' => 'processed_text',
        '#format' => 'decision_html',
        '#text' => $main_content,
      ];
    }

    // Motion content sections.
    // If decision content is empty, print motion content as main content.
    $motion_sections = $motion_xpath->query("//*[contains(@class, 'SisaltoSektio')]");
    if ($content) {
      $motion_accordions = $this->getMotionSections($motion_sections);
      foreach ($motion_accordions as $accordion) {
        $output['accordions'][] = $accordion;
      }
    }

    // To be decided in this meeting.
    $decided_in_this_meeting = $motion_xpath->query("//*[contains(@class, 'Muokkaustieto')]");
    $decided_in_this_meeting_content = NULL;
    if ($decided_in_this_meeting->length > 0) {
      $decided_in_this_meeting_content = $decided_in_this_meeting[0]->nodeValue;
    }
    if ($decided_in_this_meeting_content) {
      $output['decided_in_this_meeting'] = [
        '#markup' => $decided_in_this_meeting_content,
      ];
    }

    // More information.
    $more_info = $content_xpath->query("//*[contains(@class, 'LisatiedotOtsikko')]");
    $more_info_content = NULL;
    if ($more_info->length > 0) {
      $more_info_content = $this->getHtmlContentUntilBreakingElement($more_info);
      $more_info_content = str_replace(': 310', ': 09 310', $more_info_content);
    }

    if ($more_info_content) {
      // Replace all HTML tags with commas.
      $result = preg_replace('/<[^>]+>/', ',', $more_info_content);

      // Remove leading and trailing commas/spaces.
      $result = trim($result, ", \t\n\r\0\x0B");

      // Remove multiple subsequent commas and clean spacing.
      $result = preg_replace('/,+/', ',', $result);
      $result = preg_replace('/\s*,\s*/', ', ', $result);

      // Split into array.
      $parts = array_map('trim', explode(',', $result));

      // Make sure we have a phone number at index 2.
      if (isset($parts[2])) {
        // Remove all non-digit characters.
        $parts[2] = preg_replace('/\D+/', '', $parts[2]);
      }

      // Example of assigning named keys.
      $contact = [
        'name' => $parts[0] ?? NULL,
        'title' => ucfirst($parts[1]) ?? NULL,
        'phone' => $parts[2] ?? NULL,
        'email' => $parts[3] ?? NULL,
      ];

      $output['more_info'] = [
        'heading' => new TranslatableMarkup('Ask for more info'),
        'content' => [
          'name' => [
            '#plain_text' => $contact['name'],
          ],
          'title' => [
            '#plain_text' => $contact['title'],
          ],
          'phone' => [
            '#type' => 'link',
            '#title' => $contact['phone'],
            '#url' => strlen($contact['phone']) > 2 ? Url::fromUri('tel:' . $contact['phone']) : NULL,
          ],
          'email' => [
            '#type' => 'link',
            '#title' => $contact['email'],
            '#url' => $contact['email'] ? Url::fromUri('mailto:' . $contact['email']) : NULL,
          ],
        ],
      ];
    }

    // Signature information.
    $signature_info = $content_xpath->query("//*[contains(@class, 'SahkoisestiAllekirjoitettuTeksti')]");
    $signature_info_content = NULL;
    if ($signature_info->length > 0) {
      $signature_info_content = $this->getHtmlContentUntilBreakingElement($signature_info);
    }

    if ($signature_info_content && in_array($this->get('field_organization_type')->value, OrganizationType::TRUSTEE_TYPES)) {
      // Replace all HTML tags with #.
      $result = preg_replace('/<[^>]+>/', '#', $signature_info_content);

      // Remove leading and trailing # or whitespace.
      $result = trim($result, "# \t\n\r\0\x0B");

      // Collapse multiple # into one and normalize spacing around them.
      $result = preg_replace('/#+/', '#', $result);
      $result = preg_replace('/\s*#\s*/', ' # ', $result);

      // Split into array.
      $parts = array_map('trim', explode('#', $result));

      $contact = [
        'name' => $parts[0] ?? NULL,
        'title' => ucfirst($parts[1]) ?? NULL,
      ];
      $output['signature_info'] = [
        'heading' => new TranslatableMarkup('Decisionmaker'),
        'content' => [
          'name' => [
            '#plain_text' => $contact['name'],
          ],
          'title' => [
            '#plain_text' => $contact['title'],
          ],
        ],
      ];
    }

    // Presenter information.
    $presenter_info = $content_xpath->query("//*[contains(@class, 'EsittelijaTiedot')]");
    $presenter_content = NULL;
    if ($presenter_info->length > 0) {
      $presenter_content = $this->getHtmlContentUntilBreakingElement($presenter_info);
    }

    if ($presenter_content) {
      // Replace all HTML tags with #.
      $result = preg_replace('/<[^>]+>/', '#', $presenter_content);

      // Remove leading and trailing # or whitespace.
      $result = trim($result, "# \t\n\r\0\x0B");

      // Collapse multiple # into one and normalize spacing around them.
      $result = preg_replace('/#+/', '#', $result);
      $result = preg_replace('/\s*#\s*/', ' # ', $result);

      // Split into array.
      $parts = array_map('trim', explode('#', $result));
      $contact = [
        'title' => ucfirst($parts[0] ?? '') ?: NULL,
        'name' => ucfirst($parts[1] ?? '') ?: NULL,
      ];
      $output['presenter_info'] = [
        'heading' => new TranslatableMarkup('Presenter information'),
        'content' => [
          'title' => [
            '#plain_text' => $contact['title'],
          ],
          'name' => [
            '#plain_text' => $contact['name'],
          ],
        ],
      ];
    }

    // Decision history.
    $decision_history = $history_xpath->query("//*[contains(@class, 'paatoshistoria')]");
    $decision_history_content = NULL;
    if ($decision_history->length > 0) {
      $decision_history_content = $this->getDecisionHistoryHtmlContent($decision_history);
    }
    if ($decision_history_content) {
      $output['accordions'][] = [
        'heading' => new TranslatableMarkup('Decision history'),
        'content' => [
          '#type' => 'processed_text',
          '#format' => 'decision_html',
          '#text' => $decision_history_content,
        ],
      ];
    }

    // Add decision IssuedDate (not DecisionDate) to appeal process accordion.
    // Do not display for motions, only for decisions.
    $appeal_content = NULL;
    if ($has_case_id && $content && !$this->get('field_decision_date')->isEmpty()) {
      $decision_timestamp = strtotime($this->get('field_decision_date')->value);
      $decision_date = date('d.m.Y', $decision_timestamp);
      $appeal_content = '<p class="issue__decision-date">' . new TranslatableMarkup('This decision was published on <strong>@date</strong>', ['@date' => $decision_date]) . '</p>';
    }

    // Appeal information. Only display for decisions (if content is available).
    $appeal_info = $content_xpath->query("//*[contains(@class, 'MuutoksenhakuOtsikko')]");
    if ($content && $appeal_info) {
      $appeal_content .= $this->getHtmlContentUntilBreakingElement($appeal_info);
    }

    if ($appeal_content) {
      $output['accordions'][] = [
        'heading' => new TranslatableMarkup('Appeal process'),
        'content' => [
          '#type' => 'processed_text',
          '#format' => 'decision_html',
          '#text' => $appeal_content,
        ],
      ];
    }

    return $output;
  }

  /**
   * Split motions into sections.
   *
   * @param \DOMNodeList $list
   *   Motion content sections.
   *
   * @return array
   *   Array of sections.
   */
  private function getMotionSections(\DOMNodeList $list): array {
    $output = [];
    if ($list->length < 1) {
      return [];
    }

    foreach ($list as $node) {
      if (!$node instanceof \DOMElement) {
        continue;
      }

      $section = [
        'content' => [
          '#type' => 'processed_text',
          '#format' => 'full_html',
          '#text' => NULL,
        ],
      ];
      $heading_found = FALSE;
      foreach ($node->childNodes as $node) {
        if (!$heading_found && $node->nodeName === 'h3') {
          $section['heading'] = $node->nodeValue;
          $heading_found = TRUE;
          continue;
        }

        $section['content']['#text'] .= $node->ownerDocument->saveHtml($node);
      }

      $output[] = $section;
    }

    return $output;
  }

  /**
   * Get HTML content from first heading until next heading.
   *
   * @param \DOMNodeList $list
   *   Xpath query results.
   *
   * @return string|null
   *   HTML content as string, or NULL if content is empty.
   */
  private function getHtmlContentUntilBreakingElement(\DOMNodeList $list): ?string {
    $output = NULL;
    if ($list->length < 1) {
      return NULL;
    }

    $current_item = $list->item(0);
    while ($current_item->nextSibling instanceof \DOMNode) {
      // Iterate over to next sibling. This skips the first one.
      $current_item = $current_item->nextSibling;

      if (!$current_item instanceof \DOMElement) {
        continue;
      }

      // H3 with a class is considered a breaking element.
      if ($current_item->nodeName === 'h3' && !empty($current_item->getAttribute('class'))) {
        break;
      }
      // More information section should stop before the signatures.
      if ($current_item->getAttribute('class') === 'SahkoinenAllekirjoitusSektio') {
        break;
      }

      // Strip empty nodes. Ahjo HTML seems to contain a lot of <p></p> tags.
      if (empty($current_item->nodeValue) && $current_item->nodeName !== 'img') {
        continue;
      }

      $output .= $current_item->ownerDocument->saveHTML($current_item);
    }

    return $output;
  }

  /**
   * Get HTML content for decision history.
   *
   * @param \DOMNodeList $list
   *   Xpath query results.
   *
   * @return string|null
   *   HTML content as string, or NULL if content is empty.
   */
  private function getDecisionHistoryHtmlContent(\DOMNodeList $list): ?string {
    $output = NULL;

    if ($list->length < 1) {
      return NULL;
    }

    foreach ($list as $item) {
      if (!$item instanceof \DOMElement) {
        continue;
      }

      // Skip over any empty elements.
      if (empty($item->nodeValue)) {
        continue;
      }

      // Skip over H1 elements.
      if ($item->nodeName === 'h1') {
        continue;
      }

      // Skip over diary number field.
      if ($item->getAttribute('class') === 'DnroTmuoto') {
        continue;
      }

      if ($item->nodeName === 'h2') {
        $output .= '<h4 class="decision-history-title">' . $item->nodeValue . '</h4>';
      }
      elseif ($item->nodeName === 'h3') {
        $output .= '<h5 class="decision-history-title">' . $item->nodeValue . '</h5>';
      }
      elseif ($item->getAttribute('class') === 'SisaltoSektio' || $item->getAttribute('class') === 'paatoshistoria') {
        $output .= $this->getDecisionHistoryHtmlContent($item->childNodes);
      }
      else {
        $output .= $item->ownerDocument->saveHTML($item);
      }
    }

    return $output;
  }

}
