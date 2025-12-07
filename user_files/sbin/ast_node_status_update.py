#!/usr/bin/env python3

import os
import subprocess
import re
import configparser
import requests
import json

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
    elif os.access("/usr/sbin/weather.pl", os.X_OK):
        wx_raw = run_command(f"/usr/sbin/weather.pl \"{wx_code}\" v")
        if wx_raw:
            return f'"<b>{wx_location}   ({wx_raw})</b>"'
    elif os.access("/usr/local/sbin/weather.sh", os.X_OK):
        wx_raw = run_command(f"/usr/local/sbin/weather.sh \"{wx_code}\" v")
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

def get_skywarnplus_alerts(api_url, master_enable, custom_link=""):
    """
    Get alerts from SkywarnPlus-ng API
    
    Args:
        api_url: Base URL for SkywarnPlus-ng API (e.g., http://10.0.0.5:8100)
        master_enable: "yes" to enable, anything else to disable
        custom_link: Optional custom link for alerts (used as display link only, not for fetching)
    
    Returns:
        Formatted HTML string for display
    """
    github_link = '<a href=\'https://github.com/hardenedpenguin/SkywarnPlus-NG\' style=\'color: inherit; text-decoration: none;\'>SkywarnPlus-NG</a>'
    enabled_text = f'<span style=\'color: SpringGreen;\'><b><u>{github_link} Enabled</u></b></span>'
    disabled_text = f'<span style=\'color: darkorange;\'><b><u>{github_link} Disabled</u></b></span>'
    no_alerts_text = '<span style=\'color: #FF0000;\'>No Alerts</span>'
    error_text = '<span style=\'color: #FF0000;\'>API Error</span>'

    if master_enable.lower() != "yes":
        return f'"{disabled_text}"'

    try:
        status_url = f"{api_url}/api/status"
        response = requests.get(status_url, timeout=5)
        
        if response.status_code != 200:
            print(f"Error: SkywarnPlus-ng API returned status code {response.status_code}")
            return f'"{enabled_text}<br>{error_text}"'
        
        data = response.json()
        
        if not data.get('has_alerts', False):
            return f'"{enabled_text}<br>{no_alerts_text}"'
        
        alerts = data.get('alerts', [])
        alert_display = []
        
        for alert in alerts[:3]:  # Limit to 3 most recent alerts
            event = alert.get('event', 'Unknown')
            severity = alert.get('severity', 'Unknown')
            headline = alert.get('headline', 'No headline')
            
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
            
            alert_text = f"{event}"
            
            if custom_link:
                alert_display.append(f"<a target='WX ALERT' href='{custom_link}' style='color: {color}; text-decoration: none;'><b>{alert_text}</b></a>")
            else:
                alert_display.append(f"<span style='color: {color};'><b>{alert_text}</b></span>")
        
        alerts_html = "<br>".join(alert_display)
        
        return f'"{enabled_text}<br>{alerts_html}"'
        
    except requests.exceptions.Timeout:
        print("Error: SkywarnPlus-ng API request timed out")
        return f'"{enabled_text}<br><span style=\'color: #FF6600;\'>API Timeout</span>"'
    except requests.exceptions.ConnectionError as e:
        import traceback
        from datetime import datetime
        error_msg = f"Error: Could not connect to SkywarnPlus-ng API - URL: {status_url}, Error: {e}"
        print(error_msg)
        try:
            with open("/tmp/skywarn_api_errors.log", "a") as log_file:
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                log_file.write(f"[{timestamp}] {error_msg}\n")
                traceback.print_exc(file=log_file)
        except:
            pass
        traceback.print_exc()
        return f'"{enabled_text}<br><span style=\'color: #FF6600;\'>API Offline</span>"'
    except Exception as e:
        print(f"Error getting alerts from SkywarnPlus-ng: {e}")
        return f'"{enabled_text}<br>{error_text}"'

