#!/bin/bash

while true
do
  echo "Running Ahjo API callback queue: $(date)"
  drush queue:run ahjo_api_subscriber_queue
  echo "Generating motions from meeting data: $(date)"
  drush ahjo-proxy:get-motions
  echo "Updating decision and motion data: $(date)"
  drush ahjo-proxy:update-decisions
  # Sleep for 5 minutes.
  sleep 300
done
