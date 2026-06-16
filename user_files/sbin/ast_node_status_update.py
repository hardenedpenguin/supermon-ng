#!/usr/bin/env python3

import os
import subprocess
import re
import time
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

def _read_cpu_temp_celsius():
    """Read CPU/SoC temperature in Celsius from hwmon or thermal zone."""
    import glob

    for path in sorted(glob.glob("/sys/class/hwmon/hwmon*/temp1_input")):
        try:
            with open(path, encoding="utf-8") as f:
                temp_raw = f.read().strip()
            if temp_raw.isdigit():
                return int(temp_raw) / 1000
        except OSError:
            continue

    thermal_path = "/sys/class/thermal/thermal_zone0/temp"
    if os.path.exists(thermal_path) and os.access(thermal_path, os.R_OK):
        try:
            with open(thermal_path, encoding="utf-8") as f:
                temp_raw = f.read().strip()
            if temp_raw.isdigit():
                return int(temp_raw) / 1000
        except OSError:
            pass

    return None


def get_cpu_temperature(temp_unit):
    temp_c = _read_cpu_temp_celsius()

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

def _config_flag_yes(value):
    return str(value or "").strip().lower() in ("yes", "1", "true", "on")


def _weather_config_path():
    return os.environ.get("WEATHER_CONFIG", "/etc/asterisk/local/weather.ini")


def _weather_ini_location_source():
    """Return location_source from weather.ini (postal or gps), if readable."""
    path = _weather_config_path()
    if not os.path.isfile(path):
        return ""
    try:
        ini = configparser.ConfigParser()
        ini.read(path)
        for section in ("weather", "DEFAULT"):
            if ini.has_section(section) and ini.has_option(section, "location_source"):
                return ini.get(section, "location_source", fallback="").strip().lower()
        if ini.has_option("DEFAULT", "location_source"):
            return ini.get("DEFAULT", "location_source", fallback="").strip().lower()
    except Exception:
        pass
    return ""


def _is_placeholder_wx_code(wx_code):
    code = str(wx_code or "").strip().lower()
    if not code:
        return True
    if code in ("00000", "000000", "none", "n/a", "na", "unset", "placeholder"):
        return True
    return bool(re.fullmatch(r"0+", code))


def _is_placeholder_wx_location(wx_location):
    loc = str(wx_location or "").strip().lower()
    if not loc:
        return True
    return loc in ("city, state", "city state", "n/a", "na", "none", "unset")


def run_weather_command(argv):
    """Run a weather script; return stdout text or None (no exception spam)."""
    try:
        process = subprocess.run(argv, capture_output=True, text=True, check=True)
        out = process.stdout.strip()
        return out if out else None
    except subprocess.CalledProcessError as e:
        err = (e.stderr or e.stdout or "").strip()
        hint = err.split("\n", 1)[0][:240] if err else str(e)
        print(f"[NodeStatus] Weather lookup failed ({' '.join(argv)}): {hint}")
        return None
    except FileNotFoundError:
        print(f"[NodeStatus] Weather command not found: {argv[0]}")
        return None


def resolve_weather_gps(wx_use_gps, wx_code):
    """True when node status should call weather.rb --gps."""
    if wx_use_gps:
        return True
    if _weather_ini_location_source() != "gps":
        return False
    # weather.ini GPS mode: use --gps unless Supermon has a real postal/airport code set
    return _is_placeholder_wx_code(wx_code)


def _saytime_tmp_dir():
    return os.environ.get("SAYTIME_TMP", "/tmp")


def _read_saytime_gps_fix():
    """Read lat/lon written by saytime weather.rb from gpsd (saytime-gps-fix.json)."""
    path = os.path.join(_saytime_tmp_dir(), "saytime-gps-fix.json")
    try:
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
        lat = float(data["lat"])
        lon = float(data["lon"])
        if abs(lat) < 0.0001 and abs(lon) < 0.0001:
            return None, None
        return lat, lon
    except (OSError, json.JSONDecodeError, KeyError, TypeError, ValueError):
        return None, None


def _format_nominatim_address(address):
    """Build a short place name from Nominatim reverse-geocode address parts."""
    if not isinstance(address, dict):
        return None
    locality = (
        address.get("city")
        or address.get("town")
        or address.get("village")
        or address.get("hamlet")
        or address.get("municipality")
        or address.get("suburb")
    )
    region = address.get("state") or address.get("region") or address.get("county")
    parts = [p for p in (locality, region) if p]
    if parts:
        return ", ".join(parts)
    country = address.get("country")
    return country if country else None


