#!/bin/bash

while true
do
  # Renew auth and refresh tokens every 60 minutes.

  # Don't try to refresh tokens between 01:00 and 07:00 UTC+3.
  # Ahjo might be offline for maintenance during the night.
  currenttime=$(date +%H:%M)
  if [[ "$currenttime" > "22:00" ]] || [[ "$currenttime" < "04:00" ]]; then
    # Sleep for an hour until we're out of the maintenance window.
    sleep 3600
  else
    echo "Checking access token: $(date)"
    drush ahjo-api:refresh-token
    # Sleep for 60 minutes.
    sleep 3600
  fi
done
