#!/bin/bash

while true
do
  # Sleep for 1 hours.
  sleep 3600

  # Allow migrations to be run every 10 minutes and reset stuck migrations every minute.
  drush migrate:import allu_decisions --no-progress --reset-threshold 60 --interval 600
  drush migrate:import allu_approvals --no-progress --reset-threshold 60 --interval 600
done
