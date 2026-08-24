#!/bin/sh
# pwsh watchdog — kill any long-running PowerShell/pwsh processes that escaped
# their timeout wrapper (e.g. stuck New-PSSession to an unreachable Exchange IP).
# This prevents orphan pwsh processes from pinning FPM workers (503 crashes).
# Safe ceiling: 300s. Exchange PS sessions normally finish in <60s.

MAX_AGE=300
NOW=$(date +%s)

for d in /proc/[0-9]*; do
    [ -r "$d/cmdline" ] || continue
    cmd=$(tr "\0" " " < "$d/cmdline" 2>/dev/null)
    case "$cmd" in
        *pwsh*|*/powershell*)
            # skip our own watchdog
            case "$cmd" in
                *pwsh_watchdog*) continue ;;
            esac
            pid=${d#/proc/}
            start=$(stat -c %Y "$d" 2>/dev/null) || continue
            age=$(( NOW - start ))
            if [ "$age" -gt "$MAX_AGE" ]; then
                logger -t pwsh_watchdog "killing orphan pwsh pid=$pid age=${age}s cmd=$(echo "$cmd" | cut -c1-120)"
                kill -9 "$pid" 2>/dev/null
            fi
            ;;
    esac
done
