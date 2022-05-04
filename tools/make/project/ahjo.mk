PHONY += ahjo-migrations
ahjo-migrations: # Run Ahjo migrations from local data.
	$(call drush_on_${RUN_ON},ahjo-proxy:store-static-files)
	$(call drush_on_${RUN_ON},migrate-import ahjo_cases:latest)
	$(call drush_on_${RUN_ON},migrate-import ahjo_meetings:latest)
	$(call drush_on_${RUN_ON},migrate-import ahjo_decisions:latest)
	$(call drush_on_${RUN_ON},migrate-import ahjo_decisionmakers:all)
	$(call drush_on_${RUN_ON},migrate-import ahjo_decisionmakers:all_sv)
	$(call drush_on_${RUN_ON},migrate-import ahjo_trustees:council)
	$(call drush_on_${RUN_ON},ahjo-proxy:get-motions)
	$(call drush_on_${RUN_ON},ahjo-proxy:update-decisions)
