# Päätökset test content

This module holds the basic site structure and test content for Päätökset local installations.

## Dependencies
- [Default content](https://www.drupal.org/project/default_content)

## How to import the test content

The content is imported when the module is enabled.

The module can be enabled from admin UI (/admin/modules) or by running the following drush command.

```
drush en -y paatokset_test_content
```

When the module is already enabled and the content should be re-imported, it can be done with following drush command.

```
drush dcim paatokset_test_content
```

Make sure not to export `core.extension.yml` config with this module enabled!

## Export test content

Modify the content normally and then run the following command:

```
drush dcem paatokset_test_content
```

## Create new test content

Create the content normally, then run:

```
drush dcer [entity type] [id] --folder=/app/public/modules/custom/paatokset_test_content/content
```
