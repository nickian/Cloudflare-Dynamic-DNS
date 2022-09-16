<?php
/**
 * A script to automatically keep a Cloudflare zone "A" record up 
 * to date with your dynamic IP address.
 *
 * @link https://github.com/nickian/Cloudflare-Dynamic-DNS
 *
 * @package CloudflareDynamicDNS
 * @version 1.0
 * @license MIT
 *
 */
namespace CloudflareDynamicDNS;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Dynamically update a DNS record on Cloudflare with your local IP address.
 */
class DynamicDNS {

    /**
     * @var string|boolean $error A place to store error messages.
     */
    public $error = false;


    /**
     * @var array $header Header to use in cURL requests.
     */
    private $header = [];


    /**
     * Constructor
     *
     * @param string $token Cloudflare API token.
     */
    function __construct($token)
    {
        $this->header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
        ];
    }


    /**
     * Determine current public IP address using external service.
     * 
     * @return string IP address.
     */
    public static function getPublicIp() 
    {
        $ip = trim(shell_exec(IP_SHELL_CMD));
        if ( !filter_var($ip, FILTER_VALIDATE_IP) ) {
            return false;
        }
        return $ip;
    }


    /**
     * Wrapper for the PHP cURL extension
     * 
     * @param string $method The Request method type.
     * @param string $url The base URL.
     * @param array $params Query strings used in addition to the base URL.
     * @param array $body Data to use for the body of a POST/PUT request.
     * @param array $header Data to use in the header (e.g., Authorization)
     * 
     * @return boolean|array Return either false upon failing or an array with results.
     */
    public function curlRequest($method='GET', $url, $params=false, $body=false, $header=false)
    {
        $ch = curl_init();

        // If there are query strings, add them to the URL.
        if ( is_array($params) && !empty($params) ) {
            $url = $url.'?'.http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Add header.
        if ( $header ) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        // Add JSON-encoded body.
        if ( is_array($body) && !empty($body) ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }   

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!$response) {
            $this->error = curl_error($ch) . '" - Code: ' . curl_errno($ch);
            return false;
        } elseif ( $status != 200 ) {
            $this->error = $status;
            return false;
        } else {
            return json_decode($response, true);
        }
    }


    /**
     * Check if token status is active.
     * 
     * @param string $token The API token to test.
     * 
     * @return boolean
     */
    public function validToken($token=false)
    {
        if ( $token ) {

            $valid = $this->curlRequest('GET', BASE_URL.'user/tokens/verify', false, false, $this->header);

            if ( isset($valid['result']['status']) && $valid['result']['status'] == 'active') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Check if JSON file exists.
     * 
     * @param string Full path to file.
     * 
     * @return boolean
     */
    public function dataExists($file)
    {
        if ( file_exists($file) ) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Create JSON file.
     * 
     * @param array $data The data to write to the file.
     * 
     * @return boolean
     */
    public function createData($data)
    {
        $file = APP_PATH.'data.json';
        if ( is_array($data) ) {
            if ( file_put_contents($file, json_encode($data)) ) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Read JSON file.
     * 
     * @param string $filename Full path to file.
     * 
     * @param array $data The data to write to the file.
     * 
     * @return array|boolean The data array or false.
     */
    public function readData($filename)
    {    
        if (($file = @fopen($filename, "r")) !== false ) {
            if ( filesize($filename) > 0 ) {
                $file_data = fread($file, filesize($filename));
                fclose($file);
                return json_decode($file_data, true);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Remove the data file.
     * 
     * @return boolean
     */
    public function removeData()
    {
        if ( file_exists(DATA_FILE) ) {
            if ( unlink(DATA_FILE) ) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Find the correct zone ID in the array of all zones.
     * 
     * @param string $zone_name The name of the zone/root domain.
     * 
     * @return string|boolean The zone id or false.
     */
    public function findZoneId($zone_name)
    {
        $zones = $this->getAllZones();

        if ( $zones && isset($zones['all_zones']) ) {
            
            $zones = $zones['all_zones'];
            $zone_name_key = array_search ($zones[$zone_name], $zones);
            
            if ( array_key_exists($zone_name_key, $zones)) {
                $zone_id = $zones[$zone_name];
                return $zone_id;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * Get zone details by ID
     * 
     * @param string $id The zone ID to get details for.
     * 
     * @return array|boolean The zone details array or false.
     */
    public function getZoneDetails($id)
    {
        $header = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
        ];
        $zone_details = $this->curlRequest('GET', BASE_URL.'zones/'.$id, $params, false, $this->header);
        if ( $zone_details ) {
            return $zone_details;
        } else {
            return false;
        }   
    }


    /**
     * Get the domain zones assocciated with this account by page.
     * 
     * @param string $page The page number of results to get.
     * 
     * @return array|boolean The response array or false.
     */
    public function getZones($page=1)
    {
        $params = [
            'match' => 'all',
            'order' => 'name',
            'page' => $page,
            'per_page' => 50,
            'status' => 'active',
            'direction' => 'asc'
        ];

        $zones_request = $this->curlRequest('GET', BASE_URL.'zones', $params, false, $this->header);

        if ( $zones_request ) {
            return $zones_request;
        } else {
            return false;
        }
    }


    /**
     * Get all zones in account by looping through any additional pages.
     * 
     * @return array|boolean An array of all zones or false.
     */
    public function getAllZones()
    {
        $all_zones = [];

        // Get the first page of zones.
        $get_zones = $this->getZones(1);

        if ( $get_zones ) {

            $page = $get_zones['result_info']['page'];
            $count = $get_zones['result_info']['count'];
            $total_count = $get_zones['result_info']['total_count'];
            $total_pages = $get_zones['result_info']['total_pages'];
            $per_page = $get_zones['result_info']['per_page'];

            foreach( $get_zones['result'] as $zone ) {
                $all_zones[$zone['name']] = $zone['id'];
            }

            // Get the remaining pages, if they exist.
            if ( $total_pages > 1 ) {
                $i = 2;
                while ( $i <= $total_pages ) {
                    $more_zones = $this->getZones($i);
                    if ( $more_zones ) {
                        foreach( $more_zones['result'] as $zone ) {
                            $all_zones[$zone['name']] = $zone['id'];
                        }
                    }
                    $i++;
                }
            }

            return [
                'all_zones' => $all_zones,
                'total_count' => $total_count
            ];

        } else {
            return false;
        }
    }


    /**
     * Find the correct zone ID in the array of all zones.
     * 
     * @param string $record_name The name of the record.
     * @param string $zone_id The ID of the zone the record is in.
     * 
     * @return string|boolean The ID of the record or false.
     */
    public function findRecordId($record_name, $zone_id)
    {
        $records = $this->getAllRecords($zone_id);

        if ( $records && isset($records['all_records']) ) {
            
            $records = $records['all_records'];

            $record_name_key = array_search($records[$record_name], $records);
            
            if ( isset($records[$record_name]) ) {
                if ( array_key_exists($record_name_key, $records)) {
                    $record_id = $records[$record_name];
                    return $record_id;
                } else {
                    return false;
                }
            } else {
                return false;
            }
    
        } else {
            return false;
        }
    }


	/**
     * Get the domain zones assocciated with this account by page.
     * 
     * @param string $zone_id The ID of the zone to get records from.
     * @param integer $page The page number of results.
     * 
     * @return array|boolean The array of records or false.
     */
    public function getRecords($zone_id, $page=1)
    {
        $params = [
            'match' => 'all',
            'type' => 'A',
            'order' => 'name',
            'page' => $page,
            'per_page' => 50,
            'status' => 'active',
            'direction' => 'asc'
        ];

        $records_request = $this->curlRequest('GET', BASE_URL.'zones/'.$zone_id.'/dns_records', $params, false, $this->header);

        if ( $records_request ) {
            return $records_request;
        } else {
            return false;
        }
    }


    /**
     * Get all records in zone by looping through any additional pages.
     * 
     * @param string $zone_id The zone ID to get records from.
     * 
     * @return array|boolean Array of record or false.
     */
    public function getAllRecords($zone_id)
    {
        $all_records = [];

        // Get the first page of zones.
        $get_records = $this->getRecords($zone_id, 1);

        if ( $get_records ) {

            $page = $get_records['result_info']['page'];
            $count = $get_records['result_info']['count'];
            $total_count = $get_records['result_info']['total_count'];
            $total_pages = $get_records['result_info']['total_pages'];
            $per_page = $get_records['result_info']['per_page'];

            foreach( $get_records['result'] as $record ) {
                $all_records[$record['name']] = $record['id'];
            }

            // Get the remaining pages, if they exist.
            if ( $total_pages > 1 ) {
                $i = 2;
                while ( $i <= $total_pages ) {
                    $more_records = $this->getRecords($zone_id, $i);
                    if ( $more_records ) {
                        foreach( $more_records['result'] as $record ) {
                            $all_records[$record['name']] = $record['id'];
                        }
                    }
                    $i++;
                }
            }

            return [
                'all_records' => $all_records,
                'total_count' => $total_count
            ];

        } else {
            return false;
        }
    }


    /**
     * Get the value of a record by its ID.
     * 
     * @param string $zone_id The ID of the zone the record is in.
     * @param string $record_id The ID of the record.
     * 
     * @param string|boolean The record value or false.
     */
    public function getARecordValue($zone_id, $record_id)
    {
        $record_detail = $this->curlRequest('GET', BASE_URL.'zones/'.$zone_id.'/dns_records/'.$record_id, false, false, $this->header);
        if ( $record_detail && isset($record_detail['result']['content']) ) {
            return $record_detail['result']['content'];
        } else {
            return false;
        }
    }


    /**
     * Create an "A" record in the provided zone.
     * 
     * @param string $zone_id The ID of the zone to create a record in.
     * @param string $record_name The name of the record to create.
     * @param string $content The value for the record.
     * @param boolean $proxied Whether or not to proxy record.
     * 
     * @return array|boolean The response array or false.
     */
    public function createARecord($zone_id, $record_name, $content, $proxied=false)
    {
        $body = [
            'type' => 'A',
            'name' => $record_name,
            'content' => $content,
            'proxied' => $proxied
        ];

        $create_record = $this->curlRequest('POST', BASE_URL.'zones/'.$zone_id.'/dns_records', false, $body, $this->header);

        if ( $create_record ) {
            return $create_record;
        } else {
            return false;
        }
    }


    /**
     * Update the value for a given DNS record in a zone.
     * 
     * @param string $zone_id The ID of the zone the record is in.
     * @param string $record_id The ID of the record.
     * @param string $content The value of the record (IP address).
     * @param boolean $proxied Whehter or not to proxy record.
     * 
     * @return array|boolean The response array or false.
     */
    public function updateARecord($zone_id, $record_id, $record_name, $content, $proxied=false)
    {        
        $body = [
            'type' => 'A',
            'name' => $record_name,
            'content' => $content,
            'proxied' => $proxied
        ];

        $update_record = $this->curlRequest('PUT', BASE_URL.'zones/'.$zone_id.'/dns_records/'.$record_id, false, $body, $this->header);

        if ( isset($update_record['success']) && $update_record['success'] == true ) {
            return $update_record;
        } else {
            return false;
        }  
    }


    /**
     * Send an email notification about IP update.
     * 
     * @param string $to The email address we are sending to.
     * @param string $subject The subject line in the email.
     * @param string $template The name of the file template in email_templates folder.
     * @param string $ip_address The new IP address that has changed.
     * @param string $record_name The name of the DNS A record being used.
     * 
     * @return boolean
     */
    public function sendEmailNotification($to, $subject, $template, $ip_address, $record_name)
    {
        if ( 
            (!SMTP_HOST || 
            !SMTP_USER || 
            !SMTP_PASSWORD || 
            !SMTP_PORT || 
            !EMAIL_FROM_ADDRESS || 
            !EMAIL_FROM_NAME ) ||
            ( !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var(EMAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL) )
        ) {
            return false;
        }

        // Get template contents
        ob_start();
        require_once(APP_PATH.'/email_templates/'.$template);
        $body = ob_get_contents();
        ob_end_clean();

        $this->mail = new PHPMailer(true);

        try {
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USER;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mail->Port       = SMTP_PORT;
            $this->mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            $this->errors = $this->mail->ErrorInfo;
            return false;
        }
    }
}