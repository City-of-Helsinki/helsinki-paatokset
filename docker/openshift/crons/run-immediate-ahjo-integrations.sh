#!/bin/bash

while true
do
  echo "Reset single migrations"
  drush migrate-reset-status ahjo_meetings:single
  drush migrate-reset-status ahjo_cases:single
  drush migrate-reset-status ahjo_decisions:single
  echo "Running Ahjo API callback queue: $(date)"
  drush queue:run ahjo_api_subscriber_queue --time-limit=240 -v
  echo "Generating motions from meeting data: $(date)"
  drush ahjo-proxy:get-motions -v
  echo "Updating decision and motion data: $(date)"
  drush ahjo-proxy:update-decisions -v
  drush ahjo-proxy:update-decisions --logic=uniqueid --limit=100 -v
  drush ahjo-proxy:update-decisions --logic=outdated --limit=100 -v
  # Sleep for 5 minutes.
  sleep 300
done
