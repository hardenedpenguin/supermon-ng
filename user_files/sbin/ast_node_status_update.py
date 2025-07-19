#!/usr/bin/env python3

import os
import subprocess
import re
import configparser

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
            return f'"<b>{wx_location}   ({wx_raw})</b>"'
    elif os.access("/usr/local/sbin/weather.sh", os.X_OK):
        wx_raw = run_command(f"/usr/local/sbin/weather.sh \"{wx_code}\" v")
        if wx_raw:
            return f'"<b>{wx_location}   ({wx_raw})</b>"'
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

def get_autosky_alerts(alert_ini, warnings_file, master_enable, custom_link=""):
    github_link = '<a href=\'https://github.com/mason10198/SkywarnPlus\' style=\'color: inherit; text-decoration: none;\'>SkywarnPlus</a>'
    enabled_text = f'<span style=\'color: SpringGreen;\'><b><u>{github_link} Enabled</u></b></span>'
    disabled_text = f'<span style=\'color: darkorange;\'><b><u>{github_link} Disabled</u></b></span>'
    no_alerts_text = '<span style=\'color: #FF0000;\'>No Alerts</span>'

    if master_enable.lower() != "yes":
        return f'"{disabled_text}"'

    if warnings_file and not os.path.exists(warnings_file):
        print(f"Warning: warnings_file '{warnings_file}' not found. Create it as root: touch {warnings_file}")
        return '" "'

    if os.path.exists(warnings_file):
        alert_url_command = f"grep '^OFILE=' \"{alert_ini}\" | sed 's/OFILE=//' | sed 's/&y=0/&y=1/' | sed 's/\"//g'"
        alert_url = run_command(alert_url_command)
        
        # Use custom_link if AUTOSKY alert_url is not available
        if not alert_url and custom_link:
            alert_url = custom_link
            
        alert_url_link = f"<a target='WX ALERT' href='{alert_url}' style='color: inherit;'>" if alert_url else ""
        alert_url_link_end = "</a>" if alert_url else ""
        alert_content = ""
        try:
            with open(warnings_file, 'r') as f:
                alert_content = f.read().strip()
        except FileNotFoundError:
            return f'"{enabled_text}<br>{no_alerts_text}"'

        if not alert_content:
            return f'"{enabled_text}<br>{no_alerts_text}"'
        else:
            alert_content_cleaned = alert_content.replace("[", "").replace("]", "")
            return f'"{enabled_text}<br><span style=\'color: #FF0000;\'>{alert_url_link}<b>{alert_content_cleaned}</b>{alert_url_link_end}</span>"'
    else:
        return '" "'

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

        command_alert = [
            "/usr/sbin/asterisk",
            "-rx",
            f"rpt set variable {node} ALERT={alert}"
        ]
        result_alert = subprocess.run(command_alert, capture_output=True, text=True, check=False)
        if result_alert.returncode != 0:
            print(f"Error setting ALERT for node {node}: {result_alert.stderr}")
        else:
            print(f"Updated ALERT Node {node} using rpt set variable")
    else:
        if process_check.returncode == 1:
             print(f"Invalid Node {node}: not found in /etc/asterisk/rpt.conf")
        else:
             print(f"Error checking node {node} in /etc/asterisk/rpt.conf: {process_check.stderr if process_check.stderr else 'Unknown error'}")


if __name__ == "__main__":
    script_dir = os.path.dirname(os.path.realpath(__file__))
    config_file = os.path.join(script_dir, "node_info.ini")
    config = configparser.ConfigParser()

    if not os.path.exists(config_file):
        print(f"Error: Configuration file '{config_file}' not found.")
        exit(1)

    config.read(config_file)

    nodes = config.get("general", "NODE", fallback="").split()
    wx_code = config.get("general", "WX_CODE", fallback="")
    wx_location = config.get("general", "WX_LOCATION", fallback="")
    temp_unit = config.get("general", "TEMP_UNIT", fallback="F")

    master_enable = config.get("autosky", "MASTER_ENABLE", fallback="no")
    alert_ini = config.get("autosky", "ALERT_INI", fallback="/usr/local/bin/AUTOSKY/AutoSky.ini")
    warnings_file = config.get("autosky", "WARNINGS_FILE", fallback="/var/www/html/AUTOSKY/warnings.txt")
    custom_link = config.get("autosky", "CUSTOM_LINK", fallback="")

    cpu_up = get_uptime()
    cpu_load = get_cpu_load()
    cpu_temp_dsp = get_cpu_temperature(temp_unit)
    wx = get_weather(wx_code, wx_location)
    disk_usage_info = get_disk_usage()
    alert = get_autosky_alerts(alert_ini, warnings_file, master_enable, custom_link)

    if nodes:
        for node in nodes:
            if node.strip():
                update_node_variables(node.strip(), cpu_up, cpu_load, cpu_temp_dsp, wx, disk_usage_info, alert)
    else:
        print("No nodes specified in the configuration file.")

    exit(0)