def update_node_variables(node, cpu_up, cpu_load, cpu_temp_dsp, wx, disk_usage, alert):
    check_node_command = f"grep -q '[[:blank:]]*\\[{node}\\]' /etc/asterisk/rpt.conf"
    process_check = subprocess.run(check_node_command, shell=True, capture_output=True, text=True)
    
    if process_check.returncode == 0:
        command = [
            "/usr/sbin/asterisk",
            "-rx",
            f"rpt set variable {node} cpu_up=\"{cpu_up}\" cpu_load={cpu_load} cpu_temp={cpu_temp_dsp} WX={wx} DISK={disk_usage}"
        ]
        result = subprocess.run(command, capture_output=True, text=True, check=False)
        if result.returncode != 0:
            print(f"Error setting variables for node {node}: {result.stderr}")
        else:
            print(f"Updated Variables Node {node} using rpt set variable")

        import tempfile
        with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False, encoding='utf-8') as tmp_alert_file:
            tmp_alert_file.write(alert)
            alert_file_path = tmp_alert_file.name
        
        with tempfile.NamedTemporaryFile(mode='w', suffix='.sh', delete=False) as tmp_script:
            tmp_script.write(f'''#!/bin/bash
ALERT_VALUE=$(cat "{alert_file_path}")
/usr/sbin/asterisk -rx "rpt set variable {node} ALERT=\\"$ALERT_VALUE\\""
rm -f "{alert_file_path}"
''')
            script_path = tmp_script.name
        
        try:
            os.chmod(script_path, 0o755)
            result_alert = subprocess.run(['bash', script_path], capture_output=True, text=True, check=False)
        finally:
            try:
                os.unlink(script_path)
            except:
                pass
        if result_alert.returncode != 0:
            print(f"Error setting ALERT for node {node}: return code {result_alert.returncode}")
            if result_alert.stderr:
                print(f"Stderr: {result_alert.stderr[:200]}")
            if result_alert.stdout:
                print(f"Stdout: {result_alert.stdout[:200]}")
        else:
            print(f"Updated ALERT Node {node} using rpt set variable")
            if result_alert.stdout:
                print(f"DEBUG: Asterisk stdout: {result_alert.stdout[:100]}")
            if result_alert.stderr:
                print(f"DEBUG: Asterisk stderr: {result_alert.stderr[:100]}")
    else:
        if process_check.returncode == 1:
             print(f"Invalid Node {node}: not found in /etc/asterisk/rpt.conf")
        else:
             print(f"Error checking node {node} in /etc/asterisk/rpt.conf: {process_check.stderr if process_check.stderr else 'Unknown error'}")


if __name__ == "__main__":
    script_dir = os.path.dirname(os.path.realpath(__file__))
    config_file = os.path.join(script_dir, "node_info.ini")

    if not os.path.exists(config_file):
        print(f"Error: Configuration file '{config_file}' not found.")
        exit(1)

    # Skip CUSTOM_LINK_HEADERS lines (ConfigParser doesn't support duplicate keys)
    cleaned_config_lines = []
    try:
        with open(config_file, 'r') as f:
            lines = f.readlines()
            in_skywarnplus = False
            for line in lines:
                stripped_line = line.strip()
                if stripped_line == '[skywarnplus]':
                    in_skywarnplus = True
                    cleaned_config_lines.append(line)
                    continue
                if stripped_line.startswith('[') and stripped_line != '[skywarnplus]':
                    in_skywarnplus = False
                    cleaned_config_lines.append(line)
                    continue
                if in_skywarnplus and stripped_line.startswith('CUSTOM_LINK_HEADERS'):
                    continue
                cleaned_config_lines.append(line)
    except Exception as e:
        print(f"Warning: Could not parse config file: {e}")
        with open(config_file, 'r') as f:
            cleaned_config_lines = f.readlines()

    config = configparser.ConfigParser()
    import io
    config = configparser.ConfigParser()
    config.read_string(''.join(cleaned_config_lines))

    nodes = config.get("general", "NODE", fallback="").split()
    wx_code = config.get("general", "WX_CODE", fallback="")
    wx_location = config.get("general", "WX_LOCATION", fallback="")
    temp_unit = config.get("general", "TEMP_UNIT", fallback="F")

    master_enable = config.get("skywarnplus", "MASTER_ENABLE", fallback="no")
    api_url = config.get("skywarnplus", "API_URL", fallback="http://localhost:8100")
    custom_link = config.get("skywarnplus", "CUSTOM_LINK", fallback="")

    cpu_up = get_uptime()
    cpu_load = get_cpu_load()
    cpu_temp_dsp = get_cpu_temperature(temp_unit)
    wx = get_weather(wx_code, wx_location)
    disk_usage_info = get_disk_usage()
    alert = get_skywarnplus_alerts(api_url, master_enable, custom_link)
    if alert.startswith('"') and alert.endswith('"'):
        alert = alert[1:-1]

    if nodes:
        for node in nodes:
            if node.strip():
                update_node_variables(node.strip(), cpu_up, cpu_load, cpu_temp_dsp, wx, disk_usage_info, alert)
    else:
        print("No nodes specified in the configuration file.")

    exit(0)

