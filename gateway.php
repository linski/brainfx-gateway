<?php

/**
 * Globals
 */
if (!defined('APP_ROOT')) {
  define('APP_ROOT', dirname(__FILE__));
}
if (!defined('REQUEST_TIME')) {
  define('REQUEST_TIME', (int) $_SERVER['REQUEST_TIME']);
}
// Add header to support CORS calls from the front end app. Only add headers
// if they haven't been set by the server (Nginx) already. We depend upon server
// configuration passing a $_SERVER variable called, 'NGINX_CORS_HEADERS' to
// determine whether to manually set the headers or not.
$nginx_cors_headers = (isset($_SERVER['NGINX_CORS_HEADERS'])) ?
  explode(',', $_SERVER['NGINX_CORS_HEADERS']) :
  array();

$required_cors_headers = array(
  'Access-Control-Allow-Origin' => '*',
  'Access-Control-Allow-Credentials' => 'true',
  'Access-Control-Allow-Methods' => 'OPTIONS, POST',
  'Access-Control-Allow-Headers' => 'Accept, Content-Type, Access-Control-Request-Headers, Authorization, Origin, X-Requested-With'
);
$required_cors_headers = [];
foreach ($required_cors_headers as $header => $value) {
  if (!in_array($header, $nginx_cors_headers)) {
    header("{$header}: {$value}");
  }
}

/** @noinspection PhpComposerExtensionStubsInspection */
header('Content-Type: application/json');
/**
 * Simply respond with garbage for an HTTP Options request. Basic
 * JSON object sent back is for debugging purposes only.
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  echo '{"o":"p"}';
  exit;
}

/**
 * Confirm whether this is a POST request before proceeding.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(array('r' => '-',));
  exit;
}
/**
 * UPDATE PHP RUNTIME ENVIRONMENT.
 * -----------------------------------------------------------------------------
 * Set up the PHP Environment to ensure all procedures that need to execute
 * can without being interrupted or timed-out.
 */
// Force and increase of PHP's max_execution_time value. The number of seconds
// for the limit needs meet and exceed the number of seconds we give cURL to
// connect and attempt its functions. See use of "CURLOPT_CONNECTTIMEOUT" and
// "CURLOPT_TIMEOUT" further on in this script.
$curloptConnectionTimeout = 1800;
$curloptTimeout = 3600;
$phpTimeout = $curloptConnectionTimeout + $curloptTimeout + 60;
set_time_limit($phpTimeout);
ini_set('max_execution_time', $phpTimeout);
// Disallow User Abort to cancel script execution.
ignore_user_abort(TRUE);

/**
 * RETRIEVE DATA AND PERFORM SIMPLE VALIDATION.
 * -----------------------------------------------------------------------------
 * Pull out and store data from received Request. $_POST will not have data
 * and/or not useful data because the tablet is now sending us a raw JSON
 * string.
 */
$raw_request_data = file_get_contents('php://input');
$request_json_obj = json_decode($raw_request_data);

/**
 * Verify we have data before proceeding.
 */
if (
  empty($raw_request_data) ||
  !is_object($request_json_obj) ||
  !isset($request_json_obj->data) ||
  !is_object($request_json_obj->data)
) {
  echo json_encode(array(
      'd' => '-',
    )
  );
  exit;
}
/**
 * DECIPHER RUNTIME ENVIRONMENT AND SET UP RUNTIME ENV VARS.
 * -----------------------------------------------------------------------------
 * Check for and set the Environment that this Gateway app is running from.
 * We need to know this in order to decide whether to send the data to
 * Daisy's server via cURL further on or to simply save the received JSON to
 * disk.
 *
 * Sending data to Daisy's grading server is desired only for the
 * Production/Live environment, for Staging and Local Developer environments,
 * we only want to save the JSON we receive.
 *
 * @TODO: NOTE: This may change in the future if Daisy sets up a Staging DB
 * todo   for us that can receive data from our own Staging server.
 * todo   #asharma@brainfx.com
 */
$runtime_environment = '';
$runtime_environment_is_production = TRUE;
switch ($_SERVER['HTTP_HOST']) {
  case 'gateway.360ax.brainfx.lcl':
  case 'gateway.tablet.brainfx.lcl':
    $runtime_environment = 'local';
    $runtime_environment_is_production = FALSE;
    break;
  case 'staging.gateway.360ax.brainfx.com':
  case 'staging.gateway.tablet.brainfx.com':
    $runtime_environment = 'staging';
    $runtime_environment_is_production = FALSE;
    break;
  default:
    $runtime_environment = 'production';
    break;
}

