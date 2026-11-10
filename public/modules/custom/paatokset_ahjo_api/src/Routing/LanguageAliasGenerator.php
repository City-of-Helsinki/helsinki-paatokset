<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\path_alias\PathAliasInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\AliasUniquifierInterface;
use Drupal\pathauto\PathautoPatternInterface;
use Drupal\pathauto\PathautoState;

/**
 * Generates path aliases for decisions and cases in all UI languages.
 *
 * Most decisions and cases exist only in Finnish, but Päätökset still wants
 * to display the untranslated decisions in Swedish or English UI pages. Drupal
 * looks up path aliases only with the current language (and language-neutral).
 * This service creates aliases for the languages that the node does not exist
 * in, so that entity.node.canonical can serve the content with a localized URL,
 * e.g. /sv/arende/hel-[case-id]/[decision-id].
 *
 * Swedish decisions are separate nodes (linked with field_unique_id). They
 * have different native ids, so the generated aliases never collide with them.
 * @todo make sure that we redirect correctly if the translation exists.
 * @todo we really should move translations to proper translations!
 *
 * Pathauto keeps managing the alias in the node's own language.
 */
final class LanguageAliasGenerator {

  /**
   * Pathauto pattern ids keyed by bundle and langcode.
   */
  private const array PATTERNS = [
    'decision' => [
      'fi' => 'decision_pattern',
      'sv' => 'decision_pattern_sv',
      'en' => 'decision_pattern_en',
    ],
    'case' => [
      'fi' => 'case_pattern',
      'sv' => 'case_pattern_sv',
      'en' => 'case_pattern_en',
    ],
  ];

  /**
   * Loaded patterns.
   *
   * @var array<string, \Drupal\pathauto\PathautoPatternInterface|null>
   */
  private array $patterns = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly Token $token,
    private readonly AliasCleanerInterface $aliasCleaner,
    private readonly AliasUniquifierInterface $aliasUniquifier,
  ) {
  }

  /**
   * Checks if aliases should be generated for given node.
   */
  public function applies(NodeInterface $node): bool {
    return ($node instanceof Decision || $node instanceof CaseBundle) && !$node->isNew();
  }

  /**
   * Generate aliases for languages that the node does not exist in.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Decision or case node.
   *
   * @return int
   *   Number of created or updated aliases.
   */
  public function generate(NodeInterface $node): int {
    if (!$this->applies($node)) {
      return 0;
    }

    // Respect manually set aliases.
    if ($node->hasField('path') && (int) $node->get('path')->pathauto === PathautoState::SKIP) {
      return 0;
    }

    $source = '/' . $node->toUrl()->getInternalPath();
    $nodeLangcode = $node->language()->getId();
    $changed = 0;

    foreach (array_keys(self::PATTERNS[$node->bundle()]) as $langcode) {
      if ($langcode === $nodeLangcode || !$this->languageManager->getLanguage($langcode)) {
        continue;
      }

      if (!$pattern = $this->getPattern($node->bundle(), $langcode)) {
        continue;
      }

      $alias = $this->buildAlias($pattern, $node, $langcode);
      if (!$alias || $alias === $source) {
        continue;
      }

      if ($this->saveAlias($source, $alias, $langcode)) {
        $changed++;
      }
    }

    return $changed;
  }

  /**
   * Build alias from pattern.
   *
   * Mirrors \Drupal\pathauto\PathautoGenerator::createEntityAlias().
   */
  private function buildAlias(PathautoPatternInterface $pattern, NodeInterface $node, string $langcode): ?string {
    $alias = $this->token->replace($pattern->getPattern(), ['node' => $node], [
      'clear' => TRUE,
      'callback' => [$this->aliasCleaner, 'cleanTokenValues'],
      'langcode' => $langcode,
      'pathauto' => TRUE,
    ], new BubbleableMetadata());

    // Check if the token replacement has not actually replaced any values.
    $pattern_tokens_removed = preg_replace('/\[[^\s\]:]*:[^\s\]]*\]/', '', $pattern->getPattern());
    if ($alias === $pattern_tokens_removed) {
      return NULL;
    }

    $alias = $this->aliasCleaner->cleanAlias($alias);

    return $alias ?: NULL;
  }

  /**
   * Create or update the alias of the source path in the given language.
   *
   * @return bool
   *   TRUE if alias was created or updated.
   */
  private function saveAlias(string $source, string $alias, string $langcode): bool {
    $storage = $this->entityTypeManager->getStorage('path_alias');

    /** @var \Drupal\path_alias\PathAliasInterface[] $existing */
    $existing = $storage->loadByProperties([
      'path' => $source,
      'langcode' => $langcode,
    ]);

    $pathAlias = array_shift($existing);

    // Remove possible duplicates.
    if ($existing) {
      $storage->delete($existing);
    }

    if ($pathAlias instanceof PathAliasInterface && $pathAlias->getAlias() === $alias) {
      return FALSE;
    }

    $this->aliasUniquifier->uniquify($alias, $source, $langcode);

    if ($pathAlias instanceof PathAliasInterface) {
      if ($pathAlias->getAlias() === $alias) {
        return FALSE;
      }

      $pathAlias->setAlias($alias)->save();
      return TRUE;
    }

    $storage->create([
      'path' => $source,
      'alias' => $alias,
      'langcode' => $langcode,
    ])->save();

    return TRUE;
  }

  /**
   * Load pathauto pattern.
   */
  private function getPattern(string $bundle, string $langcode): ?PathautoPatternInterface {
    $id = self::PATTERNS[$bundle][$langcode] ?? NULL;

    if (!$id) {
      return NULL;
    }

    if (!array_key_exists($id, $this->patterns)) {
      $pattern = $this->entityTypeManager
        ->getStorage('pathauto_pattern')
        ->load($id);

      $this->patterns[$id] = $pattern instanceof PathautoPatternInterface ? $pattern : NULL;
    }

    return $this->patterns[$id];
  }

}
