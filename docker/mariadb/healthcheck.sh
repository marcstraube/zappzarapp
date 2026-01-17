#!/bin/sh
# MariaDB Healthcheck
# Uses gosu to run healthcheck.sh as mysql user
# This allows reading .my-healthcheck.cnf without DAC_OVERRIDE capability

exec gosu mysql healthcheck.sh --connect --innodb_initialized
