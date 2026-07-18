#!/bin/bash
# Manage root crontab entries for supermon-ng announcements.
# Invoked by supermon-ng via sudo -n.
# Subcommands: list | add | toggle | delete

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLAY_SCRIPT="${SCRIPT_DIR}/announce-play.sh"
MARKER="# Announcement:"
CRON_TAG="announce-play.sh"
PYTHON="${PYTHON:-/usr/bin/python3}"

usage() {
    echo "Usage: $0 list" >&2
    echo "       $0 add --min M --hour H --dom D --month M --dow D --node N --scope S --mode M --file F --desc D [--week W --use-nth 0|1]" >&2
    echo "       $0 toggle --id ID --enable 0|1" >&2
    echo "       $0 delete --id ID" >&2
    exit 1
}

validate_cron_field() {
    local val="$1"
    [[ "$val" =~ ^(\*|[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*)(/[0-9]+)?$ ]]
}

validate_name() {
    [[ "${1:-}" =~ ^[a-zA-Z0-9._-]+$ ]]
}

entry_id() {
    printf '%s\n%s' "$1" "$2" | md5sum | awk '{print $1}'
}

read_crontab() {
    crontab -l 2>/dev/null || true
}

write_crontab() {
    local file="$1"
    crontab "$file"
}

# Serialize read-modify-write of the root crontab so concurrent add/toggle/delete
# operations cannot clobber each other. Best-effort: continues if flock is absent.
lock_crontab() {
    local lockfile="${TMPDIR:-/tmp}/supermon-ng-announce-cron.lock"
    if command -v flock >/dev/null 2>&1; then
        exec 9>"$lockfile" 2>/dev/null || return 0
        flock -w 10 9 2>/dev/null || true
    fi
}

parse_entries() {
    local line comment="" last_comment="" enabled=1
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line//$'\r'/}"
        local trimmed="${line#"${line%%[![:space:]]*}"}"
        trimmed="${trimmed%"${trimmed##*[![:space:]]}"}"

        if [[ -z "$trimmed" ]]; then
            continue
        fi

        if [[ "$trimmed" == "$MARKER"* ]]; then
            last_comment="${trimmed#"$MARKER"}"
            last_comment="${last_comment# }"
            continue
        fi

        local cron_line="$trimmed"
        enabled=1
        if [[ "$cron_line" == \#* ]]; then
            enabled=0
            cron_line="${cron_line#\#}"
            cron_line="${cron_line# }"
        fi

        if [[ "$cron_line" != *"$CRON_TAG"* ]]; then
            last_comment=""
            continue
        fi

        local id
        id=$(entry_id "$MARKER ${last_comment}" "$cron_line")

        local min hour dom month dow
        read -r min hour dom month dow _ <<< "$cron_line"

        local node scope mode file desc="$last_comment"
        if [[ "$cron_line" =~ --node[[:space:]]+([0-9]+) ]]; then
            node="${BASH_REMATCH[1]}"
        fi
        if [[ "$cron_line" =~ --scope[[:space:]]+(local|global) ]]; then
            scope="${BASH_REMATCH[1]}"
        fi
        if [[ "$cron_line" =~ --mode[[:space:]]+(polite|priority) ]]; then
            mode="${BASH_REMATCH[1]}"
        fi
        if [[ "$cron_line" =~ --file[[:space:]]+(announcements/[a-zA-Z0-9._-]+) ]]; then
            file="${BASH_REMATCH[1]}"
            file="${file#announcements/}"
        fi

        printf '{"id":"%s","enabled":%s,"minute":"%s","hour":"%s","dom":"%s","month":"%s","dow":"%s","node":"%s","scope":"%s","mode":"%s","file":"%s","description":%s,"raw":%s}\n' \
            "$id" "$enabled" "$min" "$hour" "$dom" "$month" "$dow" "${node:-}" "${scope:-}" "${mode:-}" "${file:-}" \
            "$("$PYTHON" -c 'import json,sys; print(json.dumps(sys.argv[1]))' "$desc")" \
            "$("$PYTHON" -c 'import json,sys; print(json.dumps(sys.argv[1]))' "$cron_line")"

        last_comment=""
    done
}

cmd_list() {
    local entries=()
    local first=1
    while IFS= read -r row; do
        if [[ $first -eq 1 ]]; then
            first=0
            printf '[%s' "$row"
        else
            printf ',%s' "$row"
        fi
    done < <(read_crontab | parse_entries)
    if [[ $first -eq 1 ]]; then
        echo '[]'
    else
        echo ']'
    fi
}

build_play_command() {
    local node="$1" scope="$2" mode="$3" file="$4"
    printf '%s --node %s --scope %s --mode %s --file announcements/%s' \
        "$PLAY_SCRIPT" "$node" "$scope" "$mode" "$file"
}

cmd_add() {
    local min="" hour="" dom="*" month="*" dow="*" week="*" use_nth=0
    local node="" scope="local" mode="polite" file="" desc=""

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --min) min="${2:-}"; shift 2 ;;
            --hour) hour="${2:-}"; shift 2 ;;
            --dom) dom="${2:-}"; shift 2 ;;
            --month) month="${2:-}"; shift 2 ;;
            --dow) dow="${2:-}"; shift 2 ;;
            --week) week="${2:-}"; shift 2 ;;
            --use-nth) use_nth="${2:-0}"; shift 2 ;;
            --node) node="${2:-}"; shift 2 ;;
            --scope) scope="${2:-}"; shift 2 ;;
            --mode) mode="${2:-}"; shift 2 ;;
            --file) file="${2:-}"; shift 2 ;;
            --desc) desc="${2:-}"; shift 2 ;;
            *) echo "Unknown argument: $1" >&2; exit 1 ;;
        esac
    done

    lock_crontab
    [[ -n "$min" && -n "$hour" && -n "$node" && -n "$file" && -n "$desc" ]] || usage
    validate_cron_field "$min" || { echo "Invalid minute" >&2; exit 1; }
    validate_cron_field "$hour" || { echo "Invalid hour" >&2; exit 1; }
    validate_cron_field "$dom" || { echo "Invalid dom" >&2; exit 1; }
    validate_cron_field "$month" || { echo "Invalid month" >&2; exit 1; }
    validate_cron_field "$dow" || { echo "Invalid dow" >&2; exit 1; }
    [[ "$node" =~ ^[0-9]+$ ]] || { echo "Invalid node" >&2; exit 1; }
    [[ "$scope" == "local" || "$scope" == "global" ]] || { echo "Invalid scope" >&2; exit 1; }
    [[ "$mode" == "polite" || "$mode" == "priority" ]] || { echo "Invalid mode" >&2; exit 1; }
    validate_name "$file" || { echo "Invalid file" >&2; exit 1; }
    [[ "$desc" != *$'\n'* ]] || { echo "Invalid description" >&2; exit 1; }

    local play_cmd
    play_cmd=$(build_play_command "$node" "$scope" "$mode" "$file")

    local scope_note mode_note cron_line comment_line
    scope_note=$(printf '%s' "$scope" | tr '[:lower:]' '[:upper:]')
    mode_note=$(printf '%s' "$mode" | tr '[:lower:]' '[:upper:]')
    comment_line="${MARKER} ${desc} [${mode_note}] [${scope_note}] node ${node}"

    if [[ "$use_nth" == "1" && "$week" =~ ^[1-5]$ ]]; then
        local low=$(( (week - 1) * 7 + 1 ))
        local high
        if [[ "$week" == "5" ]]; then
            high=31
        else
            high=$((low + 6))
        fi
        cron_line="${min} ${hour} * * ${dow} /bin/bash -c '[ \$(date +\\%d) -ge ${low} ] && [ \$(date +\\%d) -le ${high} ] && ${play_cmd}'"
        comment_line="${comment_line} (${week}th week dow ${dow})"
    else
        cron_line="${min} ${hour} ${dom} ${month} ${dow} ${play_cmd}"
    fi

    local tmp
    tmp=$(mktemp)
    read_crontab > "$tmp" || true
    printf '%s\n%s\n' "$comment_line" "$cron_line" >> "$tmp"
    write_crontab "$tmp"
    rm -f "$tmp"
    echo "Schedule added"
}

