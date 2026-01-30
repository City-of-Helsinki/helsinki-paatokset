#!/bin/sh

# This script import data from Ahjo every day with an
# OpenShift CronJob.
#
# Most data is imported when Ahjo notifies päätökset via
# webhooks. However, this acts as an important fallback if
# webhooks misbehave. In addition, some entities, like
# trustees, is only imported by this script.
#
# This script should use drush migrate-reset-status before
# running migrations since this is run only once a day, so
# migration getting stuck could skip the import for multiple
# days.

if [ ${APP_ENV} = 'production' ] || [ ${APP_ENV} = 'staging' ]; then
  echo "Aggregating data for meetings: $(date)"
  drush ahjo-proxy:aggregate meetings --dataset=latest --queue -v

  echo "Aggregating data for cancelled meetings: $(date)"
  drush ahjo-proxy:aggregate meetings --dataset=cancelled --cancelledonly --queue -v

  echo "Aggregating data for decisions: $(date)"
  drush ahjo-proxy:aggregate decisions --dataset=latest --queue -v

  echo "Aggregating data for cases: $(date)"
  drush ahjo-proxy:aggregate cases --dataset=latest --queue -v
fi

echo "Aggregating council org data: $(date)"
drush ahjo-proxy:get-positionsoftrust -v

echo "Aggregating data for council members: $(date)"
drush ahjo-proxy:get-trustees positionsoftrust.json fi -v
drush ahjo-proxy:get-trustees positionsoftrust.json sv -v

if [ ${APP_ENV} = 'production' ] || [ ${APP_ENV} = 'staging' ]; then
  echo "Running aggregation and retry queues: $(date)"
  drush queue:run ahjo_api_aggregation_queue --time-limit=3600 -v
  drush queue:run ahjo_api_retry_queue --time-limit=1800 -v

  echo "Updating decision data: $(date)"
  drush ahjo-proxy:update-decisions --logic=record --limit=100 -v
  drush ahjo-proxy:update-decisions --logic=case --limit=100 -v
  drush ahjo-proxy:update-decisions --logic=language --limit=100 -v
  drush ahjo-proxy:update-decision-attachments --limit=100 -v
fi

echo "Migrating data for council members: $(date)"
drush migrate-reset-status ahjo_trustees:all
drush migrate-import ahjo_trustees:all --update --no-progress
drush migrate-reset-status ahjo_trustees:all_sv
drush migrate-import ahjo_trustees:all_sv --update --no-progress
drush migrate-reset-status ahjo_initiatives
drush migrate:import ahjo_initiatives --no-progress --interval 3600

echo "Migrating data for decisionmakers: $(date)"
drush migrate-reset-status ahjo_decisionmakers
drush migrate-import ahjo_decisionmakers --no-progress

drush migrate-reset-status ahjo_org_composition
drush migrate-import ahjo_org_composition --no-progress

echo "Checking for inactive decisionmakers: $(date)"
drush ahjo-proxy:check-dm-status -v
