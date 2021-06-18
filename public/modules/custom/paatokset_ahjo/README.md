# Drupal Päätökset Ahjo integration

Integrates [OpenAhjo](https://dev.hel.fi/apis/openahjo#documentation) with Drupal, focused on to meet the needs of Helsinki Päätökset site

## Requirements

- PHP 7.4 or higher

## Usage

Available migrations:

- `paatokset_agenda_items`
- `paatokset_issues`
- `paatokset_meetings`
- `paatokset_meeting_documents`
- `paatokset_organizations`
- `paatokset_policymakers`


### Running migrations

Running a single migration:

`drush migrate:import {migration_id}` Add `--update` parameter to update existing items.

Reverting a migration:

`drush migrate:rollback {migration_id}`.

Migration failed and the migration process is stuck at importing:

`drush migrate:reset-status {migration_id}`.

### Speed up migrations

Set `PARTIAL_MIGRATE=1` env variable to only migrate changed items. *NOTE:* running a partial migrate will skip
all garbage collection tasks (such as cleaning removed remote entities), so you should periodically run full migrations as well.

