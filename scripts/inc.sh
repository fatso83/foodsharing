#!/bin/bash

set -e

export FS_ENV=${FS_ENV:-dev}

MYSQL_USERNAME=${MYSQL_USERNAME:-root}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-root}

dir=$(cd $(dirname ${BASH_SOURCE[0]}) && pwd)

function dc() {
  $dir/docker-compose "$@"
}

function sql-query() {
  local database=$1 query=$2;
  dc exec -T db sh -c "mysql -p$MYSQL_PASSWORD $database -e \"$query\""
}

function sql-file() {
  local database=$1 filename=$2;
  echo "Executing sql file $FS_ENV/$database $filename"
  dc exec -T db sh -c "mysql -p$MYSQL_PASSWORD $database < /app/$filename"
}

function exec-in-container() {
  local container=$1; shift;
  local command=$@;
  dc exec -T --user $(id -u):$(id -g) $container sh -c "HOME=./ $command"
}

function run-in-container() {
  local container=$1; shift;
  local command=$@;
  dc run --rm --user $(id -u):$(id -g) $container sh -c "HOME=./ $command"
}

function exec-in-container-asroot() {
  local container=$1; shift;
  local command=$@;
  dc exec -T $container sh -c "$command"
}

function run-in-container-asroot() {
  local container=$1; shift;
  local command=$@;
  dc run --rm $container sh -c "$command"
}

function dropdb() {
  local database=$1;
  echo "Dropping database $FS_ENV/$database"
  sql-query mysql "drop database if exists $database"
}

function createdb() {
  local database=$1;
  echo "Creating database $FS_ENV/$database"
  sql-query mysql "create database if not exists $database"
}

function recreatedb() {
  local database=$1;
  dropdb "$database"
  createdb "$database"
}

function migratedb() {
  local database=$1;
  echo "Migrating database $FS_ENV/$database"
  sql-file $database migrations/initial.sql
  sql-file $database migrations/static.sql
  sql-file $database migrations/27-profilchange.sql
  sql-file $database migrations/27-verify.sql
  for f in migrations/incremental-*; do
    sql-file $database $f
  done
}

function wait-for-mysql() {
  exec-in-container-asroot db "while ! mysql -p$MYSQL_PASSWORD --silent -e 'select 1' >/dev/null 2>&1; do sleep 1; done"
}

function chat-npm-install() {
  # TODO: move this into scripts/mkdirs when MR#97 is merged
  run-in-container-asroot chat \
    "mkdir -p node_modules && chown -R $(id -u):$(id -g) node_modules"

  # have to do run, not exec, as container will not start until
  # node_modules is installed, this will run up a fresh container and
  # just run npm install
  run-in-container chat npm install
}
