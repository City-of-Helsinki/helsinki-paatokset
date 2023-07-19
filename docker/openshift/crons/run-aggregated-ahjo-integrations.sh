#!/bin/bash

while true
do
  # Sleep for 1 hour.
  sleep 3600

  # Don't run aggregations between 01:00 and 07:00 UTC+3.
  # Ahjo might be offline for maintenance during the night.
  # This process should take around 24 hours but the actual time varies.
  # This causes the start time to eventually drift into the maintenance window.
  currenttime=$(date +%H:%M)
  if [[ "$currenttime" > "22:00" ]] || [[ "$currenttime" < "04:00" ]]; then
    # Sleep for an hour until we're out of the maintenance window.
    sleep 3600
  else

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
    drush ahjo-proxy:get-trustees positionsoftrust_council.json fi -v
    drush ahjo-proxy:get-trustees positionsoftrust_council.json sv -v
    echo "Aggregating latest decisionmaker changes: $(date)"
    drush ap:get decisionmakers --dataset=latest --filename=decisionmakers_latest.json -v
    drush ap:get decisionmakers --dataset=latest --langcode=sv --filename=decisionmakers_latest_sv.json -v
    if [ ${APP_ENV} = 'production' ]; then
      echo "Running aggregation and retry queues: $(date)"
      drush queue:run ahjo_api_aggregation_queue --time-limit=3600 -v
      drush queue:run ahjo_api_retry_queue --time-limit=1800 -v
      echo "Updating decision data: $(date)"
      drush ahjo-proxy:update-decisions --logic=record --limit=100 -v
      drush ahjo-proxy:update-decisions --logic=case --limit=100 -v
      drush ahjo-proxy:update-decisions --logic=language --limit=100 -v
      drush ahjo-proxy:update-decision-attachments --limit=100 -v
    fi
    echo "Migrating data for council members: $(date)"
    drush migrate-reset-status ahjo_trustees:council
    drush migrate-import ahjo_trustees:council --update
    #drush migrate-reset-status ahjo_trustees:council_sv
    #drush migrate-import ahjo_trustees:council_sv --update
    echo "Migrating data for decisionmakers: $(date)"
    drush migrate-reset-status ahjo_decisionmakers:latest
    drush migrate-reset-status ahjo_decisionmakers:latest_sv
    drush migrate-import ahjo_decisionmakers:latest --update
    drush migrate-import ahjo_decisionmakers:latest_sv --update
    echo "Checking for inactive decisionmakers: $(date)"
    drush ahjo-proxy:check-dm-status -v

    # Sleep for 22 hours. Whole process should take approximately 24 hours.
    sleep 79200

  fi
done
