#!/usr/bin/env python3

import os
import subprocess
import re
import configparser
import requests
import json

# Asterisk/app_rpt does not persist ALERT when it exceeds ~500 chars. Cap as large as practical.
ALERT_MAX_LEN = 500

def run_command(command):
    try:
        process = subprocess.run(command, shell=True, capture_output=True, text=True, check=True)
        return process.stdout.strip()
    except subprocess.CalledProcessError as e:
        print(f"Error running command '{command}': {e}")
        return None
    except FileNotFoundError:
        print(f"Command not found: {command}")
        return None

def get_uptime():
    uptime_output = run_command("uptime -p")
    if uptime_output:
        return f"Up {uptime_output.replace('up ', '')}"
    return None

def get_cpu_load():
    uptime_output = run_command("uptime")
    if uptime_output:
        load_match = re.search(r"load average: (.+)", uptime_output)
        if load_match:
            return f'"Load Average: {load_match.group(1)}"'
    return None

def get_cpu_temperature(temp_unit):
    temp_c = None

    if os.path.exists("/sys/class/thermal/thermal_zone0/temp") and os.access("/sys/class/thermal/thermal_zone0/temp", os.R_OK):
        temp_raw = run_command("cat /sys/class/thermal/thermal_zone0/temp")
        if temp_raw and temp_raw.isdigit():
            temp_c = int(temp_raw) / 1000

    if temp_c is not None:
        temp_unit_upper = temp_unit.upper()
        if temp_unit_upper == "F":
            temp_val = (temp_c * 9 / 5) + 32
            unit_str = "F"
        elif temp_unit_upper == "C":
            temp_val = temp_c
            unit_str = "C"
        else:
            return '"Temp Unit Invalid in config"'

        temp_int = int(temp_val)
        temp_display = f"{temp_int} {unit_str}"
        temp_style = 'color: black; font-weight: bold;'

        if unit_str == "C":
            if temp_int <= 50:
                return f'"<span style=\'background-color:lightgreen;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
            elif temp_int <= 60:
                return f'"<span style=\'background-color:yellow;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
            else:
                return f'"<span style=\'background-color:#fa4c2d;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
        elif unit_str == "F":
            if temp_int <= 140:
                return f'"<span style=\'background-color:lightgreen;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
            elif temp_int <= 158:
                return f'"<span style=\'background-color:yellow;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
            else:
                return f'"<span style=\'background-color:#fa4c2d;\'><b><span style=\'{temp_style}\'>{temp_display}</span></b></span>"'
    else:
        return '"N/A"'

def get_weather(wx_code, wx_location):
    if not wx_code or not wx_location:
        return '" "'
    
    # Array of weather scripts to try in order
    weather_scripts = [
        "/usr/sbin/weather.rb",
        "/usr/sbin/weather.pl",
        "/usr/local/sbin/weather.sh"
    ]
    
    for weather_script in weather_scripts:
        if os.access(weather_script, os.X_OK):
            wx_raw = run_command(f"{weather_script} \"{wx_code}\" v")
            if wx_raw:
                return f'"<b>{wx_location}   ({wx_raw})</b>"'
    
    return '" "'

def get_disk_usage():
    disk_usage_output = run_command("df -h /")
    if disk_usage_output:
        lines = disk_usage_output.strip().split('\n')
        if len(lines) > 1:
            parts = lines[1].split()
            if len(parts) >= 5:
                used = parts[2]
                percent = parts[4]
                available = parts[3]
                return f'"Disk - {used} {percent} used, {available} remains"'
    return '"Disk - N/A"'

def _debug_log(msg: str) -> None:
    """Append to /tmp/node_status_debug.log for debugging API → ALERT flow."""
    from datetime import datetime
    try:
        ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        with open("/tmp/node_status_debug.log", "a", encoding="utf-8") as f:
            f.write(f"[{ts}] {msg}\n")
    except Exception:
        pass


def _debug_log_clear() -> None:
    """Truncate debug log at start of run so we only see latest."""
    try:
        with open("/tmp/node_status_debug.log", "w", encoding="utf-8") as f:
            f.write("")
    except Exception:
        pass


