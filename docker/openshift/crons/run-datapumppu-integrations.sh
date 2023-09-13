#!/bin/bash

# Sleep 100 seconds.
sleep 100

while true
do
  echo "Running Datapumppu migration: $(date)"
  drush migrate:import datapumppu_statements -v --no-progress

  # Sleep for 24 hours.
  sleep 86400

  echo "Reset datapumppu migration"
  drush migrate-reset-status datapumppu_statements 2>/dev/null
done
