#!/bin/bash

while true
do
  # Sleep for 1 hour.
  sleep 3600
  echo "Aggregating data for meetings: $(date)"
  drush ahjo-proxy:aggregate meetings --dataset=latest -v
  echo "Aggregating data for cases: $(date)"
  drush ahjo-proxy:aggregate cases --dataset=latest -v
  echo "Aggregating data for decisions: $(date)"
  drush ahjo-proxy:aggregate decisions --dataset=latest -v
  echo "Aggregating council org data: $(date)"
  drush ahjo-proxy:get-council-positionsoftrust -v
  echo "Aggregating data for council members: $(date)"
  drush ahjo-proxy:get-trustees positionsoftrust_council.json -v
  echo "Migrating data for meetings: $(date)"
  drush migrate-reset-status ahjo_meetings:latest
  drush migrate-import ahjo_meetings:latest --update
  echo "Migrating data for cases: $(date)"
  drush migrate-reset-status ahjo_cases:latest
  drush migrate-import ahjo_cases:latest --update
  echo "Migrating data for decisions: $(date)"
  drush migrate-reset-status ahjo_decisions:latest
  drush migrate-import ahjo_decisions:latest --update
  echo "Migrating data for council members: $(date)"
  drush migrate-reset-status ahjo_trustees:council
  drush migrate-import ahjo_trustees:council --update
  # Sleep for 23 hours.
  sleep 82800
done