/**
 * LOAD PHP DEPENDENCIES/SCRIPTS/LIBRARIES AND PERFORM OTHER SUCH SET UP.
 * -----------------------------------------------------------------------------
 */
//Initial variable and Composer autoload set up. Must be done before importing
//packages with the PHP "use" statement (e.g. "use Monolog\Logger").
$librariesLoaded = FALSE;
$fileSaveStatus = FALSE;

try {
  require_once APP_ROOT . '/vendor/autoload.php';
  $librariesLoaded = TRUE;
}
catch (Exception $e) {
  $eol = PHP_EOL;
  echo "Caught exception: {$e->getMessage()}.{$eol}";
}

// Load configuration data. This sets up some CONSTANTS for use; e.g. "DS" =
// DIRECTORY_SEPARATOR. This must be required AFTER the autoloader as it makes
// use of the PHP "use" statement as well.
require_once APP_ROOT . '/src/class.GatewayConfig.php';

// Instantiate and load config for use by other classes;
$cfgObj = new GatewayConfig();
$cfg = $cfgObj->getConfig();
$ds = DS;

$output = array(
  'curlSendStatus' => FALSE,
  'fileSaveStatus' => FALSE,
  'runtimeEnvironmentInt' => $cfgObj->getRuntimeEnv(),
  'runtimeEnvironmentName' => $cfgObj->getRuntimeEnvName(),
);

/**
 * Load Dependencies
 */

// Logger classes.
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// Perhaps, at some future time, we may want to send emails of these events.
// Not right now though.
//use Monolog\Handler\MailHandler;

// Load classes needed to parse and save Assessment data.
require_once APP_ROOT . '/src/class.CbaWrapper.php';
require_once APP_ROOT . '/src/class.AppointmentWrapper.php';
require_once APP_ROOT . '/src/class.AssessmentData.php';
require_once APP_ROOT . '/src/class.FileStore.php';
require_once APP_ROOT . '/src/class.HelperFunctions.php';
require_once APP_ROOT . '/src/class.AxbiEventLogWrapper.php';
require_once APP_ROOT . '/src/class.BusinessIntelligenceDataWrapper.php';
require_once APP_ROOT . '/src/class.AxDataUploadNotificationEmailService.php';

// Performing Authentication
$authenticationFailedResponse = new stdClass();
$authenticationFailedResponse->message = 'Authentication failed.';

$headers = apache_request_headers();
if (
  empty($headers['Authorization']) ||
  // I.e., we are ensuring that the Authorisation header string at minimum
  // has the same length as, "Bearer " before proceeding.
  strlen($headers['Authorization']) < 7
) {
  http_response_code(401);
  print json_encode($authenticationFailedResponse);
  exit;
}

// Load Appointment.
$appointmentWrapper = new AppointmentWrapper($request_json_obj->data->metaData->assessmentKey);
$appointment = $appointmentWrapper->getAppointment();

// The axbiEventLogWrapper is used for saving BOTH Upload (Send Assessment) and
// Authentication/Authorisation Failure Events into the
// brainfx_axbi_log_events table. We also use it to save the Platform Data
// for each Event Log when available.
$axbiData = new AxbiEventLogWrapper($request_json_obj, $appointment);

