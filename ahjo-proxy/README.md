# Ahjo proxy v3

Standalone Symfony app that proxies allowlisted `GET` requests to the Ahjo API,
which is behind a firewall. Replaces the Drupal based proxy v2
(`paatokset_ahjo_api/src/AhjoProxy`). Paths and query strings are forwarded
as-is, and Ahjo responses are returned as-is.

## Endpoints

- `GET /cases`, `/cases/{id}`, `/agents/positionoftrust[/{id}]`, `/agents/decisionmakers`,
  `/organization[/{id}]`, `/organization/decisionmakingorganizations[/{id}]`,
  `/records/{id}`, `/decisions[/{id}]`, `/meetings[/{id}]`,
  `/meetings/{id}/agendaitems/{id}`: proxied to Ahjo. Require `api-key` header.
- `GET /openid`: Ahjo OpenID connector status and the link that starts the authentication flow.
- `GET /openid/callback`: OpenID callback. Must be registered as a redirect URI in Ahjo.
- `GET /healthz`: health check.

## Ahjo auth token

The proxy has its own Ahjo OpenID refresh token chain, separate from Drupal.
Refresh tokens are single-use, so the token is stored in `TOKEN_STORAGE_DIR`,
which must be a persistent volume. Shared Redis is not used, because Drupal
flushes Redis on every deployment.

1. Open `/openid` and complete the authentication flow with Ahjo credentials.
2. Run `bin/console ahjo-proxy:refresh-token` periodically (e.g. hourly
   OpenShift CronJob, mounting the same volume) to keep the token fresh.

## Configuration

See [.env](.env). Secrets (`APP_SECRET`, `AHJO_PROXY_API_KEY`,
`AHJO_OPENID_SECRET`) are set with environment variables.

## Development

The `ahjo-proxy` service is defined in the project's `compose.override.yml`
behind the `ahjo-proxy` profile, and make commands are in
`tools/make/project/ahjo-proxy.mk` (`compose.yaml`, `Makefile` and
`tools/make/*.mk` are overwritten by automatic updates).

```sh
make ahjo-proxy-up             # start (and composer install)
make ahjo-proxy-test           # phpunit, phpstan, phpcs
make ahjo-proxy-shell
make ahjo-proxy-refresh-token
make ahjo-proxy-stop
```

To always start it with `make up`, add `COMPOSE_PROFILES=search,ahjo-proxy`
to the project's `.env.local`.

It is available at
https://ahjo-proxy-helsinki-paatokset.docker.so (add it to `/etc/hosts` like the other
`*.docker.so` hosts), and from the Drupal container at `http://ahjo-proxy:8080`.

Put local secrets (`AHJO_PROXY_API_KEY`, `AHJO_OPENID_SECRET`, ...) in `ahjo-proxy/.env.local`.

Without docker compose:

```sh
composer install
vendor/bin/phpunit
vendor/bin/phpstan
../vendor/bin/phpcs  # uses Drupal coding standards from the main project
```

Docker image: `docker build -f docker/openshift/ahjo-proxy/Dockerfile .` (from repository root).
