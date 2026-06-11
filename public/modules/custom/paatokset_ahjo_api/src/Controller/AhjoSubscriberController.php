<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\paatokset_ahjo_api\Queue\SubscriberQueueEnum;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * AHJO subscriber controller.
 */
final class AhjoSubscriberController extends ControllerBase {

  public function __construct(
    private readonly QueueFactory $queueFactory,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * Handle subscriber callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param string $id
   *   Subscriber callback ID (decisions, meetings, etc.).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response for debugging.
   */
  public function callback(Request $request, string $id): JsonResponse {
    $content = json_decode($request->getContent());
    $created = (int) (new \DateTime('NOW'))->format('U');

    if (isset($content->updatetype)) {
      $update_type = (string) $content->updatetype;
    }
    else {
      $update_type = 'unknown';
    }

    // Push all items to default queue unless we have a specific queue for
    // this type of event.
    $queueEnum = SubscriberQueueEnum::tryFrom(strtolower(sprintf('%s.%s', $id, $update_type))) ?? SubscriberQueueEnum::Default;

    // @todo do not allow delete requests for now
    // until we fix ahjo authentication.
    $headers = [];
    foreach ($request->headers->all() as $key => $value) {
      $headers[] = $key . ': ' . implode(',', $value);
    }
    $this->logger->info('Ahjo headers: ' . implode(',', $headers));
    if ($queueEnum === SubscriberQueueEnum::DecisionRemoved) {
      throw new AccessDeniedHttpException();
    }

    $queue = $this->queueFactory->get($queueEnum->getQueueName());

    $data = [
      'id' => $id,
      'content' => $content,
      'created' => $created,
      'request' => $request->request->all(),
    ];

    $item_id = $queue->createItem($data);
    $data['item_id'] = $item_id;

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

    return new JsonResponse($data);
  }

}
