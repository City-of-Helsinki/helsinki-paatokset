# Helsinki Päätökset Drupal site

Drupal 9 Website for the Helsinki Päätökset project.

## Environments

Env | Branch | Drush alias | URL | Notes
--- | ------ | ----------- | --- | -----
development | * | - | http://helsinki-paatokset.docker.so/ | Local development environment
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

Stop project and remove app container:

``
$ make down
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

To create a local SQL dump to save your site's state, run:

``
$ make drush-create-dump
``

After this, the `make fresh` command should use it instead.

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

# Check Drupal coding style
$ make lint-drupal

# Automatically fix Drupal coding style errors
$ make fix-drupal
```

## Development best practices
Documented in more detail on the project's Confluence page: https://helsinkisolutionoffice.atlassian.net/wiki/spaces/PP/pages/2682126339/Kehitysk+yt+nn+t (in finnish, requires access.)

### Coding standards
Follow Drupal's coding standards: https://www.drupal.org/docs/develop/standards

City of Helsinki's coding standars and best practices: https://dev.hel.fi/

Check for coding style violantions by running `$ make lint-drupal`

### Commit messages
Commit message format: "Update development documentation with Git instructions."

The ticket ID is automatically added to the message based on the branch name.

The description should be brief and complete the sentence: "If applied, this commit will..".

Additional information can be added in a multiline message, where the first line includes the ticket ID and description.
### Gitflow workflow
The Gitflow workflow is followed, with the following conventions:

**Main branch**: `develop`. All feature branches are created from `develop` and merged back with pull requests. All new code must be added with pull requests, not committed directly.

**Production branch:** `main`. Code running in production. Code is merged to `main` with release and hotfix branches.

**Feature branches**: For example, `PP-123-add-search-functionality`, Always created from and merged back to `develop` with pull requests after code review and testing. Should contain the Jira ticket ID or `PP-0` if a ticket doesn't exist.

**Release branches**: Code for future and currently developed releases. Should include the version number, for example: `1.1.0-release`

**Hotfix branches**: Branches for small fixes to production code. Should include the ticket number and the word hotfix, for example: `PP-124-hotfix-drupal-updates`. Remember to also merge these back to `develop`.

### Pull requests
The pull request should contain:
* A link to relevant information (Jira ticket, documentation, etc)
* Description of what it will do if merged
* Steps to get the branch running locally
* Importing / creating test content
* Detailed steps on how to test the pull request
* Specific things to pay attention to when reading the code

Additional steps for frontend related PRs:
* A link to the designs / screenshots of what the functionality should look like (if applicable, mainly for frontend tickets)

Additional steps for bugfix PRs:
* Detailed steps on how to reproduce issue before installing the fix

### Reviewing pull requests
* Assign yourself to the PR before you do anything.
* Check possible issues with code quality checks and automated tests
* Follow the steps for reproducing and testing the issue. If anything is unclear, ask for clarifications in the comments
* The code should have passed basic code quality checks and linters already, so focus on code quality issues that can't be automated. Pay attention to maintainability, readability and extendability
* Ideally, code should be self documenting, but always ask for more documentation and comments in the code if something is unclear. Comments should answer WHY something is done the way it is.
* Always review exported configuration, if included in the change set:
  * Does this configuration include everything that it should?
  * Does this configuration include something that it should not?
    * Exported configuration not relevant to the feature
    * Exported local values (enabled devel or kint, etc)
    * Sensitive information (passwords, api keys and secrets)
* Are the configuration splits handled correctly?
