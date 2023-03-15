#!/bin/bash

while true
do
  sleep 120
  drush sapi-i --limit=1000 --batch-size=50
  sleep 60
done
