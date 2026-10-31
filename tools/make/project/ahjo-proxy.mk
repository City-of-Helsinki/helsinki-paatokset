# Ahjo proxy v3 (ahjo-proxy/).
#
# The service is defined in compose.override.yml behind the `ahjo-proxy`
# profile. Add `ahjo-proxy` to COMPOSE_PROFILES in .env.local to start
# it with `make up`.

AHJO_PROXY_COMPOSE := --profile ahjo-proxy

define ahjo_proxy_exec
	$(call docker_compose,$(AHJO_PROXY_COMPOSE) exec ahjo-proxy $(1))
endef

PHONY += ahjo-proxy-up
ahjo-proxy-up: ## Start Ahjo proxy
	$(call step,Start Ahjo proxy...\n)
	$(call docker_compose,$(AHJO_PROXY_COMPOSE) up --wait ahjo-proxy)
	$(call ahjo_proxy_exec,composer install --no-interaction)

PHONY += ahjo-proxy-stop
ahjo-proxy-stop: ## Stop Ahjo proxy
	$(call step,Stop Ahjo proxy...\n)
	$(call docker_compose,$(AHJO_PROXY_COMPOSE) stop ahjo-proxy)

PHONY += ahjo-proxy-shell
ahjo-proxy-shell: ## Login to Ahjo proxy container
	$(call ahjo_proxy_exec,bash)

PHONY += ahjo-proxy-test
ahjo-proxy-test: ## Run Ahjo proxy tests and static analysis
	$(call step,Run Ahjo proxy tests...\n)
	$(call ahjo_proxy_exec,vendor/bin/phpunit)
	$(call ahjo_proxy_exec,vendor/bin/phpstan analyse --no-progress --memory-limit=1G)
	$(call docker_compose,exec -w /app/ahjo-proxy app ../vendor/bin/phpcs)

PHONY += ahjo-proxy-refresh-token
ahjo-proxy-refresh-token: ## Refresh Ahjo proxy auth token
	$(call ahjo_proxy_exec,bin/console ahjo-proxy:refresh-token)
