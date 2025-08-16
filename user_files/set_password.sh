#!/bin/bash

# Supermon2 Password Management Script for ASL3+
# Author: Jory A. Pratt, W5GLE
# Date: 5/20252

# Path to the password file
PASSWORD_FILE_DIR="$(dirname "$0")"
PASSWORD_FILE="$PASSWORD_FILE_DIR/.htpasswd"

# === FUNCTIONS ===

# Blocked usernames for security
BLOCKED_USERNAMES=(
    "admin"
    "administrator"
    "root"
    "superuser"
    "supervisor"
    "master"
    "owner"
    "system"
    "user"
    "username"
    "test"
    "demo"
    "guest"
    "anonymous"
    "nobody"
    "default"
    "webmaster"
    "webadmin"
    "sysadmin"
    "operator"
    "manager"
    "supermon"
    "allstar"
    "asterisk"
    "ham"
    "radio"
    "repeater"
    "node"
    "hub"
    "server"
    "host"
    "localhost"
    "127.0.0.1"
    "localhost"
    "0"
    "1"
    "2"
    "3"
    "4"
    "5"
    "6"
    "7"
    "8"
    "9"
    "10"
    "11"
    "12"
    "13"
    "14"
    "15"
    "16"
    "17"
    "18"
    "19"
    "20"
    "21"
    "22"
    "23"
    "24"
    "25"
    "26"
    "27"
    "28"
    "29"
    "30"
    "31"
    "32"
    "33"
    "34"
    "35"
    "36"
    "37"
    "38"
    "39"
    "40"
    "41"
    "42"
    "43"
    "44"
    "45"
    "46"
    "47"
    "48"
    "49"
    "50"
    "51"
    "52"
    "53"
    "54"
    "55"
    "56"
    "57"
    "58"
    "59"
    "60"
    "61"
    "62"
    "63"
    "64"
    "65"
    "66"
    "67"
    "68"
    "69"
    "70"
    "71"
    "72"
    "73"
    "74"
    "75"
    "76"
    "77"
    "78"
    "79"
    "80"
    "81"
    "82"
    "83"
    "84"
    "85"
    "86"
    "87"
    "88"
    "89"
    "90"
    "91"
    "92"
    "93"
    "94"
    "95"
    "96"
    "97"
    "98"
    "99"
    "100"
)

validate_username() {
    local username="$1"
    
    # Check if username is empty
    if [[ -z "$username" ]]; then
        echo "ERROR: Username cannot be empty."
        return 1
    fi
    
    # Check if username contains only whitespace
    if [[ "$username" =~ ^[[:space:]]+$ ]]; then
        echo "ERROR: Username cannot contain only whitespace."
        return 1
    fi
    
    # Check if username is too short (less than 3 characters)
    if [[ ${#username} -lt 3 ]]; then
        echo "ERROR: Username must be at least 3 characters long."
        return 1
    fi
    
    # Check if username is too long (more than 20 characters)
    if [[ ${#username} -gt 20 ]]; then
        echo "ERROR: Username must be 20 characters or less."
        return 1
    fi
    
    # Check if username contains invalid characters (only allow alphanumeric, underscore, hyphen)
    if [[ ! "$username" =~ ^[a-zA-Z0-9_-]+$ ]]; then
        echo "ERROR: Username can only contain letters, numbers, underscores, and hyphens."
        return 1
    fi
    
    # Check if username starts with a number
    if [[ "$username" =~ ^[0-9] ]]; then
        echo "ERROR: Username cannot start with a number."
        return 1
    fi
    
    # Check if username is in the blocked list
    for blocked in "${BLOCKED_USERNAMES[@]}"; do
        if [[ "${username,,}" == "${blocked,,}" ]]; then
            echo "ERROR: Username '$username' is not allowed for security reasons."
            echo "Please choose a different username."
            return 1
        fi
    done
    
    # Check if username contains common patterns that might be weak
    if [[ "${username,,}" =~ (admin|root|user|test|demo|guest|anonymous|nobody|default|master|owner|system|super|web|host|server|node|hub|radio|ham|repeater|asterisk|allstar|supermon) ]]; then
        echo "WARNING: Username '$username' contains common patterns that may be weak."
        echo "Consider using a more unique username for better security."
        read -p "Do you want to continue with this username? [y/N]: " continue_choice
        case $continue_choice in
            [Yy]* ) return 0 ;;
            * ) return 1 ;;
        esac
    fi
    
    return 0
}

confirm_action() {
    echo
    read -r -p "${1:-Are you sure? [y/N]} " response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

pause_for_user() {
    echo
    read -n 1 -s -r -p "Press any key to continue..."
    echo
}

display_password_file() {
    echo
    if [[ -f "$PASSWORD_FILE" ]]; then
        if [[ -s "$PASSWORD_FILE" ]]; then
            echo -e "\nCurrent contents of the password file ($PASSWORD_FILE):\n"
            cat "$PASSWORD_FILE"
        else
            echo -e "\nPassword file exists but is empty: $PASSWORD_FILE\n"
        fi
    else
        echo -e "\nNo password file found at $PASSWORD_FILE\n"
    fi
}

create_password_file() {
    if [[ ! -f "$PASSWORD_FILE" ]]; then
        echo
        read -p "Would you like to create a new password file? [y/n]: " choice
        case $choice in
            [Yy]* )
                while true; do
                    read -p "Enter a username for the new password: " username
                    if validate_username "$username"; then
                        htpasswd -cB "$PASSWORD_FILE" "$username"
                        echo -e "\nPassword file created with user: $username"
                        break
                    fi
                done
                ;;
            [Nn]* )
                echo -e "\nNo password file created. Exiting."
                exit
                ;;
            * )
                echo -e "\nPlease answer [y]es or [n]o."
                create_password_file
                ;;
        esac
    fi
}

delete_password_file() {
    if [[ -f "$PASSWORD_FILE" ]]; then
        echo
        read -p "Do you want to delete the existing password file? [y/n]: " choice
        case $choice in
            [Yy]* )
                if confirm_action "Are you sure you want to delete $PASSWORD_FILE? [y/N]: "; then
                    rm "$PASSWORD_FILE"
                    echo -e "\nPassword file deleted.\n"
                else
                    echo -e "\nDeletion canceled. Password file retained.\n"
                fi
                ;;
            [Nn]* ) echo -e "\nPassword file retained.\n" ;;
            * ) echo -e "\nPlease answer [y]es or [n]o."; delete_password_file ;;
        esac
    fi
}

