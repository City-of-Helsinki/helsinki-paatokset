#!/bin/bash

while true
do
  # Sleep for 1 hour.
  sleep 3600
  # Don't run aggregations between 01:00 and 07:00 UTC+3.
  # Ahjo might be offline for maintenance during the night.
  currenttime=$(date +%H:%M)
  if [[ "$currenttime" > "22:00" ]] || [[ "$currenttime" < "04:00" ]]; then
    sleep 3600
  fi
  if [ ${APP_ENV} = 'production' ]; then
    echo "Aggregating data for meetings: $(date)"
    drush ahjo-proxy:aggregate meetings --dataset=latest --queue -v
    echo "Aggregating data for cancelled meetings: $(date)"
    drush ahjo-proxy:aggregate meetings --dataset=cancelled --cancelledonly --queue -v
    echo "Aggregating data for decisions: $(date)"
    drush ahjo-proxy:aggregate decisions --dataset=latest --queue -v
    echo "Aggregating data for cases: $(date)"
    drush ahjo-proxy:aggregate cases --dataset=latest --queue -v
  fi
  echo "Aggregating council org data: $(date)"
  drush ahjo-proxy:get-council-positionsoftrust -v
  echo "Aggregating data for council members: $(date)"
  drush ahjo-proxy:get-trustees positionsoftrust_council.json -v
  echo "Aggregating latest decisionmaker changes: $(date)"
  drush ap:get decisionmakers --dataset=latest --filename=decisionmakers_latest.json -v
  drush ap:get decisionmakers --dataset=latest --langcode=sv --filename=decisionmakers_latest_sv.json -v
  if [ ${APP_ENV} = 'production' ]; then
    drush queue:run ahjo_api_aggregation_queue --time-limit=1800 -v
    drush queue:run ahjo_api_retry_queue --time-limit=1800 -v
  fi
  echo "Migrating data for council members: $(date)"
  drush migrate-reset-status ahjo_trustees:council
  drush migrate-import ahjo_trustees:council --update
  echo "Migrating data for decisionmakers: $(date)"
  drush migrate-reset-status ahjo_decisionmakers:latest
  drush migrate-reset-status ahjo_decisionmakers:latest_sv
  drush migrate-import ahjo_decisionmakers:latest --update
  drush migrate-import ahjo_decisionmakers:latest_sv --update
  echo "Checking for inactive decisionmakers: $(date)"
  drush ahjo-proxy:check-dm-status -v
  # Sleep for 23 hours.
  sleep 82800
done
