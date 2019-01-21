<?php


$webcam = $_GET['url'];

$data = get_solarcam_infos($webcam);

// Output
header('Content-Type: application/json');
echo json_encode($data);



/**
 * Return all infos from a solarcam
 *
 * @param $webcam_url
 */
function get_solarcam_infos($webcam_url) {

  $data = array();
  $data['webcam_url'] = $webcam_url;

// Get HTML content
  $webcamHtmlContent = @file_get_contents($data['webcam_url']);
  if ($webcamHtmlContent === FALSE) {
    return;
  }

  $dom = new DOMDocument; // DOM document Creation
  @$dom->loadHTML($webcamHtmlContent);
  $xpath = new DOMXPath($dom); // DOM XPath Creation
  _get_url_image_and_log($xpath, $data);


  // Reading log file.
  $log_file = @file($data['log_url']);
  if ($log_file !== FALSE) {

    $log_content = $log_file[0];
    $log_array = preg_split("/(\r\n|\n|\r)/", $log_content);
    $log_array_count = count($log_array);

    // Device info
    _get_device_info($log_array[0], $data);


    // GPS information from cellID info
    if ($data['device_network'] === "3G") {
      $log_second_line = $log_array[1]; // with cellid info link
      $log_second_line_data = explode(' ', $log_second_line);
      $data['gps_link'] = $log_second_line_data[0];
      _get_gsm_cell_info($data);
    }


    // Last info from image been taken
    $log_last_line = $log_array[$log_array_count - 2];

    // V58, V59 => OK.
    _get_log_from_image($log_last_line, $data);

  }

  return $data;

}

/**
 *
 * return url's image
 *
 * @param $xpath
 * @param $data
 */
function _get_url_image_and_log($xpath, &$data) {

  // Get latest image
  $image_last = $xpath->query('/html/body/div[1]/div/div/a[1]/@href');
  $image_last_url = $data['webcam_url'] . $image_last->item(0)->value;
  $data['image_last_url'] = $image_last_url;

  $image_last_small = $xpath->query('/html/body/div[1]/div/div/a[1]/img/@src');
  $image_last_url_small = $data['webcam_url'] . str_replace('./', '', $image_last_small->item(0)->value);
  $data['image_last_url_small'] = $image_last_url_small;

  // Get log file
  $data['log_url'] = $data['webcam_url'] . date('ymd') . '.txt';

}

/**
 * return GSM info
 *
 * @param $gps_link
 * @param $data
 */
function _get_gsm_cell_info(&$data) {
  $query_str = parse_url($data['gps_link'], PHP_URL_QUERY);
  parse_str($query_str, $query_params);
  $data['gsm_cell_cellid'] = (int) $query_params['cellid'];
  $data['gsm_cell_lac'] = (int) $query_params['lac'];
  $data['gsm_cell_mcc'] = (int) $query_params['mcc'];
  $data['gsm_cell_mnc'] = (int) $query_params['mnc'];
}

/**
 * decrypt image log info
 *
 * @param $line
 * @param $data
 */
function _get_log_from_image($line, &$data) {

  $line_array = explode(' ', $line);

  $solarcam_modern = array(56, 57, 58, 59, 60);

  $solarcam_middle = array(49, 54, 55);
  $solarcam_old = array(48); //@@todo : find match on log.

  if (in_array($data['device_solarcam_version'], $solarcam_middle)) {
    \array_splice($line_array, 3, 1);
  }

  $data['time'] = strtotime('20' . pathinfo($data['log_url'], PATHINFO_FILENAME) . ' ' . $line_array[1]);
  $data['temp'] = (float) str_replace('C', '', $line_array[3]);
  $data['volt'] = (float) str_replace(array('v', ','), array('', '.'), $line_array[2]);
  $data['network'] = $line_array[4];
  $data['cam_second'] = (float) str_replace(array('Cam=', 's'), array('', ''), $line_array[5]);
  $data['light'] = str_replace(array('Light:'), array(''), $line_array[6]);
  $data['ip'] = (int) str_replace(array('Ip:'), array(''), $line_array[7]);
  $data['http'] = str_replace(array('HTTP:'), array(''), $line_array[8]);
  $data['size'] = $line_array[9];
  $data['speed'] = $line_array[10];
  $data['echec'] = str_replace(array('Echec:'), array(''), $line_array[11]);
  $data['result'] = $line_array[12];

}

/**
 * decrypt device log info
 *
 * @param $log_last_line
 * @param $data
 */
function _get_device_info($line, &$data) {

  $line_array = explode(' ', $line);

  $data['device_model'] = $line_array[0] . " " . $line_array[1] . " " . $line_array[2];

  $data['device_android_version'] = $line_array[4];
  $data['device_solarcam_version'] = (int) str_replace(array('V'), array(''), $line_array[5]);
  $data['device_name'] = $line_array[6];
  $data['device_network'] = $line_array[7];
  $data['device_frequency_min'] = (int) str_replace(array('min'), array(''), $line_array[8]);
  $data['device_size'] = (int) $line_array[10];

  $data['device_frequency_rule'] = $line_array[12];
  $data['device_voltage_limit'] = str_replace(array('no-pics='), array(''), $line_array[13]);
  $data['device_access'] = $line_array[15] . " " . $line_array[16];


}