# Datapumppu integration

Datapumppu API serves speaking turns for trustees. These speaking turns are listed on trustee pages.

The integration uses [Drupal migration](./migrations/datapumppu_statements.yml) to fetch speaking turns to [`Statement`](./src/Entity/Statement.php) entities. The migration is called in a cron job by [`run-datapumppu-integration.sh`](../../../../docker/openshift/crons/run-datapumppu-integrations.sh).

## Known issues

Datapumppu API uses trustee names as ids. This is a somewhat unreliable. There has been issues with Ahjo API having different spelling for trustee names that Datapumppu. For this reason, trustee entity type has `field_trustee_datapumppu_id`, which can be used to override the name from Ahjo in Datapumppu queries.

## Troubleshooting

Drush command `drush datapumppu:all-trustee-statements` can be used for advanced manipulation of the statement entities. Refer to `--help` for options.

