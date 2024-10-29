#!/bin/bash

while true
do
  # Renew auth and refresh tokens every 60 minutes.
  echo "Checking access token: $(date)"
  drush ahjo-proxy:check-auth-token refresh
  # Sleep for 60 minutes.
  sleep 3600
done
