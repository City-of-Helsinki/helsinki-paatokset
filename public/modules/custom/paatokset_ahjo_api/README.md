# Paatokset Ahjo API

## Ahjo importer v2

Import API v2 is a re-write of the old codebase that uses Drupal nodes for all data. The new codes base uses custom Drupal entities. These custom entities use the Ahjo API ids as database primary keys, which makes it much simpler to query data. New entities are also trying to learn lessons from the old codebase and simplify language handling, URL structure, among other things.

**Import cases from Ahjo API to Drupal.**

```sh
# Import latest document (~1 week)
drush ahjo-api:import:case

# Import specific document
drush ahjo-api:import:case --idlist=hel-2020-000591,hel-2025-002349 --verbose
```

**Process queues items**

Datamigration supports lightweight read that enqueues ahjo documents for later retrieval. Queue also offers easy re-trying of failed items. In case of errors, items are pushed to retry-queue and from there to error queue.

```sh
# Queue latest cases (~1 week)
drush ahjo-api:import:case --queue

# Process queue
drush queue:run ahjo_api_aggregation_queue
```

## Troubleshooting

The drush command uses Drupal migrations. If the command fails for some reason, you might need to reset the migration manually.

```sh
drush migrate:reset-status ahjo_cases_v2
```