// Continue Performing Authentication
$cbaWrapper = NULL;
try {
  $authToken = substr($headers['Authorization'], 7);
  $authTokenParts = explode('_', $authToken);
  $uuid = $authTokenParts[0];
  $md5Hash = $authTokenParts[1];
  $cbaWrapper = new CbaWrapper(); // "SELECT name, uid, uuid, mail, token_date as tokenDate FROM users WHERE uuid='" . $uuid . "'"
  $cbaWrapper->setCbaByUuid($uuid);
  $recreatedHash = $cbaWrapper->createCbaAuthHash();

  // Validate the cba exists.
  if (empty($cbaWrapper->getCba())) {
    $axbiData->updatePlatformData('cbaId', $uuid);
    $axbiData->updatePlatformData('logEventName', 'upload-authentication-failed-cba-uuid-not-found');
    $axbiData->savePlatformData();

    // "C-000" stands for "CBA Not Found". Using this code because the
    // exception message is passed back to the client application and it is
    // not safe practice to inform users of this type of error as it can be
    // used for brute-force attacks.
    throw new Exception('Identity claim validation failed: C-000.');
  }

  // Validate the identity claim.
  if ($recreatedHash !== $md5Hash) {
    $axbiData->updatePlatformData('cbaId', $uuid);
    $axbiData->updatePlatformData('logEventName', 'upload-authentication-failed-token-validation-failed');
    $axbiData->savePlatformData();


    // "T-000" stands for "Token Not Valid". Using this code because the
    // exception message is passed back to the client application and it is
    // not safe practice to inform users of this type of error as it can be
    // used for brute-force attacks.
    throw new Exception('Identity claim validation failed: T-000.');
  }

  // Check token expiry date time
  $parsedDateTime = DateTime::createFromFormat(
    "Y-m-d H:i:s",
    $cbaWrapper->getCba()->tokenDate
  );

  // 600 minutes or 10 hours
  $parsedDateTime->add(new DateInterval('PT600M'));

  if ($parsedDateTime <= new DateTime()) {
    $axbiData->updatePlatformData('cbaId', $cbaWrapper->getCba()->uid);
    $axbiData->updatePlatformData('logEventName', 'upload-authentication-failed-token-expired');
    $axbiData->savePlatformData();

    throw new Exception('Authentication token is expired.');
  }
}
catch (Exception $exception) {
  http_response_code(401);
  $authenticationFailedResponse->message .= $exception->getMessage();
  print json_encode($authenticationFailedResponse);
  exit;
}

// Authorisation.
if ($appointment->cba_id !== $cbaWrapper->getCba()->uid) {
  $axbiData->updatePlatformData('cbaId', $cbaWrapper->getCba()->uid);
  $axbiData->updatePlatformData('logEventName', 'upload-access-denied');
  $axbiData->savePlatformData();

  http_response_code(403);
  $authorisationFailedResponse = new stdClass();
  $authorisationFailedResponse->message = 'Access denied.';
  print json_encode($authorisationFailedResponse);
  exit;
}
else {
  // If Authorisation is successful, set the Appointment CBA to the
  // authenticated CBA.
  $appointmentWrapper->setCbaWrapper($cbaWrapper);
}

// Load and parse Assessment Data.
$axData = new AssessmentData($request_json_obj, $appointment);

// Set Date/Time format for use with date().
$timestampFormat = 'H:i:s:u-T';

// Set up Logger for logging success/failure messages.
$lgrLogFileName = APP_ROOT . "{$ds}{$cfg->logDirName}{$ds}{$cfg->logFileName}";
$lgrStream = new RotatingFileHandler($lgrLogFileName, 0, Logger::INFO, TRUE, 0640);
$lgrObj = new Logger('logger');
$lgrObj->pushHandler($lgrStream);
$lgrTimestamp = $lgrObj->withName("ax-key-{$axData->assessmentKey}_request-timestamp");
$lgrCurl = $lgrObj->withName("ax-key-{$axData->assessmentKey}_data-curl-send");
$lgrSave = $lgrObj->withName("ax-key-{$axData->assessmentKey}_data-file-save");

// Log start time of Request processing.
$lgrTimestamp->info('Request timestamp start:', array('timestamp-start' => date($timestampFormat)));

// Loading axbiConsumerFeedbackWrapper to pass feedback data to inject into
// required table.
$biData = new BusinessIntelligenceWrapper($request_json_obj, $appointment);

/**
 * SAVE POST JSON DATA TO FILE SYSTEM.
 * -----------------------------------------------------------------------------
 * If there any problems along the way, the "$output"
 * variable will not be echoed.
 */
