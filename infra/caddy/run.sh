#!/bin/sh
# Runs Caddy and reloads it whenever the builder publishes a new release, so the redirects it imports from the
# release stay current. The reload goes through Caddy's admin API, which only listens inside this container.
set -u
CONFIG=/etc/caddy/Caddyfile

caddy run --config "$CONFIG" --adapter caddyfile &
caddy_pid=$!
trap 'kill -TERM "$caddy_pid" 2>/dev/null; wait "$caddy_pid"; exit 0' TERM INT

live=$(readlink /srv/site/current 2>/dev/null || true)
while kill -0 "$caddy_pid" 2>/dev/null; do
	sleep 10 &
	wait $!
	now=$(readlink /srv/site/current 2>/dev/null || true)
	if [ "$now" != "$live" ] && caddy reload --config "$CONFIG" --adapter caddyfile; then
		live=$now
	fi
done
wait "$caddy_pid"
