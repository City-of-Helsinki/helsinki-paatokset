#!/bin/bash

while true
do
  # Sleep for 1 hour.
  sleep 3600
  # echo "Aggregating data for meetings: $(date)"
  # drush ahjo-proxy:aggregate meetings --dataset=latest -v
  # echo "Aggregating data for cancelled meetings: $(date)"
  # drush ahjo-proxy:aggregate meetings --dataset=cancelled --cancelledonly -v
  echo "Aggregating data for cases: $(date)"
  drush ahjo-proxy:aggregate cases --dataset=latest -v
  echo "Aggregating data for decisions: $(date)"
  drush ahjo-proxy:aggregate decisions --dataset=latest -v
  echo "Aggregating council org data: $(date)"
  drush ahjo-proxy:get-council-positionsoftrust -v
  echo "Aggregating data for council members: $(date)"
  drush ahjo-proxy:get-trustees positionsoftrust_council.json -v
  echo "Aggregating latest decisionmaker changes: $(date)"
  drush ap:get decisionmakers --dataset=latest --filename=decisionmakers_latest.json -v
  drush ap:get decisionmakers --dataset=latest --langcode=sv --filename=decisionmakers_latest_sv.json -v
  # echo "Migrating data for meetings: $(date)"
  # drush migrate-reset-status ahjo_meetings:latest
  # drush migrate-import ahjo_meetings:latest --update
  # echo "Migrating data for cancelled meetings: $(date)"
  # drush migrate-reset-status ahjo_meetings:cancelled
  # drush migrate-import ahjo_meetings:cancelled --update
  echo "Migrating data for cases: $(date)"
  drush migrate-reset-status ahjo_cases:latest
  drush migrate-import ahjo_cases:latest --update
  echo "Migrating data for decisions: $(date)"
  drush migrate-reset-status ahjo_decisions:latest
  drush migrate-import ahjo_decisions:latest --update
  echo "Migrating data for council members: $(date)"
  drush migrate-reset-status ahjo_trustees:council
  drush migrate-import ahjo_trustees:council --update
  echo "Migrating data for decisionmakers: $(date)"
  drush migrate-reset-status ahjo_decisionmakers:latest
  drush migrate-reset-status ahjo_decisionmakers:latest_sv
  drush migrate-import ahjo_decisionmakers:latest --update
  drush migrate-import ahjo_decisionmakers:latest_sv --update
  # Sleep for 23 hours.
  sleep 82800
done
