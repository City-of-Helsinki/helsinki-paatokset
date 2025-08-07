<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoAdmin;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\paatokset_ahjo_api\Entity\AhjoEntityInterface;
use Drupal\paatokset_ahjo_api\Entity\AhjoUpdatableInterface;

/**
 * Ahjo admin controls.
 */
class AdminHooks {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Adds ahjo admin controls to form render array.
   */
  public function alterForm(array &$form, FormStateInterface $formState): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $formObject */
    $formObject = $formState->getFormObject();
    $entity = $formObject->getEntity();

    if ($entity instanceof AhjoEntityInterface && $render = $this->getAdminRenderArray($entity)) {
      $form['ahjo'] = $render;
    }
  }

  /**
   * Gets admin controls render array.
   *
   * @return array
   *   Drupal render array.
   */
  private function getAdminRenderArray(AhjoEntityInterface $entity): array {
    $form = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Ahjo tools'),
      '#group' => 'advanced',
      '#open' => TRUE,
      'actions' => [
        '#theme' => 'links',
        '#attributes' => ['class' => ['action-links']],
        '#links' => [],
      ],
    ];

    if (
      $this->currentUser->hasPermission('administer paatokset') &&
      $entity instanceof AhjoUpdatableInterface
    ) {
      $form['actions']['#links']['update'] = [
        'title' => new TranslatableMarkup('Update ahjo data'),
        'url' => Url::fromRoute('paatokset_ahjo_api.ahjo_update_form', [
          'node' => $entity->id(),
        ]),
        'attributes' => [
          'class' => [
            'button',
          ],
          'target' => '_blank',
        ],
      ];
    }

    if ($this->currentUser->hasPermission('access ahjo proxy')) {
      $form['actions']['#links']['view'] = [
        'title' => new TranslatableMarkup('View on ahjo proxy'),
        'url' => $entity->getProxyUrl(),
        'attributes' => [
          'target' => '_blank',
        ],
      ];

      try {
        $environment = $this->environmentResolver->getActiveEnvironment();
        if ($environment->getEnvironment() !== EnvironmentEnum::Prod) {
          $form['warning'] = [
            '#markup' => new TranslatableMarkup('Note: Ahjo proxy urls only work in the production environment.'),
            '#prefix' => '<p>',
            '#suffix' => '</p>',
          ];
        }
      }
      catch (\InvalidArgumentException $e) {
      }
    }

    if (empty($form['actions']['#links'])) {
      return [];
    }

    $cache = new CacheableMetadata();
    $cache->setCacheContexts(['user.permissions']);
    $cache->applyTo($form);

    return $form;
  }

}
