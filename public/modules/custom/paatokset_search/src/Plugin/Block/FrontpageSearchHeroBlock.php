<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_search\Form\DecisionSearchForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Decisions Search Hero Block.
 */
#[Block(
  id: 'frontpage_search_hero_block',
  admin_label: new TranslatableMarkup('Frontpage Search Hero Block'),
)]
final class FrontpageSearchHeroBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private FormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    $instance = new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );

    $instance->formBuilder = $container->get(FormBuilderInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->buildHero(
      $this->t("The City's decisions", [], ['context' => 'Decisions search']),
      $this->t(
        "The City of Helsinki uses Finnish in its decision-making. You can browse the service in English, but the decision documents themselves are only available in Finnish. The Decisions service compiles all decision-making by the City of Helsinki in one place. You can find decisions made by the Helsinki City Council, City Board, committees, office holders and other authorities of Helsinki in Finnish.",
        [],
        ['context' => 'Decisions search']
      ),
      $this->formBuilder->getForm(DecisionSearchForm::class),
    );
  }

  /**
   * Builds the hero block.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   Hero title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   Hero description.
   * @param array $form
   *   Search form render array.
   *
   * @return array
   *   Render array.
   */
  private function buildHero(
    TranslatableMarkup $title,
    TranslatableMarkup $description,
    array $form,
  ): array {
    return [
      '#theme' => 'frontpage_search_hero_block',
      '#hero_title' => $title,
      '#hero_description' => $description,
      '#form' => $form,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