def _load_gps_place_cache():
    path = os.path.join(_saytime_tmp_dir(), "supermon-gps-place-cache.json")
    try:
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
        return data if isinstance(data, dict) else {}
    except (OSError, json.JSONDecodeError):
        return {}


def _save_gps_place_cache(cache):
    path = os.path.join(_saytime_tmp_dir(), "supermon-gps-place-cache.json")
    try:
        with open(path, "w", encoding="utf-8") as f:
            json.dump(cache, f)
    except OSError:
        pass


def _reverse_geocode_place_name(lat, lon, max_age_seconds=30 * 24 * 3600):
    """Resolve city/region from coordinates (gpsd has no place names). Uses Nominatim + cache."""
    key = f"{round(lat, 4)},{round(lon, 4)}"
    cache = _load_gps_place_cache()
    entry = cache.get(key)
    if isinstance(entry, dict) and entry.get("name"):
        age = time.time() - float(entry.get("ts", 0))
        if max_age_seconds <= 0 or age <= max_age_seconds:
            return entry["name"]

    url = (
        "https://nominatim.openstreetmap.org/reverse"
        f"?lat={lat}&lon={lon}&format=json&zoom=12&addressdetails=1"
    )
    headers = {
        "User-Agent": "Supermon-NG/1.0 (AllStar node status; amateur radio dashboard)",
    }
    try:
        response = requests.get(url, timeout=10, headers=headers)
        if response.status_code != 200:
            return None
        data = response.json()
        name = _format_nominatim_address(data.get("address"))
        if not name:
            display = (data.get("display_name") or "").strip()
            if display:
                name = ", ".join(display.split(", ")[:2])
        if name:
            cache[key] = {"name": name, "ts": time.time()}
            _save_gps_place_cache(cache)
            return name
    except requests.RequestException:
        return None
    return None


def _gps_display_label(wx_location):
    """Dashboard label for GPS weather: custom WX_LOCATION, reverse-geocoded place, or coords."""
    custom = (wx_location or "").strip()
    if custom and not _is_placeholder_wx_location(custom):
        return custom

    lat, lon = _read_saytime_gps_fix()
    if lat is not None and lon is not None:
        place = _reverse_geocode_place_name(lat, lon)
        if place:
            return place
        return f"GPS {lat:.4f}, {lon:.4f}"

    return "GPS"


def get_weather(wx_code, wx_location, use_gps=False):
    """Fetch weather text for the node WX variable.

    When use_gps is True, calls saytime_weather_rb weather.rb --gps v (gpsd).
    Otherwise uses wx_code (postal, ICAO, lat,lon, etc.) with legacy scripts as fallback.
    """
    wx_code = str(wx_code or "").strip()
    label = (wx_location or "").strip()

    if use_gps:
        weather_rb = "/usr/sbin/weather.rb"
        if os.access(weather_rb, os.X_OK):
            print("[NodeStatus] Weather: GPS (weather.rb --gps)")
            wx_raw = run_weather_command([weather_rb, "--gps", "v"])
            label = _gps_display_label(wx_location)
            if label != "GPS":
                print(f"[NodeStatus] GPS place label: {label}")
            if wx_raw:
                return f'"<b>{label}   ({wx_raw})</b>"'
        else:
            print("[NodeStatus] GPS weather requested but /usr/sbin/weather.rb not found or not executable")
        return '" "'

    if _is_placeholder_wx_code(wx_code) or _is_placeholder_wx_location(label):
        print(
            "[NodeStatus] Weather skipped: set a real WX_CODE and WX_LOCATION, "
            "enable WX_USE_GPS=yes, or set location_source=gps in weather.ini"
        )
        return '" "'

    weather_scripts = [
        "/usr/sbin/weather.rb",
        "/usr/sbin/weather.pl",
        "/usr/local/sbin/weather.sh",
    ]

    for weather_script in weather_scripts:
        if os.access(weather_script, os.X_OK):
            wx_raw = run_weather_command([weather_script, wx_code, "v"])
            if wx_raw:
                return f'"<b>{label}   ({wx_raw})</b>"'

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
    if os.environ.get("SUPERMON_NODE_STATUS_DEBUG", "").lower() not in ("1", "true", "yes", "on"):
        return
    from datetime import datetime
    try:
        ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        with open("/tmp/node_status_debug.log", "a", encoding="utf-8") as f:
            f.write(f"[{ts}] {msg}\n")
    except Exception:
        pass


