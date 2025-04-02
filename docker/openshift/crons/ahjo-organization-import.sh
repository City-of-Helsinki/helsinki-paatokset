#!/bin/bash

while true
do
  sleep 3600

  # Don't run aggregations between 01:00 and 07:00 UTC+3.
  # Ahjo might be offline for maintenance during the night.
  time=$(date +%H:%M)
  if [[ "$time" > "22:00" ]] || [[ "$time" < "04:00" ]]; then
    continue
  fi

  # Interval: 48 hours.
  # Reset threshold: 1 hour.
  drush migrate:import ahjo_organizations --no-progress --update --interval 172800 --reset-threshold 3600
done
