<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Bundle class for decisions.
 */
class Decision extends Node {

  /**
   * Get diary (case) number.
   */
  public function getDiaryNumber(): string {
    return $this->get('field_diary_number')->getString();
  }

  /**
   * Get formatted section label for decision, including agenda point.
   *
   * @return string|null|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   Formatted section label, if possible to generate.
   */
  public function getFormattedDecisionSection(): mixed {
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
      $data = json_decode($field->value, TRUE);

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
    $not_formatted = $this->get('field_voting_results');
    $types = ['Ayes', 'Noes', 'Blank', 'Absent'];

    foreach ($not_formatted as $row) {
      $grouped_by_party = [];
      $results = [];
      $json = json_decode($row->value);
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

    if (!$this->get('field_decision_record')->isEmpty()) {
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
    if (!in_array($this->get('field_organization_type')->value, PolicymakerService::TRUSTEE_TYPES)) {
      return NULL;
    }

    if (!$this->get('field_decision_minutes_pdf')->isEmpty()) {
      return NULL;
    }

    $data = json_decode($this->get('field_decision_minutes_pdf')->value, TRUE);
    if (!empty($data) && isset($data['FileURI'])) {
      return $data['FileURI'];
    }
    return NULL;
  }

}