manage_user() {
    echo
    while true; do
        read -p "Enter the username to add, delete, or change password: " username
        if validate_username "$username"; then
            break
        fi
    done
    
    if grep -qs "^$username:" "$PASSWORD_FILE"; then
        echo
        read -p "User '$username' exists. [D]elete or [C]hange password? " action
        case $action in
            [Dd]* )
                htpasswd -D "$PASSWORD_FILE" "$username" && echo "User deleted."
                ;;
            [Cc]* )
                htpasswd -B "$PASSWORD_FILE" "$username" && echo "Password changed."
                ;;
            * )
                echo "Invalid choice. Please enter 'D' to delete or 'C' to change."
                ;;
        esac
    else
        echo
        read -p "User '$username' not found. Would you like to create it? [y/n]: " create_choice
        case $create_choice in
            [Yy]* )
                htpasswd -B "$PASSWORD_FILE" "$username" && echo "User '$username' created."
                ;;
            [Nn]* )
                echo "No changes made."
                ;;
            * )
                echo "Invalid choice."
                ;;
        esac
    fi
    pause_for_user
}

# === SCRIPT START ===

clear
cat << EOF
+=========================================================+
|       Supermon-ng Password File Management Utility        |
|---------------------------------------------------------|
|  Create, view, update, or delete .htpasswd entries.     |
|  Password file is now located at user_files/.htpasswd   |
|  (relative to the project root).                        |
|  Useful for managing Supermon-ng web access.            |
|                                                         |
|  SECURITY: Common usernames like 'admin', 'root', etc.  |
|  are blocked for security reasons.                      |
+=========================================================+
EOF

while true; do
    display_password_file
    echo
    read -p "Do you want to manage the password file? [y/n]: " proceed
    case $proceed in
        [Yy]* )
            delete_password_file
            create_password_file
            ;;
        [Nn]* )
            echo -e "\nExiting. No changes made.\n"
            exit
            ;;
        * )
            echo "Please answer [y]es or [n]o."
            continue
            ;;
    esac

    while true; do
        echo
        read -p "Would you like to add, delete, or change a user? [y/n]: " user_action
        case $user_action in
            [Yy]* ) manage_user ;;
            [Nn]* ) echo -e "\nExiting. No further changes made.\n"; exit ;;
            * ) echo "Please answer [y]es or [n]o." ;;
        esac
    done
done
