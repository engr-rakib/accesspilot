#!/bin/bash
# Auto-fix monitoring network routes
# Reads all monitored IPs from JSON, auto-discovers subnets, fixes dead routes
# Runs at host startup via systemd (monitoring-routes.service)

print_status() {
    local icon msg
    case "$1" in
        ok) icon="[OK]";;
        fix) icon="[FIX]";;
        fail) icon="[FAIL]";;
        info) icon="[INFO]";;
    esac
    echo "$icon $2"
}

get_default_gw() {
    ip route show default | awk '{print $3; exit}'
}

get_default_iface() {
    ip route show default | awk '{print $5; exit}'
}

# Discover all /24 subnets from monitored servers JSON
discover_subnets() {
    local json_file="/data/secure/monitoring/monitored_servers.json"
    declare -A subnets

    if [ -f "$json_file" ]; then
        while IFS= read -r ip; do
            [ -z "$ip" ] && continue
            # Extract /24 subnet
            local subnet=$(echo "$ip" | awk -F. '{print $1"."$2"."$3".0/24"}')
            [ -n "$subnet" ] && subnets["$subnet"]=1
        done < <(grep -oP '"ip"\s*:\s*"\K[0-9.]+' "$json_file")
    fi

    # Also add any hardcoded fallback subnets
    [ ${#subnets[@]} -eq 0 ] && subnets["192.168.150.0/24"]=1

    echo "${!subnets[@]}"
}

fix_single_subnet() {
    local subnet="$1"
    local gw="$2"
    local iface="$3"

    local current_route
    current_route=$(ip route show "$subnet" 2>/dev/null)

    # Check if route exists via a linkdown/dead interface
    local dead_iface
    dead_iface=$(echo "$current_route" | grep -oP 'dev \K\S+' | head -1)
    if [ -n "$dead_iface" ]; then
        local iface_state
        iface_state=$(cat /sys/class/net/"$dead_iface"/operstate 2>/dev/null)
        if [ "$iface_state" = "down" ]; then
            print_status fix "Removing dead route $subnet via $dead_iface ($iface_state)"
            ip route del "$subnet" dev "$dead_iface" 2>/dev/null
            current_route=""
        fi
    fi

    # If subnet is directly connected on a live interface, skip gateway route
    local local_iface
    local_iface=$(ip -o addr show | awk -v s="$subnet" '$4 == s {print $2; exit}')
    if [ -n "$local_iface" ]; then
        local iface_state
        iface_state=$(cat /sys/class/net/"$local_iface"/operstate 2>/dev/null)
        if [ "$iface_state" = "up" ]; then
            print_status ok "$subnet directly connected via $local_iface (UP)"
            return 0
        fi
        print_status fix "$subnet on $local_iface but state=$iface_state — removing bad route and adding via gateway"
        # If directly connected but interface is DOWN, the route is useless
        # Remove the direct route and add via gateway instead
        ip route del "$subnet" dev "$local_iface" 2>/dev/null
    fi

    # Add/replace route via gateway if not already present
    if [ -z "$current_route" ] || ! echo "$current_route" | grep -q "via $gw"; then
        print_status fix "Adding route $subnet via $gw"
        # Let kernel auto-select interface (omit dev); if add fails, try replace
        if ! ip route add "$subnet" via "$gw" 2>/dev/null; then
            ip route replace "$subnet" via "$gw" 2>/dev/null && \
            print_status ok "$subnet via $gw (replaced)" || \
            print_status fail "$subnet — could not add/replace route"
        else
            print_status ok "$subnet via $gw"
        fi
    else
        print_status ok "$subnet via $gw (already set)"
    fi
    return 0
}

# --- Main ---
GW=$(get_default_gw)
IFACE=$(get_default_iface)

if [ -z "$GW" ] || [ -z "$IFACE" ]; then
    print_status fail "No default gateway found"
    exit 1
fi

print_status info "Default gateway: $GW via $IFACE"

# Discover all subnets from monitored servers
SUBNETS=$(discover_subnets)
FIXED=0
FAILED=0

for subnet in $SUBNETS; do
    if fix_single_subnet "$subnet" "$GW" "$IFACE"; then
        ((FIXED++))
    else
        ((FAILED++))
    fi
done

print_status info "Fixed: $FIXED subnets | Failed: $FAILED"

# --- Persist to netplan (only new routes not already in netplan) ---
if [ "$1" = "--persist" ] && [ "$FIXED" -gt 0 ]; then
    NETPLAN_FILE=$(ls /etc/netplan/*.yaml 2>/dev/null | grep -v _bck | head -1)
    if [ -n "$NETPLAN_FILE" ]; then
        for subnet in $SUBNETS; do
            if ! grep -q "$subnet" "$NETPLAN_FILE" 2>/dev/null; then
                # Only persist if the route was actually added (not already via default)
                if ip route show "$subnet" | grep -q "via $GW"; then
                    route_entry="      - to: $subnet\n        via: $GW"
                    sed -i "/^      nameservers:/i\      routes:\n$route_entry" "$NETPLAN_FILE" 2>/dev/null && \
                    print_status fix "Persisted $subnet to netplan"
                fi
            fi
        done
        netplan apply 2>/dev/null
    fi
fi

exit 0
