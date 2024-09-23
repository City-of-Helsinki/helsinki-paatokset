#!/bin/bash

while true
do
  if [ ${APP_ENV} = 'production' ] || [ ${APP_ENV} = 'staging' ]; then
    echo "Checking access token: $(date)"
    drush ahjo-proxy:check-auth-token
  fi
  # Sleep for 15 minutes.
  sleep 900
done
