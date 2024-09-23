#!/bin/bash

while true
do
  if [ ${APP_ENV} = 'production' ] || [ ${APP_ENV} = 'staging' ]; then
    echo "Refreshing access token: $(date)"
    drush ahjo-proxy:check-auth-token
  fi
  # Sleep for 60 minutes.
  sleep 3600
done