def _log_skywarn_api_error(msg, status_code=None, body_snippet=None):
    """Log API error to stderr and /tmp/skywarn_api_errors.log for debugging."""
    from datetime import datetime
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}"
    if status_code is not None:
        line += f" (HTTP {status_code})"
    print(line)
    try:
        with open("/tmp/skywarn_api_errors.log", "a", encoding="utf-8") as f:
            f.write(line + "\n")
            if body_snippet:
                # Log first 500 chars inline; if longer, add truncated traceback/body
                snippet = body_snippet[:500] if len(body_snippet) > 500 else body_snippet
                f.write(f"  | {snippet}\n")
                if len(body_snippet) > 500:
                    f.write("  (truncated)\n")
    except Exception:
        pass


def _format_alert_html(enabled_text, has_alerts, alerts, custom_link, no_alerts_text="<span style='color: #FF0000;'>No Alerts</span>", max_len=None):
    """Format alert data as HTML. Add full alerts only; stop before exceeding max_len (no mid-word truncation).
    When max_len is set, use compact prefix/links so we can fit two full alerts (e.g. Brazoria)."""
    if not has_alerts or not alerts:
        return f'"{enabled_text}<br>{no_alerts_text}"'
    compact = max_len is not None
    if compact:
        prefix = "<span style='color:SpringGreen'><b>SkywarnPlus-NG Enabled</b></span><br>"
    else:
        prefix = f"{enabled_text}<br>"
    total = prefix
    first = True
    for alert in alerts[:5]:
        if not isinstance(alert, dict):
            continue
        event = alert.get('event', 'Unknown')
        severity = alert.get('severity', 'Unknown')
        if severity == 'Extreme':
            color = '#FF0000'
        elif severity == 'Severe':
            color = '#FF6600'
        elif severity == 'Moderate':
            color = '#FFCC00'
        elif severity == 'Minor':
            color = '#FFFF00'
        else:
            color = '#FF0000'
        if custom_link:
            if compact:
                seg = f"<a href='{custom_link}' style='color:{color}'><b>{event}</b></a>"
            else:
                seg = f"<a target='WX ALERT' href='{custom_link}' style='color: {color}; text-decoration: none;'><b>{event}</b></a>"
        else:
            seg = f"<span style='color: {color};'><b>{event}</b></span>"
        candidate = total + ("" if first else "<br>") + seg
        if max_len is not None and len(candidate) > max_len:
            break
        total = candidate
        first = False
    if total == prefix:
        head = prefix.rstrip("<br>") if compact else enabled_text
        return f'"{head}<br>{no_alerts_text}"'
    return f'"{total}"'


