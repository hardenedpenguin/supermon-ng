<?php
/**
 * Link.php Helper Functions
 * 
 * This file contains helper functions extracted from link.php
 * for better organization and maintainability.
 */

/**
 * Print authorization button if user has permission
 * @param string $auth_perm Permission to check
 * @param string $css_class CSS class for button
 * @param string $value Button value/text
 * @param string $id Button ID (optional)
 * @param string $extra_attrs Extra HTML attributes (optional)
 * @param string $onclick_action JavaScript onclick action (optional)
 */
function print_auth_button($auth_perm, $css_class, $value, $id = "", $extra_attrs = "", $onclick_action = "") {
    if (get_user_auth($auth_perm)) {
        $id_attr = $id ? "id=\"$id\"" : "";
        $onclick_attr = $onclick_action ? "OnClick=\"$onclick_action\"" : "";
        $extra_attrs_spaced = ($extra_attrs && substr($extra_attrs, 0, 1) !== ' ') ? " " . $extra_attrs : $extra_attrs;
        print "<input type=\"button\" class=\"$css_class\" value=\"$value\" $id_attr{$extra_attrs_spaced} $onclick_attr>";
    }
}

/**
 * Check if an IP address is internal/private
 * @param string $ip The IP address to check
 * @return bool True if the IP is internal, false otherwise
 */
function is_internal_ip($ip) {
    // The loopback address is always internal.
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    
    // Use PHP's built-in filter to check if the IP is NOT a public IP.
    // The FILTER_FLAG_NO_PRIV_RANGE and FILTER_FLAG_NO_RES_RANGE flags cause filter_var to return false
    // for private or reserved IPs. We return the opposite (!).
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
?>
