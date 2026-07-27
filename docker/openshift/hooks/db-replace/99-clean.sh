#!/bin/sh

# Clear maintenance mode to allow access to the site while cleaning up
# the database.
drush state:set system.maintenance_mode 0 --input-format=integer

# Delete old decisions. Allow it to run for max 5 hours.
drush paatokset:decisions:delete --timeout=18000