cmd_toggle() {
    local target_id="" enable=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --id) target_id="${2:-}"; shift 2 ;;
            --enable) enable="${2:-}"; shift 2 ;;
            *) echo "Unknown argument: $1" >&2; exit 1 ;;
        esac
    done
    [[ -n "$target_id" && ( "$enable" == "0" || "$enable" == "1" ) ]] || usage

    lock_crontab
    local tmp found=0
    tmp=$(mktemp)
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line//$'\r'/}"
        local trimmed="${line#"${line%%[![:space:]]*}"}"
        trimmed="${trimmed%"${trimmed##*[![:space:]]}"}"

        if [[ "$trimmed" == "$MARKER"* ]]; then
            local next_line=""
            IFS= read -r next_line || true
            local raw="$next_line"
            local uncommented="$raw"
            if [[ "$uncommented" == \#* ]]; then
                uncommented="${uncommented#\#}"
                uncommented="${uncommented# }"
            fi
            if [[ "$uncommented" == *"$CRON_TAG"* ]]; then
                local id
                id=$(entry_id "$trimmed" "$uncommented")
                if [[ "$id" == "$target_id" ]]; then
                    found=1
                    printf '%s\n' "$trimmed" >> "$tmp"
                    if [[ "$enable" == "1" ]]; then
                        printf '%s\n' "$uncommented" >> "$tmp"
                    else
                        printf '# %s\n' "$uncommented" >> "$tmp"
                    fi
                    continue
                fi
            fi
            printf '%s\n' "$trimmed" >> "$tmp"
            [[ -n "$next_line" ]] && printf '%s\n' "$next_line" >> "$tmp"
            continue
        fi

        printf '%s\n' "$line" >> "$tmp"
    done < <(read_crontab)

    [[ $found -eq 1 ]] || { echo "Schedule not found" >&2; rm -f "$tmp"; exit 1; }
    write_crontab "$tmp"
    rm -f "$tmp"
    echo "Schedule updated"
}

cmd_delete() {
    local target_id=""
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --id) target_id="${2:-}"; shift 2 ;;
            *) echo "Unknown argument: $1" >&2; exit 1 ;;
        esac
    done
    [[ -n "$target_id" ]] || usage

    lock_crontab
    local tmp found=0 skip_next=0
    tmp=$(mktemp)
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line//$'\r'/}"
        local trimmed="${line#"${line%%[![:space:]]*}"}"
        trimmed="${trimmed%"${trimmed##*[![:space:]]}"}"

        if [[ "$trimmed" == "$MARKER"* ]]; then
            local next_line=""
            IFS= read -r next_line || true
            local raw="$next_line"
            local uncommented="$raw"
            if [[ "$uncommented" == \#* ]]; then
                uncommented="${uncommented#\#}"
                uncommented="${uncommented# }"
            fi
            if [[ "$uncommented" == *"$CRON_TAG"* ]]; then
                local id
                id=$(entry_id "$trimmed" "$uncommented")
                if [[ "$id" == "$target_id" ]]; then
                    found=1
                    continue
                fi
            fi
            printf '%s\n' "$trimmed" >> "$tmp"
            [[ -n "$next_line" ]] && printf '%s\n' "$next_line" >> "$tmp"
            continue
        fi

        printf '%s\n' "$line" >> "$tmp"
    done < <(read_crontab)

    [[ $found -eq 1 ]] || { echo "Schedule not found" >&2; rm -f "$tmp"; exit 1; }
    write_crontab "$tmp"
    rm -f "$tmp"
    echo "Schedule deleted"
}

[[ $# -ge 1 ]] || usage
case "$1" in
    list) shift; cmd_list "$@" ;;
    add) shift; cmd_add "$@" ;;
    toggle) shift; cmd_toggle "$@" ;;
    delete) shift; cmd_delete "$@" ;;
    *) usage ;;
esac
