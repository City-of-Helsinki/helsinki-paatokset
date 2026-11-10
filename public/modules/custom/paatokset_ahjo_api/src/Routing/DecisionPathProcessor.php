<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Localized paths for untranslated cases and decisions.
 *
 * Most decisions and cases exist only in Finnish. They only have a Finnish
 * path alias, e.g. /asia/hel-2025-019540/29049ff0-901a-4e59-a450-48a5b7b8fbc8.
 * We still want to display the Finnish content with Swedish UI, but untranslated
 * pages gets URLs like /sv/node/123.
 *
 * This processor translates URLs for nodes, so the original Finnish content is
 * always available from our nicer URLs.
 *
 * @see \Drupal\path_alias\PathProcessor\AliasPathProcessor
 */
final readonly class DecisionPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * Case and decision path prefixes keyed by langcode.
   *
   * These must match the case and decision pathauto patterns.
   */
  private const array PREFIXES = [
    'fi' => 'asia',
    'sv' => 'arende',
    'en' => 'case',
  ];

  public function __construct(
    private AliasManagerInterface $aliasManager,
    private LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function processInbound($path, Request $request): string {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_URL)
      ->getId();

    // Only translate paths that use the prefix of the current language. Core
    // alias processor has already handled paths that have an alias in the
    // current language.
    if (!($rest = $this->getRest($path, $langcode))) {
      return $path;
    }

    foreach (self::PREFIXES as $other => $prefix) {
      if ($other === $langcode) {
        continue;
      }

      $alias = "/$prefix/$rest";
      $source = $this->aliasManager->getPathByAlias($alias, $other);

      if ($source !== $alias) {
        return $source;
      }
    }

    return $path;
  }

  /**
   * {@inheritDoc}
   *
   * @param string $path
   *   The path to process.
   * @param array<string, mixed> $options
   *   The options array.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The HttpRequest object.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   Bubbleable metadata.
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    // Core alias processor has already replaced paths that
    // have an alias in the requested language.
    if (!empty($options['alias']) || !preg_match('/^\/node\/\d+$/', $path)) {
      return $path;
    }

    $language = $options['language'] ?? NULL;
    $langcode = match (TRUE) {
      $language instanceof LanguageInterface => $language->getId(),
      is_string($language) => $language,
      default => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId(),
    };

    if (!isset(self::PREFIXES[$langcode])) {
      return $path;
    }

    foreach (array_keys(self::PREFIXES) as $other) {
      if ($other === $langcode) {
        continue;
      }

      $alias = $this->aliasManager->getAliasByPath($path, $other);

      if ($rest = $this->getRest($alias, $other)) {
        return '/' . self::PREFIXES[$langcode] . '/' . $rest;
      }
    }

    return $path;
  }

  /**
   * Gets the path without the language specific prefix.
   *
   * @return string|null
   *   Rest of the path, or NULL if the path does not have
   *   case or decision prefix for given language.
   */
  private function getRest(string $path, string $langcode): ?string {
    $prefix = self::PREFIXES[$langcode] ?? NULL;

    if ($prefix && str_starts_with($path, "/$prefix/") && strlen($path) > strlen($prefix) + 2) {
      return substr($path, strlen($prefix) + 2);
    }

    return NULL;
  }

}
