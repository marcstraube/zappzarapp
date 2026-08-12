#!/bin/sh
# RabbitMQ Healthcheck
# Runs rabbitmq-diagnostics as the rabbitmq user so it can read
# .erlang.cookie without the DAC_READ_SEARCH capability.
# Root startup path (development): drop to rabbitmq via su-exec.
# Unprivileged path (production preset runs as uid 100): already the
# rabbitmq user - su-exec would fail at setgroups, so exec directly.

if [ "$(id -u)" = "0" ]; then
    exec su-exec rabbitmq rabbitmq-diagnostics -q ping
else
    exec rabbitmq-diagnostics -q ping
fi
