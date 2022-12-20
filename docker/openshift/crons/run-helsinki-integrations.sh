#!/bin/bash

while true
do
  # Sleep for 1 minute.
  sleep 360
  echo "Running Helsinki kanava migration: $(date)"
  drush migrate-reset-status paatokset_meeting_videos
  drush migrate-import paatokset_meeting_videos --update
  #echo "Running news migration: $(date)"
  #drush migrate-import paatokset_news --update
  # Sleep for 1 hour.
  sleep 3540
done
