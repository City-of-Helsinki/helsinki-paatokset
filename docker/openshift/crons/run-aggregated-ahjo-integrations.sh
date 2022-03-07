#!/bin/bash

while true
do
  echo "Aggregating data for meetings: $(date)"
  drush ahjo-proxy:aggregate meetings --dataset=latest
  echo "Aggregating data for cases: $(date)"
  drush ahjo-proxy:aggregate cases --dataset=latest
  echo "Migrating data for cases: $(date)"
  echo "Aggregating data for decisions: $(date)"
  drush ahjo-proxy:aggregate decisions --dataset=latest
  echo "Aggregating data for council members: $(date)"
  drush ahjo-proxy:get-council-positionsoftrust
  echo "Migrating data for meetings: $(date)"
  drush migrate-import ahjo_meetings:latest --update
  echo "Migrating data for cases: $(date)"
  drush migrate-import ahjo_cases:latest --update
  echo "Migrating data for decisions: $(date)"
  drush migrate-import ahjo_decisions:latest --update
  echo "Migrating data for council members: $(date)"
  drush migrate-import ahjo_trustees:council --update
  echo "Generating motions from meeting data: $(date)"
  drush ahjo-proxy:get-motions
  echo "Updating decision and motion data: $(date)"
  drush ahjo-proxy:update-decisions
  # Sleep for 24 hours.
  sleep 86400
done
