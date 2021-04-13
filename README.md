# Helsinki Päätökset Drupal site

Drupal 9 Website for the Helsinki Päätökset project.

## Environments

Env | Branch | Drush alias | URL | Notes
--- | ------ | ----------- | --- | -----
development | * | - | http://helsinki-paatokset.docker.sh/ | Local development environment
~~production~~ | main | @main | TBD | Not implemented yet

## Requirements

You need to have these applications installed to operate on all environments:

- [Docker](https://github.com/druidfi/guidelines/blob/master/docs/docker.md)
- [Stonehenge](https://github.com/druidfi/stonehenge)
- For the new person: Your SSH public key needs to be added to servers

## Create and start the environment

For the first time (new project):

``
$ make new
``

Stop project:

``
$ make stop
``

Start project, rebuild and update configuration:

``
$ make up; make build; make post-install
``

Install fresh Drupal site from existing configuration:

``
$ make build; make drush-si; make post-install
``

Start project, update all packages and sync db from production:

``
$ make fresh
``

**Note:** Will not work at this point, since the production environment has not been set up.

## Update Drupal and composer modules

Update all modules and composer packages:

``
$ make composer-update
``

Update only Drupal core:

``
$ make drupal-update
``

**Note:** After updates, clear caches, run database updates and export possibly changed configuration:

``
$ make drush-cr; make drush-updb; make drush-cex
``

Update Composer.lock if outdated (after merges, etc):

```
# Login into app container first:
$ make shell

# Update lock file:
$ composer update --lock
```

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
```
