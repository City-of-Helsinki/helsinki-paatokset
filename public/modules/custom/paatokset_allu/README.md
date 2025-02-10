# Paatokset allu

__This integration is work in progress__

Integration to Urban Environment Divisions Allu system. Allu contains decisions documents related to public area usage in Helsinki.

Allu API documentation: https://allu.kaupunkiymparisto.fi/external/swagger-ui/index.html.

Relevant parts for paatokset is search endpoints for decision and approval documents. Paatokset runs a migration
([@todo UHF-10567](https://helsinkisolutionoffice.atlassian.net/browse/UHF-10567)) which imports metadata about
decision files from these search endpoints to Drupal entities. These entities are indexed to elasticsearch
([@todo UHF-10975](https://helsinkisolutionoffice.atlassian.net/browse/UHF-10975)), which
is to render frontend search component ([@todo UHF-10566](https://helsinkisolutionoffice.atlassian.net/browse/UHF-10566)).

The PDF files are not publicly accessible from Allu, since the api requires authentication, so the public links
proxy the files from Allu API.

## Configuration

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

Optionally, configure Allu base URL to use production environement.
```php
// local.settings.php
$config['paatokset_allu.settings']['base_url'] = 'https://allu.kaupunkiymparisto.fi';
```



