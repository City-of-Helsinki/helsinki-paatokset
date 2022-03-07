#!/bin/bash

while true
do
  echo "Running Helsinki kanava migration: $(date)"
  drush migrate-import paatokset_helsinki_kanava --update
  echo "Running news migration: $(date)"
  drush migrate-import paatokset_news --update
  # Sleep for 1 hour.
  sleep 3600
done