def get_skywarnplus_alerts(api_url, master_enable, custom_link="", nodes=None):
    """
    Get alerts from SkywarnPlus-NG API.

    Uses per-node alerts (alerts_by_node) when the API provides them and nodes
    are configured, so each Supermon node shows alerts only for its counties.

    API errors (timeout, connection refused, HTTP errors) are logged to
    /tmp/skywarn_api_errors.log and to stderr (node-status-update.log when run
    via systemd). Check those when the dashboard shows "API Offline" or no alerts.

    Args:
        api_url: Base URL for SkywarnPlus-NG API (e.g. http://10.0.0.5:8100).
                 When using a reverse proxy at /skywarnplus-ng, include the path
                 (e.g. https://host/skywarnplus-ng).
        master_enable: "yes" to enable, anything else to disable.
        custom_link: Optional custom link for alerts (display only, not for fetching).
        nodes: List of node IDs (strings) from [general] NODE. Used for per-node alerts.

    Returns:
        Dict mapping node -> formatted HTML string (including quoted wrapper).
        Fallback key "" used for nodes not in alerts_by_node when using global fallback.
    """
    github_link = '<a href=\'https://github.com/hardenedpenguin/SkywarnPlus-NG\' style=\'color: inherit; text-decoration: none;\'>SkywarnPlus-NG</a>'
    enabled_text = f'<span style=\'color: SpringGreen;\'><b><u>{github_link} Enabled</u></b></span>'
    disabled_text = f'<span style=\'color: darkorange;\'><b><u>{github_link} Disabled</u></b></span>'
    no_alerts_text = '<span style=\'color: #FF0000;\'>No Alerts</span>'
    error_text = '<span style=\'color: #FF0000;\'>API Error</span>'

    node_list = [n.strip() for n in (nodes or []) if n and str(n).strip()]
    fallback = f'"{disabled_text}"'
    if master_enable.lower() != "yes":
        _debug_log("MASTER_ENABLE != yes | returning disabled for all")
        return {n: fallback for n in node_list} if node_list else {"": fallback}

    api_url = str(api_url).strip().rstrip('/')
    # Use 127.0.0.1 instead of localhost to avoid IPv6 connection refused when
    # SkywarnPlus-NG listens on IPv4 only (0.0.0.0:8100). Python may resolve
    # localhost to ::1 first, which fails in that case.
    try:
        from urllib.parse import urlparse, urlunparse
        p = urlparse(api_url)
        if p.hostname and p.hostname.lower() == 'localhost':
            netloc = '127.0.0.1' + ('' if p.port is None else f':{p.port}')
            api_url = urlunparse(p._replace(netloc=netloc))
    except Exception:
        pass
    status_url = f"{api_url}/api/status"
    if node_list:
        nodes_param = ",".join(str(n).strip() for n in node_list)
        status_url = f"{status_url}?nodes={nodes_param}"
    print(f"[SkywarnPlus] GET {status_url}")

    try:
        from urllib.parse import urlparse
        _u = urlparse(status_url)
        _no_proxy = _u.hostname in ('127.0.0.1', 'localhost', '::1')
        _kw = {'proxies': {'http': None, 'https': None}} if _no_proxy else {}
        response = requests.get(status_url, timeout=5, **_kw)
        if response.status_code != 200:
            body_snippet = getattr(response, 'text', None) or ""
            err_detail = ""
            try:
                parsed = json.loads(body_snippet)
                if isinstance(parsed, dict) and parsed.get("error"):
                    err_detail = parsed["error"]
            except Exception:
                pass
            msg = f"SkywarnPlus-NG API error: {status_url}"
            if err_detail:
                msg += f" | {err_detail}"
            _log_skywarn_api_error(msg, status_code=response.status_code, body_snippet=body_snippet or err_detail)
            _debug_log(f"API HTTP error {response.status_code} request={status_url} | returning API Error for all")
            one = f'"{enabled_text}<br>{error_text}"'
            return {n: one for n in node_list} if node_list else {"": one}

        try:
            data = response.json()
        except json.JSONDecodeError as e:
            _log_skywarn_api_error(
                f"SkywarnPlus-NG API JSON decode error: {e}",
                body_snippet=response.text[:500] if getattr(response, 'text', None) else None
            )
            _debug_log("API JSON decode error | returning API Error for all")
            one = f'"{enabled_text}<br>{error_text}"'
            return {n: one for n in node_list} if node_list else {"": one}

        if not isinstance(data, dict):
            _log_skywarn_api_error("SkywarnPlus-NG API returned non-dict response", body_snippet=str(type(data)))
            _debug_log("API non-dict response | returning API Error for all")
            one = f'"{enabled_text}<br>{error_text}"'
            return {n: one for n in node_list} if node_list else {"": one}

        alerts_by_node = data.get("alerts_by_node") or {}
        has_alerts = data.get("has_alerts", False)
        alerts = data.get("alerts", [])
        if not isinstance(alerts, list):
            alerts = []
        abn_keys = list(alerts_by_node.keys()) if isinstance(alerts_by_node, dict) else []
        print(f"[SkywarnPlus] API OK 200 | has_alerts={has_alerts} | alerts_by_node keys={abn_keys}")

        _debug_log(f"API request={status_url} | has_alerts={has_alerts} | alerts_by_node keys={abn_keys} | alerts count={len(alerts)}")

        use_per_node = bool(node_list and isinstance(alerts_by_node, dict))
        result = {}

        def _snippet(s: str, n: int = 72) -> str:
            t = (s or "").replace("\n", " ").strip()
            return (t[:n] + "..") if len(t) > n else t

        if use_per_node:
            for node in node_list:
                node_key = str(node).strip()
                per = alerts_by_node.get(node_key) if node_key else None
                if isinstance(per, dict) and "alerts" in per:
                    has = per.get("has_alerts", False)
                    alist = per.get("alerts", [])
                    if not isinstance(alist, list):
                        alist = []
                    result[node] = _format_alert_html(enabled_text, has, alist, custom_link, no_alerts_text, max_len=ALERT_MAX_LEN)
                    _debug_log(f"node={node} source=per_node has_alerts={has} alerts={len(alist)} snippet={_snippet(result[node])}")
                else:
                    result[node] = _format_alert_html(enabled_text, has_alerts, alerts, custom_link, no_alerts_text, max_len=ALERT_MAX_LEN)
                    _debug_log(f"node={node} source=global_fallback has_alerts={has_alerts} snippet={_snippet(result[node])}")
        else:
            single = _format_alert_html(enabled_text, has_alerts, alerts, custom_link, no_alerts_text, max_len=ALERT_MAX_LEN)
            _debug_log(f"source=global single snippet={_snippet(single)}")
            for node in node_list:
                result[node] = single
            if not node_list:
                result[""] = single

        return result

    except requests.exceptions.Timeout:
        import traceback
        _log_skywarn_api_error(
            f"SkywarnPlus-NG API timeout: {status_url}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API TIMEOUT request={status_url} | returning API Timeout for all nodes")
        one = f'"{enabled_text}<br><span style=\'color: #FF6600;\'>API Timeout</span>"'
        return {n: one for n in node_list} if node_list else {"": one}
    except requests.exceptions.ConnectionError as e:
        import traceback
        _log_skywarn_api_error(
            f"SkywarnPlus-NG API offline / connection refused: {status_url} | {e!r}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API CONNECTION ERROR request={status_url} | {e!r} | returning API Offline for all nodes")
        one = f'"{enabled_text}<br><span style=\'color: #FF6600;\'>API Offline</span>"'
        return {n: one for n in node_list} if node_list else {"": one}
    except Exception as e:
        import traceback
        _log_skywarn_api_error(
            f"SkywarnPlus-NG unexpected error: {e!r}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API ERROR request={status_url} | {e!r} | returning API Error for all nodes")
        one = f'"{enabled_text}<br>{error_text}"'
        return {n: one for n in node_list} if node_list else {"": one}

