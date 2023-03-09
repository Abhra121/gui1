<?php
    echo shell_exec('nmcli -f SSID device wifi list');
    
	function nearbyWifiStations(&$networks, $cached = true)
{
    $cacheTime = filemtime(RASPI_WPA_SUPPLICANT_CONFIG);
    $cacheKey  = "nearby_wifi_stations_$cacheTime";

    if ($cached == false) {
        deleteCache($cacheKey);
    }

    $scan_results = cache(
        $cacheKey, function () {
            exec('sudo wpa_cli -i ' .$_SESSION['wifi_client_interface']. ' scan');
            sleep(3);

            $stdout = shell_exec('sudo wpa_cli -i ' .$_SESSION['wifi_client_interface']. ' scan_results');
            return preg_split("/\n/", $stdout);
        }
    );
    // get the name of the AP. Should be excluded from nearby networks
    exec('cat '.RASPI_HOSTAPD_CONFIG.' | sed -rn "s/ssid=(.*)\s*$/\1/p" ', $ap_ssid);
    $ap_ssid = $ap_ssid[0];

    $index = 0;
    if ( !empty($networks) ) {
        $lastnet = end($networks);
        if ( isset($lastnet['index']) ) $index = $lastnet['index'] + 1;
    }

    array_shift($scan_results);
    foreach ($scan_results as $network) {
        $arrNetwork = preg_split("/[\t]+/", $network);  // split result into array
        $ssid = $arrNetwork[4];

        // exclude raspap ssid
        if (empty($ssid) || $ssid == $ap_ssid) {
            continue;
        }

        // filter SSID string: unprintable 7bit ASCII control codes, delete or quotes -> ignore network
        if (preg_match('[\x00-\x1f\x7f\'\`\´\"]', $ssid)) {
            continue;
        }

        // If network is saved
        if (array_key_exists($ssid, $networks)) {
            $networks[$ssid]['visible'] = true;
            $networks[$ssid]['channel'] = ConvertToChannel($arrNetwork[1]);
            // TODO What if the security has changed?
        } else {
            $networks[$ssid] = array(
                'ssid' => $ssid,
                'configured' => false,
                'protocol' => ConvertToSecurity($arrNetwork[3]),
                'channel' => ConvertToChannel($arrNetwork[1]),
                'passphrase' => '',
                'visible' => true,
                'connected' => false,
                'index' => $index
            );
            ++$index;
        }

        // Save RSSI, if the current value is larger than the already stored
        if (array_key_exists(4, $arrNetwork) && array_key_exists($arrNetwork[4], $networks)) {
            if (! array_key_exists('RSSI', $networks[$arrNetwork[4]]) || $networks[$ssid]['RSSI'] < $arrNetwork[2]) {
                $networks[$ssid]['RSSI'] = $arrNetwork[2];
            }
        }
    }
}
?>
