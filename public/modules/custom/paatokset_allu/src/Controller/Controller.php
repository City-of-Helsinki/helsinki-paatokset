<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\paatokset_allu\Entity\Document;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Allu controller.
 */
class Controller extends ControllerBase {

  public function __construct(private readonly Client $client) {
  }

  /**
   * Stream decision.
   *
   * @param \Drupal\paatokset_allu\Entity\Document $paatokset_allu_document
   *   Decision id.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function decision(Document $paatokset_allu_document): StreamedResponse {
    return $this->pdfResponse(
      fn () => $this->client->decisionDocument((string) $paatokset_allu_document->id()),
      "{$paatokset_allu_document->label()}.pdf",
    );
  }

  /**
   * Stream approval.
   *
   * @param \Drupal\paatokset_allu\Entity\Document $paatokset_allu_document
   *   Decision id.
   * @param string $type
   *   Approval type.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function approvalFromDecision(Document $paatokset_allu_document, string $type): StreamedResponse {
    $approvalType = ApprovalType::tryFrom($type);
    if (!$approvalType) {
      throw new NotFoundHttpException();
    }

    return $this->approvalResponse($paatokset_allu_document, $approvalType);
  }

  /**
   * Stream approval.
   *
   * @param \Drupal\paatokset_allu\Entity\Approval $paatokset_allu_approval
   *   Approval id.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function approval(Approval $paatokset_allu_approval): StreamedResponse {
    $document = $paatokset_allu_approval->getDocument();
    $approvalType = $paatokset_allu_approval->getApprovalType();
    if (!$document || !$approvalType) {
      throw new NotFoundHttpException();
    }

    return $this->approvalResponse($document, $approvalType);
  }

  /**
   * Build response for approval document.
   *
   * @param \Drupal\paatokset_allu\Entity\Document $document
   *   The decision the approval belongs to.
   * @param \Drupal\paatokset_allu\ApprovalType $approvalType
   *   Approval type.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  private function approvalResponse(Document $document, ApprovalType $approvalType): StreamedResponse {
    $suffix = strtolower($approvalType->value);

    return $this->pdfResponse(
      fn () => $this->client->approvalDocument((string) $document->id(), $approvalType),
      "{$document->label()}-$suffix.pdf",
    );
  }

  /**
   * Build streamed PDF response.
   *
   * @param callable(): \Psr\Http\Message\ResponseInterface $fetch
   *   Callback that fetches the document from Allu.
   * @param string $filename
   *   Filename for the downloaded document.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  private function pdfResponse(callable $fetch, string $filename): StreamedResponse {
    try {
      $upstream = $fetch();
    }
    catch (AlluException $e) {
      if ($e->getCode() === Response::HTTP_NOT_FOUND) {
        throw new NotFoundHttpException(previous: $e);
      }

      throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Failed to fetch document.', $e);
    }

    $headers = [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
    ];

    if ($upstream->hasHeader('Content-Length')) {
      $headers['Content-Length'] = $upstream->getHeaderLine('Content-Length');
    }

    $body = $upstream->getBody();

    return new StreamedResponse(static function () use ($body): void {
      while (!$body->eof()) {
        echo $body->read(8192);
        flush();
      }
      $body->close();
    }, headers: $headers);
  }

}
