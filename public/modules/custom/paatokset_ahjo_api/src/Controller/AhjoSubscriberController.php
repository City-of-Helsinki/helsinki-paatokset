<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AHJO subscriber controller.
 *
 * @package Drupal\paatokset_ahjo_api\Controller
 */
class AhjoSubscriberController extends ControllerBase {

  private const QUEUE_NAME = 'ahjo_api_subscriber_queue';

  /**
   * Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected $ahjoProxy;

  /**
   * Constructor.
   */
  public function __construct(QueueFactory $queue_factory, LoggerChannelFactoryInterface $logger_factory, AhjoProxy $ahjo_proxy) {
    $this->queueFactory = $queue_factory;
    $this->logger = $logger_factory->get('ahjo_api_subscriber');
    $this->ahjoProxy = $ahjo_proxy;
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('logger.factory'),
      $container->get('paatokset_ahjo_proxy')
    );
  }

  /**
   * Handle subscriber callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Subscriber callback ID (decisions, meetings, etc).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response for debugging.
   */
  public function callback(Request $request, string $id): JsonResponse {
    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    $content = json_decode($request->getContent());
    $created = (int) (new \DateTime('NOW'))->format('U');

    $data = [
      'id' => $id,
      'content' => $content,
      'created' => $created,
      'request' => $request->request->all(),
    ];

    $item_id = $queue->createItem($data);
    $data['item_id'] = $item_id;

    if (isset($content->updatetype)) {
      $update_type = (string) $content->updatetype;
    }
    else {
      $update_type = 'unknown';
    }
    if (isset($content->id)) {
      $entity_id = (string) $content->id;
    }
    else {
      $entity_id = 'unknown';
    }

    if ($item_id) {
      $this->logger->info('Added item to @id queue: @entity_id (@update_type) on @created.', [
        '@id' => $id,
        '@entity_id' => $entity_id,
        '@update_type' => $update_type,
        '@created' => $created,
      ]);
    }

    $this->ahjoProxy->invalidateCacheForProxy($id, $entity_id);
    if ($id === 'meetings') {
      $this->ahjoProxy->invalidateAgendaItemsCache($entity_id);
    }

    return new JsonResponse($data);
  }

  /**
   * List subscriber queue contents.
   *
   * @param string $id
   *   Subsciber callback ID to filter (decisions, meetings, etc).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Queue contents.
   */
  public function listQueue(string $id): JsonResponse {
    $data = [];
    $items = [];

    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    while ($item = $queue->claimItem()) {
      if ($id === 'all' || $item->data['id'] === $id) {
        $data[] = $item;
      }

      $items[] = $item;
    }

    // Release claimed items.
    foreach ($items as $item) {
      $queue->releaseItem(($item));
    }

    return new JsonResponse($data);
  }

}
