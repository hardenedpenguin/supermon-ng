#!/bin/bash
# Restart DVSwitch bridge systemd units. Fixed unit list; invoked by supermon-ng via sudo.

set -u

SYSTEMCTL=/bin/systemctl
SERVICES="mmdvm_bridge analog_bridge"

restarted=0

for name in $SERVICES; do
    unit="${name}.service"
    if ! $SYSTEMCTL cat "$unit" &>/dev/null; then
        echo "skip:${name}:unit_not_found"
        continue
    fi
    if $SYSTEMCTL restart "$name" >/dev/null 2>&1; then
        echo "ok:${name}:restarted"
        restarted=$((restarted + 1))
    else
        err=$($SYSTEMCTL restart "$name" 2>&1 | tr '\n' ' ')
        echo "fail:${name}:${err}"
    fi
done

if [ "$restarted" -gt 0 ]; then
    exit 0
fi

exit 1
