<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxyOpenIdClient;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxySessionExpiredException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\StatusMessageTrait;

/**
 * Starts the Ahjo proxy (v3) OpenID flow.
 */
final class AhjoProxyAuthForm extends FormBase {

  use StatusMessageTrait;

  public function __construct(
    protected readonly AhjoProxyOpenIdClient $proxyClient,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_ahjo_api_proxy_auth';
  }

  /**
   * Builds the form.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->proxyClient->hasSession()) {
      $form['status'] = $this->statusMessage('warning', $this->t('Managing the Ahjo proxy token requires a Helsinki AD login. <a href=":url">Log out</a> and log back in using AD account.', [
        ':url' => $this->getLogoutUrl(),
      ]));
      return $form;
    }

    $form['actions'] = ['#type' => 'actions'];

    // AD tokens expire sooner than the Drupal session.
    if (!$this->proxyClient->hasValidSession()) {
      $form['status'] = $this->statusMessage('warning', $this->t('Your Helsinki AD login has expired. Log in again to manage the Ahjo proxy token.'));
      $form['actions']['reauthenticate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Log in again'),
        '#submit' => ['::reauthenticate'],
      ];
      return $form;
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Ahjo proxy authentication flow'),
    ];

    return $form;
  }

  /**
   * Starts the OpenID flow.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $form_state->setResponse($this->proxyClient->startAuthFlow());
    }
    catch (AhjoProxySessionExpiredException) {
      // The login expired after the form was shown. The reloaded form offers
      // to log in again.
      $this->messenger()->addError($this->t('Your Helsinki AD login has expired. Log in again, then try again.'));
      return;
    }
    catch (AhjoOpenIdException $e) {
      Error::logException($this->logger('paatokset_ahjo_api'), $e);
      $this->messenger()->addError($this->getErrorMessage($e));
      return;
    }
  }

  /**
   * Logs the user in to Helsinki AD again, then reloads the page.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function reauthenticate(array &$form, FormStateInterface $form_state): void {
    try {
      $form_state->setResponse($this->proxyClient->reauthenticate());
    }
    catch (AhjoOpenIdException $e) {
      Error::logException($this->logger('paatokset_ahjo_api'), $e);
      $this->messenger()->addError($this->t('Could not log in to Helsinki AD again. <a href=":url">Log out</a> and log back in using the Helsinki AD button.', [
        ':url' => $this->getLogoutUrl(),
      ]));
    }
  }

  /**
   * Gets a user facing message for Ahjo proxy errors.
   */
  private function getErrorMessage(AhjoOpenIdException $e): TranslatableMarkup {
    return match ($e->getCode()) {
      // The proxy rejected a token for reasons logging in again did
      // not fix, such as the user not being in an allowed AD group.
      401, 403 => $this->t('Ahjo proxy did not accept your AD login. Your account does not have permission to manage the Ahjo proxy token.', [
        ':url' => $this->getLogoutUrl(),
      ]),
      default => $this->t('Ahjo proxy is unavailable. Try again later.'),
    };
  }

  /**
   * Gets the logout URL.
   *
   * The routes using this are not cached, so the CSRF token can be resolved
   * right away.
   */
  private function getLogoutUrl(): string {
    return Url::fromRoute('user.logout')->toString();
  }

}