def _rpt_conf_exists():
    return os.path.isfile("/etc/asterisk/rpt.conf")


def update_node_variables(node, cpu_up, cpu_load, cpu_temp_dsp, wx, disk_usage, alert):
    """Update RPT variables for a node. Returns 'ok', 'skip_rpt', 'error_vars', or 'error_alert'."""
    # Match section headers: [546051] or [546051](node-main) etc. at line start
    check_node_command = ["grep", "-qE", rf"^[[:blank:]]*\[{re.escape(str(node))}\]([[:blank:]]*\([^)]*\))?[[:blank:]]*$", "/etc/asterisk/rpt.conf"]
    process_check = subprocess.run(check_node_command, capture_output=True, text=True)
    if process_check.returncode != 0:
        if process_check.returncode == 1:
            print(f"Invalid Node {node}: not found in /etc/asterisk/rpt.conf")
        else:
            err = (process_check.stderr or "Unknown error").strip()
            if "No such file" in err:
                pass  # Already logged once at start
            else:
                print(f"Error checking node {node} in /etc/asterisk/rpt.conf: {err}")
        return "skip_rpt"

    command = [
        "/usr/sbin/asterisk",
        "-rx",
        f"rpt set variable {node} cpu_up=\"{cpu_up}\" cpu_load={cpu_load} cpu_temp={cpu_temp_dsp} WX={wx} DISK={disk_usage}"
    ]
    result = subprocess.run(command, capture_output=True, text=True, check=False)
    if result.returncode != 0:
        print(f"Error setting variables for node {node}: {result.stderr}")
        return "error_vars"
    print(f"Updated Variables Node {node} using rpt set variable")

    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False, encoding='utf-8') as tmp_alert_file:
        tmp_alert_file.write(alert)
        alert_file_path = tmp_alert_file.name

    with tempfile.NamedTemporaryFile(mode='w', suffix='.sh', delete=False) as tmp_script:
        tmp_script.write(f'''#!/bin/bash
set -e
ALERT_RAW=$(cat "{alert_file_path}")
ALERT_ESC=$(printf %s "$ALERT_RAW" | sed 's/\\\\/\\\\\\\\/g; s/"/\\\\"/g')
/usr/sbin/asterisk -rx "rpt set variable {node} ALERT=\\"$ALERT_ESC\\""
rm -f "{alert_file_path}"
''')
        script_path = tmp_script.name

    try:
        os.chmod(script_path, 0o755)
        result_alert = subprocess.run(['bash', script_path], capture_output=True, text=True, check=False)
    finally:
        try:
            os.unlink(script_path)
        except Exception:
            pass
    if result_alert.returncode != 0:
        print(f"Error setting ALERT for node {node}: return code {result_alert.returncode}")
        if result_alert.stderr:
            print(f"Stderr: {result_alert.stderr[:200]}")
        if result_alert.stdout:
            print(f"Stdout: {result_alert.stdout[:200]}")
        return "error_alert"
    print(f"Updated ALERT Node {node} using rpt set variable")
    return "ok"


