<?php

class Hipchat {
	
  const DEFAULT_TARGET = 'https://api.hipchat.com';

  const STATUS_BAD_RESPONSE = -1; // Not an HTTP response code
  const STATUS_OK = 200;
  const STATUS_BAD_REQUEST = 400;
  const STATUS_UNAUTHORIZED = 401;
  const STATUS_FORBIDDEN = 403;
  const STATUS_NOT_FOUND = 404;
  const STATUS_NOT_ACCEPTABLE = 406;
  const STATUS_INTERNAL_SERVER_ERROR = 500;
  const STATUS_SERVICE_UNAVAILABLE = 503;

  /**
   * Colors for rooms/message
   */
  const COLOR_YELLOW = 'yellow';
  const COLOR_RED = 'red';
  const COLOR_GRAY = 'gray';
  const COLOR_GREEN = 'green';
  const COLOR_PURPLE = 'purple';
  const COLOR_RANDOM = 'random';

  /**
   * Formats for rooms/message
   */
  const FORMAT_HTML = 'html';
  const FORMAT_TEXT = 'text';

  /**
   * API versions
   */
  const VERSION_1 = 'v1';

  private $api_target;
  private $auth_token;
  private $verify_ssl = true;

  function __construct($auth_token, $api_target = self::DEFAULT_TARGET,
                       $api_version = self::VERSION_1) {
    $this->api_target = $api_target;
    $this->auth_token = $auth_token;
    $this->api_version = $api_version;
  }

  public function message_room($room_id, $from, $message, $notify = false,
                               $color = self::COLOR_YELLOW,
                               $message_format = self::FORMAT_HTML) {
    $args = array(
      'room_id' => $room_id,
      'from' => $from,
      'message' => utf8_encode($message),
      'notify' => (int)$notify,
      'color' => $color,
      'message_format' => $message_format
    );
    $response = $this->make_request("rooms/message", $args, 'POST');
    return ($response->status == 'sent');
  }

  public function curl_request($url, $post_data = null) {

    if (is_array($post_data)) {
      $post_data = array_map(array($this, "sanitize_curl_parameter"), $post_data);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
    if (is_array($post_data)) {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }
    $response = curl_exec($ch);

    // make sure we got a real response
    if (strlen($response) == 0) {
      $errno = curl_errno($ch);
      $error = curl_error($ch);
      throw new Exception("CURL error: $errno - $error");
    }

    // make sure we got a 200
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code != self::STATUS_OK) {
      throw new Exception("HTTP status code: $code, response=$response");
    }

    curl_close($ch);

    return $response;
  }

  public function make_request($api_method, $args = array(),
                               $http_method = 'GET') {
    $args['format'] = 'json';
    $args['auth_token'] = $this->auth_token;
    $url = "$this->api_target/$this->api_version/$api_method";
    $post_data = null;

    // add args to url for GET
    if ($http_method == 'GET') {
      $url .= '?'.http_build_query($args);
    } else {
      $post_data = $args;
    }

    $response = $this->curl_request($url, $post_data);

    // make sure response is valid json
    $response = json_decode($response);
    if (!$response) {
      throw new Exception("Hipchat: Invalid JSON received: $response");
    }

    return $response;
  }

  private function sanitize_curl_parameter ($value) {

    if ((strlen($value) > 0) && ($value[0] === "@")) {
      return substr_replace($value, '&#64;', 0, 1);
    }

    return $value;
  }

}