if ($librariesLoaded) {
  // Retrieve generated path for saving file. Format:
  // ClientId/ApptId/Date_AxKey
  $fileSavePath = $axData->fileSavePath;
  // Load FileStore handler.
  $fs = new FileStore();

  // Save data.
  // IMPORTANT NOTE: Please make sure that the parent directory of this script
  //                 file is writable by the Group and/or User that PHP is
  //                 being run as.
  $path = APP_ROOT . "{$ds}{$cfg->assessmentDirName}{$ds}{$axData->fileSavePath}";
  $fileSaveStatus = $fs->saveData(HelperFunctions::remove_accents($axData->data_json_string), $path);
  $lgrSave->info('Data file save', array('status' => $fileSaveStatus));

  $output['fileSaveStatus'] = (bool) $fileSaveStatus;

  if ($output['fileSaveStatus']) {
    // Save PlatformData as an Upload event to the BrainFx Assessment Business
    // Intelligence event log in the brainfx_axbi_log_events table in the
    // admin.brainfx.com DB.
    $axbiData->savePlatformData();

    // Save the businessIntelligence data from feedback data parsed within the
    // ConsumerFeedback class.
    $biData->saveBusinessIntelligenceData();
  }
  else {
    // Change the event name to indicate that there was an issue with uploading
    // the data because the JSON file wasn't saved correctly.
    // @TODO: Need to add a new column in brainfx_axbi_log_events table called
    // todo   "event_comment" that can be used to save this sort of information.
    // todo   #aparashar@brainfx.com | 2019-09-16T2337
    $axbiData->updatePlatformData('logEventName', 'upload: JSON file not saved.');
    $axbiData->savePlatformData();
  }

  $mailer = new AxDataUploadNotificationEmailService(
    $cfg,
    $appointmentWrapper,
    $axData,
    $output['fileSaveStatus'],
    $lgrObj
  );
  $mailer->sendEmail();

  /**
   * KEEP THIS COMMENT!!!
   *
   * $curlStatus is no longer sent in the response because we are sending the
   * Response back to the Client before performing the cURL Request to send
   * the data to Daisy. The cURL request has been moved to executing BEFORE
   * the file save to the end of this script.
   *
   * Original Code we had here was:
   * $output['curlSendStatus'] = (bool) $curlStatus;
   */
}

/**
 * SEND FILE SAVE STATUS TO CLIENT BROWSER.
 * -----------------------------------------------------------------------------
 * Send Response back to Client before initiating cURL Request to send data
 * to Daisy.
 * Ref.: https://php.net/manual/en/features.connection-handling.php#93441
 */
// Ensure no content exists in the output buffer.
ob_end_clean();
// Inform the client browser that the server is closing the connection after
// sending the following JSON string as the Response.
header("Connection: close");
// Tell the client browser that the Response is plain-text and not encoded in
// any way.
header("Content-Encoding: none");
// Open and begin output-buffer to store JSON string for sending in Response.
ob_start();
// Store JSON string in output-buffer.
print json_encode($output);
// Get character length of Response.
$content_size = ob_get_length();
// Inform client browser of Response content's character length.
header("Content-Length: {$content_size}");
// Send Response to client browser.
ob_end_flush();
// Flush buffers.
flush();
// Ensure output-buffer has been fully cleaned (erased).
ob_end_clean();

/**
 * SEND POST DATA TO DAISY.
 * -----------------------------------------------------------------------------
 */
$curlStatus = FALSE;
// Only attempt to data to Daisy via CURL if this is PRD or STG env
// (i.e. not LCL).
if ($runtime_environment_is_production || $runtime_environment === 'staging') {
  // If PRD, use PRD endpoint, else use STG endpoint.

  // NOTE: Keeping this here for posterity's sake as we MIGHT need this
  // conditional setting of the URL if Daisy ends up providing us with an STG
  // instance on their new GCP server.
  // #aparashar@brainfx.com | 2021-06-28T1412
  /*
  $url = ($runtime_environment_is_production) ?
    'https://bfxca.daisyintel.com/web_service/load_all.php' :
    'https://bfxca.daisyintel.com/web_service_stg/load_all.php';
  */
  $url = 'https://bfxca.daisyintel.com/web_service/load_all.php';

  $ch = curl_init();

  if ($ch === FALSE) {
    die($ch);
  }

  // Daisy expects a URL encoded string of the following object:
  // { data: "{...data json string from tablet...}" }.
  $data_to_send = new stdClass();
  $data_to_send->data = json_encode($request_json_obj->data);
  $data_to_send = http_build_query($data_to_send);

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, TRUE);

  curl_setopt($ch, CURLOPT_POSTFIELDS, $data_to_send);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curloptConnectionTimeout);
  curl_setopt($ch, CURLOPT_TIMEOUT, $curloptTimeout);

  $curlStatus = curl_exec($ch);
  curl_close($ch);

  $lgrCurl->info('Data curl send:', array('status' => $curlStatus));
}
else {
  $lgrCurl->info('Data curl send:', array('status' => "Curl sending was skipped: non-production environment detected. Environment Detected: {$runtime_environment}"));
}

/**
 * WRAP UP OPERATION.
 * -----------------------------------------------------------------------------
 */
// Log end time of Request processing.
$lgrTimestamp->info('Request timestamp end:', array('timestamp' => date($timestampFormat)));
// Explicitly tell PHP that this Request's procedural execution is finished
// and that it should shut down this process.
exit;
