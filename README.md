# Helsinki Päätökset Drupal site

Drupal Website for the Helsinki Päätökset project.

## Environments

Env | Branch | Drush alias | URL | Notes
--- | ------ | ----------- | --- | -----
dev | * | - | http://helsinki-paatokset.docker.so/ | Local development environment
production | tag based | - | https://paatokset.hel.fi | Production environment

## Requirements

You need to have these applications installed to operate on all environments:

- [Docker](https://github.com/druidfi/guidelines/blob/master/docs/docker.md)
- [Stonehenge](https://github.com/druidfi/stonehenge)
- For the new person: Your SSH public key needs to be added to servers

## Create and start the environment

More in-depth instructions for setting up this project and dealing with API content: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/6151897290/Lokaali+kehitysymp+rist

For the first time (new project):

``
$ make new
``

Stop project:

``
$ make stop
``

Stop project and remove app container:

``
$ make down
``


``

Start project, update all packages and sync db from test:

``
$ make fresh
``


To create a local SQL dump to save your site's state, run:

``
$ make drush-create-dump
``

After this, the `make fresh` command should use it instead.

## Configuration management

Export settings:

``
$ make drush-cex
``

Import settings:

``
$ make drush-cim
``

## Other useful commands
```
# Login to app container:
$ make shell

# Login with Drush
$ make drush-uli

# Check Drupal coding style
$ make lint-drupal

# Automatically fix Drupal coding style errors
$ make fix-drupal
```

## Development and coding best practices
Documented in more detail on the project's Confluence page: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/2682126339/Kehitysk+yt+nn+t (in finnish, requires access.)


## Instance specific features

Most of the content comes through migration and aggregation scripts. The Päätökset project also has multiple JS applications used for displaying content.

Critical functionality, API content lifecycle and flow and debugging instructions can be found in Confluence: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/8333819923/Kriittiset+toiminnallisuudet+ja+ongelmatilanteiden+selvitys

More in-depth documentation:
* Content module and custom components: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/2688516251/Drupal+sis+lt+malli
* Custom roles and permissions: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/6189809682/K+ytt+j+roolit+oikeudet+ja+k+ytt+j+tilit
* AHJO API integration: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/2688254056/AHJO+API
* Other integrations and search implementation: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/2681897044/Integraatiot
