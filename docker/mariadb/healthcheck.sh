#!/bin/sh
# MariaDB Healthcheck
# Runs the image's healthcheck.sh as the mysql user so it can read
# .my-healthcheck.cnf without the DAC_OVERRIDE capability.
# Root startup path (development): drop to mysql via gosu.
# Unprivileged path (production preset runs as uid 999): already the mysql
# user - gosu would fail at setgroups, so exec directly.

if [ "$(id -u)" = "0" ]; then
    exec gosu mysql healthcheck.sh --connect --innodb_initialized
else
    exec healthcheck.sh --connect --innodb_initialized
fi
