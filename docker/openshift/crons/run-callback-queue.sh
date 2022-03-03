#!/bin/bash

while true
do
  echo "Running Ahjo API callback queue: $(date)"
  drush queue:run ahjo_api_subscriber_queue
  # Sleep for 30 minutes.
  sleep 1800
done