if __name__ == "__main__":
    _debug_log_clear()
    script_dir = os.path.dirname(os.path.realpath(__file__))
    config_file = os.path.join(script_dir, "node_info.ini")

    if not os.path.exists(config_file):
        print(f"Error: Configuration file '{config_file}' not found.")
        exit(1)

    config = configparser.ConfigParser()
    config.read(config_file)
    print(f"[NodeStatus] config={config_file}")

    nodes = config.get("general", "NODE", fallback="").split()
    wx_code = config.get("general", "WX_CODE", fallback="")
    wx_location = config.get("general", "WX_LOCATION", fallback="")
    temp_unit = config.get("general", "TEMP_UNIT", fallback="F")

    master_enable = config.get("skywarnplus", "MASTER_ENABLE", fallback="no").strip()
    api_url = config.get("skywarnplus", "API_URL", fallback="http://localhost:8100").strip()
    custom_link = config.get("skywarnplus", "CUSTOM_LINK", fallback="").strip()
    print(f"[NodeStatus] API_URL={api_url!r} MASTER_ENABLE={master_enable!r} NODES={nodes}")

    cpu_up = get_uptime()
    cpu_load = get_cpu_load()
    cpu_temp_dsp = get_cpu_temperature(temp_unit)
    wx = get_weather(wx_code, wx_location)
    disk_usage_info = get_disk_usage()

    node_list = []
    seen = set()
    for n in nodes:
        if not n or not str(n).strip():
            continue
        s = str(n).strip()
        if s not in seen:
            seen.add(s)
            node_list.append(s)

    if not node_list:
        _debug_log("exit early: no nodes configured")
        print("No nodes specified in the configuration file.")
        exit(0)

    if not _rpt_conf_exists():
        _debug_log(f"exit early: no rpt.conf | nodes={node_list}")
        print("[NodeStatus] /etc/asterisk/rpt.conf not found; cannot update any node variables.")
        print(f"[NodeStatus] Summary: all {len(node_list)} node(s) skipped (no rpt.conf)")
        exit(0)

    print(f"[NodeStatus] Updating {len(node_list)} node(s): {', '.join(node_list)}")
    alerts_map = get_skywarnplus_alerts(api_url, master_enable, custom_link, nodes=node_list)
    default_alert = alerts_map.get(node_list[0], "") if node_list else ""

    def _snippet(s: str, n: int = 72) -> str:
        t = (s or "").replace("\n", " ").strip()
        return (t[:n] + "..") if len(t) > n else t

    summary = []
    for node in node_list:
        a = alerts_map.get(node)
        b = alerts_map.get("")
        alert = a or b or default_alert
        if a:
            src = "map[node]"
        elif b:
            src = "map['']"
        else:
            src = "default_alert"
        if isinstance(alert, str) and alert.startswith('"') and alert.endswith('"'):
            alert = alert[1:-1]
        _debug_log(f"WRITE node={node} source={src} len={len(alert)} snippet={_snippet(alert)}")
        status = update_node_variables(node, cpu_up, cpu_load, cpu_temp_dsp, wx, disk_usage_info, alert)
        _debug_log(f"WRITE node={node} status={status}")
        if status == "ok":
            summary.append(f"{node} OK")
        elif status == "skip_rpt":
            summary.append(f"{node} skipped (not in rpt.conf)")
        elif status == "error_vars":
            summary.append(f"{node} error (vars)")
        else:
            summary.append(f"{node} error (ALERT)")

    print(f"[NodeStatus] Summary: {' | '.join(summary)}")
    _debug_log(f"run complete | summary={' | '.join(summary)}")
    exit(0)

