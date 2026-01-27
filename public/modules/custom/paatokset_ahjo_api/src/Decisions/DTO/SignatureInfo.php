<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions\DTO;

/**
 * Signature information for decision.
 */
final readonly class SignatureInfo {

  /**
   * List of signers keyed by SignerRole name.
   *
   * @var array<string, \Drupal\paatokset_ahjo_api\Decisions\DTO\Signer>
   */
  public array $signers;

  /**
   * Constructs a new SignatureInfo.
   *
   * @param array<string, \Drupal\paatokset_ahjo_api\Decisions\DTO\Signer> $signers
   *   List of signers keyed by SignerRole name.
   */
  public function __construct(array $signers) {
    $this->signers = $signers;
  }

  /**
   * Get a signer by role.
   */
  public function getSigner(SignerRole $role): ?Signer {
    return $this->signers[$role->name] ?? NULL;
  }

}
