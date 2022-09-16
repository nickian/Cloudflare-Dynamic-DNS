<?php

$cli->yellow('data.json not found. Creating...');

/**
 * Determine current public IP address
 */
$ip_address = $dynamic_dns->getPublicIp();

if ( !$ip_address ) {
    $cli->red('There was a problem determining your public IP address.');
    exit();
}

/**
 * Define the zone name
 */
$cli->magenta('Your zone name is the root domain that we are managing DNS records for (e.g., mysite.com).');
$input = $cli->input('Enter zone name to use:');
$zone_name = $input->prompt();

/**
 * Get the ID of the zone entered.
 */
$zone_id = $dynamic_dns->findZoneId($zone_name);

if ( !$zone_id ) {
    $cli->red('Zone not found. Make sure your API key has access to this zone.');
    exit();
} else {
    $cli->green('Zone "'.$zone_name.'" found.');
}

/**
 * Define the record name
 */
$cli->magenta('What name would you like to use or create for the A record entry? (e.g., home.mymdomain.com)');
$input = $cli->input('Record Name:');
$record_name = $input->prompt();

/**
 * Check if the record exists, get ID, or create it and return ID.
 */
$record_id = $dynamic_dns->findRecordId($record_name, $zone_id);

// "A" record does not exist, try creating it.
if ( !$record_id ) {

    $cli->yellow('Unable to find existing record. Attempting to create record...');

    $create_record = $dynamic_dns->createARecord($zone_id, $record_name, $ip_address);

    if ( isset($create_record['success']) ) {
        $record_id = $create_record['result']['id'];
    } else {
        $cli->red('Unable to create "A" record.');
        exit();
    }

// "A" record does exit. Get the value.
} else {

    $record_value = $dynamic_dns->getARecordValue($zone_id, $record_id);

    if ( !$record_value ) {
        $cli->red('Unable to get DNS record value from Cloudflare.');
        exit();
    }

    if ( $ip_address == $record_value ) {
        $cli->green('Current IP matches existing DNS record.');
    } else {
        $cli->yellow('Current IP does not match existing DNS record...');
        $update_record = $dynamic_dns->updateARecord($zone_id, $record_id, $record_name, $ip_address);
        if ( $update_record ) {
            $cli->green('DNS record for '.$record_name.' updated to '.$ip_address);
        } else {
            $cli->red('Unable to update DNS record.');
        }
    }

}

/**
 * Create the data JSON file.
 */
$data = $dynamic_dns->createData([
    'ip_address' => $ip_address,
    'zone_name' => $zone_name,
    'zone_id' => $zone_id,
    'record_name' => $record_name,
    'record_id' => $record_id
]);

if ( $data ) {
    $cli->green('JSON file created.');
} else {
    $cli->red('There was an error creating the JSON file.');
    exit();
}