def _debug_log_clear() -> None:
    """Truncate debug log at start of run so we only see latest."""
    if os.environ.get("SUPERMON_NODE_STATUS_DEBUG", "").lower() not in ("1", "true", "yes", "on"):
        return
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


def _empty_alert():
    """Quoted empty ALERT for Asterisk (keeps sysinfo clean when no active alerts)."""
    return '""'


def _format_alert_html(has_alerts, alerts, max_len=None):
    """Format active alerts as HTML only. No provider header or status lines."""
    if not has_alerts or not alerts:
        return _empty_alert()
    total = ""
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
        seg = f"<span style='color: {color};'><b>{event}</b></span>"
        candidate = total + ("" if first else "<br>") + seg
        if max_len is not None and len(candidate) > max_len:
            break
        total = candidate
        first = False
    if not total:
        return _empty_alert()
    return f'"{total}"'


def get_alerts_from_api(api_url, master_enable, nodes=None, product_name="SkywarnPlus-NG"):
    """
    Get alerts from SkywarnPlus-NG- or CANWarn-NG-style API.

    Uses per-node alerts (alerts_by_node) when the API provides them and nodes
    are configured, so each Supermon node shows alerts only for its counties.

    API errors (timeout, connection refused, HTTP errors) are logged to
    /tmp/skywarn_api_errors.log and to stderr (node-status-update.log when run
    via systemd). The dashboard ALERT field stays empty unless there are active alerts.

    Args:
        api_url: Base URL for SkywarnPlus-NG API (e.g. http://10.0.0.5:8100).
                 When using a reverse proxy at /skywarnplus-ng, include the path
                 (e.g. https://host/skywarnplus-ng).
        master_enable: "yes" to enable, anything else to disable.
        nodes: List of node IDs (strings) from [general] NODE. Used for per-node alerts.

    Returns:
        Dict mapping node -> formatted HTML string (including quoted wrapper).
        Fallback key "" used for nodes not in alerts_by_node when using global fallback.
    """
    product = (product_name or "SkywarnPlus-NG").strip()

    node_list = [n.strip() for n in (nodes or []) if n and str(n).strip()]
    if master_enable.lower() != "yes":
        _debug_log("MASTER_ENABLE != yes | ALERT cleared for all nodes")
        empty = _empty_alert()
        return {n: empty for n in node_list} if node_list else {"": empty}

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
    print(f"[{product}] GET {status_url}")

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
            msg = f"{product} API error: {status_url}"
            if err_detail:
                msg += f" | {err_detail}"
            _log_skywarn_api_error(msg, status_code=response.status_code, body_snippet=body_snippet or err_detail)
            _debug_log(f"API HTTP error {response.status_code} request={status_url} | ALERT cleared for all")
            empty = _empty_alert()
            return {n: empty for n in node_list} if node_list else {"": empty}

        try:
            data = response.json()
        except json.JSONDecodeError as e:
            _log_skywarn_api_error(
                f"SkywarnPlus-NG API JSON decode error: {e}",
                body_snippet=response.text[:500] if getattr(response, 'text', None) else None
            )
            _debug_log("API JSON decode error | ALERT cleared for all")
            empty = _empty_alert()
            return {n: empty for n in node_list} if node_list else {"": empty}

        if not isinstance(data, dict):
            _log_skywarn_api_error(f"{product} API returned non-dict response", body_snippet=str(type(data)))
            _debug_log("API non-dict response | ALERT cleared for all")
            empty = _empty_alert()
            return {n: empty for n in node_list} if node_list else {"": empty}

        alerts_by_node = data.get("alerts_by_node") or {}
        has_alerts = data.get("has_alerts", False)
        alerts = data.get("alerts", [])
        if not isinstance(alerts, list):
            alerts = []
        abn_keys = list(alerts_by_node.keys()) if isinstance(alerts_by_node, dict) else []
        print(f"[{product}] API OK 200 | has_alerts={has_alerts} | alerts_by_node keys={abn_keys}")

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
                    result[node] = _format_alert_html(has, alist, max_len=ALERT_MAX_LEN)
                    _debug_log(f"node={node} source=per_node has_alerts={has} alerts={len(alist)} snippet={_snippet(result[node])}")
                else:
                    result[node] = _format_alert_html(has_alerts, alerts, max_len=ALERT_MAX_LEN)
                    _debug_log(f"node={node} source=global_fallback has_alerts={has_alerts} snippet={_snippet(result[node])}")
        else:
            single = _format_alert_html(has_alerts, alerts, max_len=ALERT_MAX_LEN)
            _debug_log(f"source=global single snippet={_snippet(single)}")
            for node in node_list:
                result[node] = single
            if not node_list:
                result[""] = single

        return result

    except requests.exceptions.Timeout:
        import traceback
        _log_skywarn_api_error(
            f"{product} API timeout: {status_url}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API TIMEOUT request={status_url} | ALERT cleared for all nodes")
        empty = _empty_alert()
        return {n: empty for n in node_list} if node_list else {"": empty}
    except requests.exceptions.ConnectionError as e:
        import traceback
        _log_skywarn_api_error(
            f"{product} API offline / connection refused: {status_url} | {e!r}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API CONNECTION ERROR request={status_url} | {e!r} | ALERT cleared for all nodes")
        empty = _empty_alert()
        return {n: empty for n in node_list} if node_list else {"": empty}
    except Exception as e:
        import traceback
        _log_skywarn_api_error(
            f"{product} unexpected error: {e!r}",
            body_snippet=traceback.format_exc()
        )
        _debug_log(f"API ERROR request={status_url} | {e!r} | ALERT cleared for all nodes")
        empty = _empty_alert()
        return {n: empty for n in node_list} if node_list else {"": empty}

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
    wx_use_gps = _config_flag_yes(config.get("general", "WX_USE_GPS", fallback="no"))
    wx_use_gps = resolve_weather_gps(wx_use_gps, wx_code)
    temp_unit = config.get("general", "TEMP_UNIT", fallback="F")

    # API source selection:
    # - SkywarnPlus-NG: [skywarnplus] MASTER_ENABLE / API_URL
    # - CANWarn-NG:     [canwarn_ng] MASTER_ENABLE / API_URL
    #
    # Selection precedence:
    #  1) If [general] ALERT_PROVIDER is set, use that provider.
    #  2) Otherwise (back-compat), prefer [canwarn_ng] when present; else fall back to [skywarnplus].
    #
    # Optional [general] ALERT_PRODUCT = "CANWarn-NG"|"SkywarnPlus-NG" controls the label in the dashboard.
    # ALERT_PRODUCT is optional and (historically) controlled the label shown on the dashboard.
    # When ALERT_PROVIDER is explicitly set, we prefer to match the selected provider's branding.
    product_name = config.get("general", "ALERT_PRODUCT", fallback="").strip()
    provider = config.get("general", "ALERT_PROVIDER", fallback="").strip().lower()

    if provider in ("canwarn", "canwarn-ng", "canwarn_ng"):
        master_enable = config.get("canwarn_ng", "MASTER_ENABLE", fallback="no").strip()
        api_url = config.get("canwarn_ng", "API_URL", fallback="http://localhost:8110").strip()
        product_name = "CANWarn-NG"
    elif provider in ("skywarnplus", "skywarnplus-ng", "skywarnplus_ng"):
        master_enable = config.get("skywarnplus", "MASTER_ENABLE", fallback="no").strip()
        api_url = config.get("skywarnplus", "API_URL", fallback="http://localhost:8100").strip()
        product_name = "SkywarnPlus-NG"
    else:
        # Backward-compatible auto-detect
        if config.has_section("canwarn_ng"):
            master_enable = config.get("canwarn_ng", "MASTER_ENABLE", fallback="no").strip()
            api_url = config.get("canwarn_ng", "API_URL", fallback="http://localhost:8110").strip()
            if not product_name:
                product_name = "CANWarn-NG"
        else:
            master_enable = config.get("skywarnplus", "MASTER_ENABLE", fallback="no").strip()
            api_url = config.get("skywarnplus", "API_URL", fallback="http://localhost:8100").strip()
            if not product_name:
                product_name = "SkywarnPlus-NG"

    print(f"[NodeStatus] ALERT_PRODUCT={product_name!r} API_URL={api_url!r} MASTER_ENABLE={master_enable!r} NODES={nodes}")

    cpu_up = get_uptime()
    cpu_load = get_cpu_load()
    cpu_temp_dsp = get_cpu_temperature(temp_unit)
    if wx_use_gps:
        print("[NodeStatus] WX_USE_GPS=yes or weather.ini location_source=gps")
    wx = get_weather(wx_code, wx_location, use_gps=wx_use_gps)
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
    alerts_map = get_alerts_from_api(api_url, master_enable, nodes=node_list, product_name=product_name)
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

