#!/bin/bash

# Supermon2 Password Management Script for ASL3+
# Author: Jory A. Pratt, W5GLE
# Date: 5/20252

# Path to the password file
PASSWORD_FILE_DIR="/var/www/html/supermon-ng"
PASSWORD_FILE="$PASSWORD_FILE_DIR/.htpasswd"

# === FUNCTIONS ===

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
                read -p "Enter a username for the new password: " username
                htpasswd -cB "$PASSWORD_FILE" "$username"
                echo -e "\nPassword file created with user: $username"
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
    read -p "Enter the username to add, delete, or change password: " username
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
|  Useful for managing Supermon-ng web access.              |
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
