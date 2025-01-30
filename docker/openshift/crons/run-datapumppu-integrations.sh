#!/bin/bash

# Sleep 100 seconds.
sleep 100

while true
do
  echo "Running Datapumppu migration: $(date)"
  # Interval 6 hours, reset threshold 10 minutes.
  drush migrate:import datapumppu_statements --interval 21600 --reset-threshold 600 --no-progress

  # Sleep for 24 hours.
  sleep 86400
done
