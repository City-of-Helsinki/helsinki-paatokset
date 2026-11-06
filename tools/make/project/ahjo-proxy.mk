# The service is defined in compose.override.yml and starts with `make up`.

define ahjo_proxy_exec
	$(call docker_compose,exec ahjo-proxy $(1))
endef

# Install proxy dependencies together with Drupal's (make fresh / make new).
composer-install: ahjo-proxy-composer-install

PHONY += ahjo-proxy-composer-install
ahjo-proxy-composer-install: ## Install Ahjo proxy Composer packages
	$(call step,Do Ahjo proxy Composer install...\n)
	$(call ahjo_proxy_exec,composer install --no-interaction)

PHONY += ahjo-proxy-up
ahjo-proxy-up: ## Start Ahjo proxy
	$(call step,Start Ahjo proxy...\n)
	$(call docker_compose,up --wait ahjo-proxy)
	$(call ahjo_proxy_exec,composer install --no-interaction)

PHONY += ahjo-proxy-stop
ahjo-proxy-stop: ## Stop Ahjo proxy
	$(call step,Stop Ahjo proxy...\n)
	$(call docker_compose,stop ahjo-proxy)

PHONY += ahjo-proxy-shell
ahjo-proxy-shell: ## Login to Ahjo proxy container
	$(call ahjo_proxy_exec,bash)

PHONY += ahjo-proxy-refresh-token
ahjo-proxy-refresh-token: ## Refresh Ahjo proxy auth token
	$(call ahjo_proxy_exec,bin/console ahjo-proxy:refresh-token)
