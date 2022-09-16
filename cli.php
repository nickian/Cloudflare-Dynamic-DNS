<?php
require_once('vendor/autoload.php');
require_once('config.php');
require_once('CloudflareDynamicDNS.php');

$cli = new \League\CLImate\CLImate;

/**
 * Check config.php for token.
 */
if ( !TOKEN ) {
    $cli->red('TOKEN not found. Set your TOKEN value in the config.php file.');
    exit();
}

$dynamic_dns = new \CloudflareDynamicDNS\DynamicDNS(TOKEN);

/**
 * Check that the token is active.
 */
if ( !$dynamic_dns->validToken(TOKEN) ) {
    if ( $dynamic_dns->error ) {
        $cli->red('Error: '.$dynamic_dns->error);
    }
    $cli->red('TOKEN is invalid or expired. Update the value in config.php.');
    exit();
}

/**
 * If JSON file doesn't exist, create it.
 */
if ( !$dynamic_dns->dataExists(DATA_FILE) ) {
    require_once('create_file.php');
}

/**
 * Read contents of JSON file into an array.
 */
$data = $dynamic_dns->readData(DATA_FILE);

if ( !$data ) {
    $cli->red('Unable to read the JSON file.');
    exit();
}

/**
 * Validate the contents of the JSON file.
 */
if ( 
    (!isset($data['ip_address']) || !filter_var($data['ip_address'], FILTER_VALIDATE_IP)) ||
    !isset($data['zone_id']) || !$data['zone_id'] || 
    !isset($data['zone_name']) || !$data['zone_name'] || 
    !isset($data['record_id']) || !$data['record_id'] || 
    !isset($data['record_name']) || !$data['record_name']
) {
    $dynamic_dns->removeData();
    $cli->red('JSON file found, but it doesn\'t contain the required data.');
    $cli->red('Run the script again to create a new JSON file.');
    exit();
}

/**
 * Check that the IP address in data file matches current IP.
 */
$current_ip = $dynamic_dns->getPublicIp();

// The IP has changed.
if ( $current_ip != $data['ip_address']) {
    
    $cli->yellow('IP has changed. Updating DNS record...');
    
    // Update the DNS A Record.
    $update_record = $dynamic_dns->updateARecord($data['zone_id'], $data['record_id'], $data['record_name'], $current_ip);
    
    // Record was updated.
    if ( $update_record ) {

        // Recreate the JSON file with the new IP address.
        $data = $dynamic_dns->createData([
            'ip_address' => $current_ip,
            'zone_name' => $data['zone_name'],
            'zone_id' => $data['zone_id'],
            'record_name' => $data['record_name'],
            'record_id' => $data['record_id']
        ]);

        if ( $data ) {
            $cli->green('JSON file updated.');
        } else {
            $cli->red('There was an error updating the JSON file.');
            exit();
        }

        $cli->green('Updated DNS record value for '.$update_record['result']['name'].' to '.$update_record['result']['content'].'.');

        // Send email notification, if enabled
        if ( EMAIL_NOTIFICATIONS ) {
            if ( HTML_EMAIL ) {
                $template = 'updated_success_html.php';
            } else {
                $template = 'updated_success_txt.php';
            }
            $subject = 'IP has changed. DNS record updated.';
            $dynamic_dns->sendEmailNotification(EMAIL_TO, $subject, $template, $update_record['result']['content'], $update_record['result']['name']);
        }

    // Unable to update record.
    } else {
        $cli->red('Unable to update record value for '.$data['zone_name'].'.');

        // Send email notification, if enabled
        if ( EMAIL_NOTIFICATIONS ) {
            if ( HTML_EMAIL ) {
                $template = 'updated_fail_html.php';
            } else {
                $template = 'updated_fail_txt.php';
            }
            $subject = 'IP has changed. DNS failed to update.';
            $dynamic_dns->sendEmailNotification(EMAIL_TO, $subject, $template, $current_ip, $data['record_name']);
        }
    }
// The IP has not changed.
} else {
    $cli->green('Current public IP matches saved value.');
    exit();
}