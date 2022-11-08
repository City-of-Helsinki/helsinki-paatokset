#!/bin/bash

while true
do
  echo "Running Ahjo API callback queue: $(date)"
  drush queue:run ahjo_api_subscriber_queue -v
  echo "Generating motions from meeting data: $(date)"
  drush ahjo-proxy:get-motions -v
  echo "Updating decision and motion data: $(date)"
  drush ahjo-proxy:update-decisions -v
  drush ahjo-proxy:update-decisions --logic=uniqueid --limit=100 -v
  drush ahjo-proxy:update-decisions --logic=outdated --limit=100 -v
  # Sleep for 5 minutes.
  sleep 300
done
