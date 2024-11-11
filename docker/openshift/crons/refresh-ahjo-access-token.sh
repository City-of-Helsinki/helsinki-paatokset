#!/bin/bash

# Renew auth and refresh tokens every 60 minutes.
while true
do
  # Don't try to refresh tokens between 01:00 and 07:00 UTC+3.
  # Ahjo might be offline for maintenance during the night.
  currenttime=$(date +%H:%M)
  if [[ "$currenttime" >= "04:00" ]] && [[ "$currenttime" < "22:00" ]]; then
    echo "Checking access token: $(date)"
    drush ahjo-proxy:check-auth-token refresh
  fi

  # Sleep for 60 minutes.
  sleep 3600
done
