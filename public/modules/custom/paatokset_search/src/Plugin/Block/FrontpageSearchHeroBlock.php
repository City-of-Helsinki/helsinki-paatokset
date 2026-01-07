<?php

declare(strict_types=1);

namespace Drupal\paatokset_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_search\Form\DecisionSearchForm;

/**
 * Provides Decisions Search Hero Block.
 */
#[Block(
  id: 'frontpage_search_hero_block',
  admin_label: new TranslatableMarkup('Frontpage Search Hero Block'),
)]
final class FrontpageSearchHeroBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'frontpage_search_hero_block',
      '#hero_title' => $this->t("The City's decisions", [], ['context' => 'Decisions search']),
      '#hero_description' =>
      $this->t(
          "The City of Helsinki uses Finnish in its decision-making. You can browse the service in English, but the decision documents themselves are only available in Finnish. The Decisions service compiles all decision-making by the City of Helsinki in one place. You can find decisions made by the Helsinki City Council, City Board, committees, office holders and other authorities of Helsinki in Finnish.",
          [],
          ['context' => 'Decisions search']
      ),
      '#form' => $this->formBuilder->getForm(DecisionSearchForm::class),
    ];
  }

}
