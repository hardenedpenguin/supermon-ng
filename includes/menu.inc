<?php

include_once("authini.php");

$current_url = urldecode(basename($_SERVER['REQUEST_URI']));

$SUPINI = get_ini_name($_SESSION['user']);

if (!file_exists($SUPINI)) {
    die("Couldn't load ini file: $SUPINI");
}
$config = parse_ini_file($SUPINI, true);

if (empty($config)) {
    die("Check ini file $SUPINI format or ensure it's not empty.");
}

$systems = array();
foreach ($config as $name => $data) {
    if (!isset($data['menu']) || $data['menu'] !== "1") {
        continue;
    }

    if (strtolower($name) == 'break') {
        continue;
    }

    $sysName = isset($data['system']) ? $data['system'] : 'MainNavBar';

    $url = '';
    if (isset($data['url'])) {
        $url = $data['url'];
    } elseif (isset($data['rtcmnode'])) {
        $url = "voter.php?node={$data['rtcmnode']}";
    } elseif (isset($data['nodes'])) {
        $url = "link.php?nodes={$data['nodes']}";
    } else {
        $url = "link.php?nodes=$name";
    }
    $systems[$sysName][$name]['url'] = $url;
}

$MENU_FONT = ($Show_Detail == "1") ? "14px" : "22px";
$MENU_PADDING = ($Show_Detail == "1") ? "4px 2px 2px 4px;" : "12px 4px 12px 4px;";

$outBuf = "<div id=\"menu\">\n";
$outBuf .= "<ul style=\"font-size:$MENU_FONT;\">\n";

foreach ($systems as $sysName => $items) {
    if ($sysName == "MainNavBar") {
        $outBuf .= "<li>";
        foreach ($items as $itemName => $itemValue) {
            $raw_url = $itemValue['url'];
            $href = $raw_url;
            $target_attr = '';
            $class_attr = '';

            $is_active = ($current_url == $raw_url);

            if ($is_active) {
                $class_attr = ' class="active"';
                if (substr($href, -1) == '>') {
                    $href = substr($href, 0, -1);
                }
            } else {
                $can_have_target_blank = !(is_numeric($itemName));

                if ($can_have_target_blank && substr($raw_url, -1) == '>') {
                    $href = substr($raw_url, 0, -1);
                    $target_attr = ' target="_blank"';
                }
            }
            if (substr($href, -1) == '>') {
                $href = substr($href, 0, -1);
            }

            $outBuf .= " <a{$class_attr} href=\"" . htmlspecialchars($href) . "\"{$target_attr}>" . htmlspecialchars($itemName) . "</a>";
        }
        $outBuf .= "</li>\n";
    } else {
        $outBuf .= "<li class=\"dropdown\">\n";
        $outBuf .= "<a href=\"#\" class=\"dropbtn\">" . htmlspecialchars($sysName) . "</a>\n";
        $outBuf .= "<div class=\"dropdown-content\">\n";
        foreach ($items as $itemName => $itemValue) {
            $raw_url = $itemValue['url'];
            $href = $raw_url;
            $target_attr = '';
            $class_attr = '';
            $style_attr = " style=\"padding:$MENU_PADDING\"";

            $is_active = ($current_url == $raw_url);

            if ($is_active) {
                $class_attr = ' class="active"';
                if (substr($href, -1) == '>') {
                    $href = substr($href, 0, -1);
                }
            } else {
                if (substr($raw_url, -1) == '>') {
                    $href = substr($raw_url, 0, -1);
                    $target_attr = ' target="_blank"';
                }
            }
            if (substr($href, -1) == '>') {
                $href = substr($href, 0, -1);
            }

            $outBuf .= " <a{$class_attr}{$style_attr} href=\"" . htmlspecialchars($href) . "\"{$target_attr}>" . htmlspecialchars($itemName) . "</a>\n";
        }
        $outBuf .= "</div>\n</li>\n";
    }
}

$outBuf .= "</ul>\n</div>\n";
$outBuf .= "<div class=\"clearer\"></div>\n";
print $outBuf;

?>