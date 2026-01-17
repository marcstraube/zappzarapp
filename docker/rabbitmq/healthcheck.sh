#!/bin/sh
# RabbitMQ Healthcheck
# Uses su-exec to run rabbitmq-diagnostics as rabbitmq user
# This allows reading .erlang.cookie without DAC_READ_SEARCH capability

exec su-exec rabbitmq rabbitmq-diagnostics -q ping
