# Paatokset allu

Integration to Urban Environment Divisions Allu system. Allu contains decisions documents related to public area usage in Helsinki.

Allu API documentation: https://allu.kaupunkiymparisto.fi/external/swagger-ui/index.html.

Relevant parts for paatokset is search endpoints for decision and approval documents. Paatokset runs a migration
([`drush migrate:import allu_*`](../../../../docker/openshift/crons/allu.sh)) which imports metadata about
decision files from these search endpoints to Drupal. These entities are indexed to elasticsearch, which
is to render frontend [search component](../../../themes/custom/hdbt_subtheme/src/js/react/apps/allu-decisions-search).

The PDF files are not imported to Drupal. The files are not publicly accessible from Allu (the api requires
authentication), so the [routes Drupal](./src/Entity/Routing/EntityRouteProvider.php) proxy the files from Allu API.

Available routes:
- `/allu/document/{id}/download`
- `/allu/document/{id}/approval/{type}/download`

New data is fetched to Drupal every hour.

## Migrations

Allu documents are imported with a Drupal migration. The migration imports documents that have been created within one week.
Older content can be imported with a Drush command. Under the hood, the Drush command just runs the same migration with
customizable timeframe.

Import most recent documents:
```shell
drush migrate:import allu_decisions
drush migrate:import allu_approvals
```

Import historical data:
```shell
drush allu:run-allu-migration allu_decisions --after="-5 year" --update
```
Check all available parameters with `--help` option.

## Manual updates

If there are mistakes in Allu data, synchronize Drupal with the API by running the migrations with `--update` flag manually. Note that Drupal does not store the PDF files, so any changed to the PDF files will be visible immediately.

### Required configuration

Using this integration locally requires allu credentials in your `local.settings.php` file. The value can be found from [Confluence](https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/8354005224/Tunnusten+salasanojen+ja+muiden+avainten+jakaminen).
```php
// local.settings.php
$config['helfi_api_base.api_accounts']['vault'][] = [
  'id' => 'allu',
  'plugin' => 'json',
  'data' => json_encode([
    'username' => 'hel_fi_paatokset_staging',
    'password' => '<< password from confluence >>',
  ]),
];
```

Configure Allu base URL to use production environment.
```php
// local.settings.php
$config['paatokset_allu.settings']['base_url'] = 'https://staging.allu.kaupunkiymparisto.fi';
# $config['paatokset_allu.settings']['base_url'] = 'https://allu.kaupunkiymparisto.fi';
```



