<?php

/**
 * Responsibilities:
 *  - Perform metadata injection on brainfx_axbi_log_events table for viewing
 *    logged 'upload' events on admin.brainfx.com interface.
 *
 */
require_once 'class.HelperFunctions.php';

class AxbiEventLogWrapper {

  /* @var \PDO $dbConnection */
  private $dbConnection = NULL;

  private $appointment = NULL;

  private $platformData = NULL;

  private $platformDataDefaults = array(
    'cbaId' => 0,
    'logEventName' => NULL,
    'logEventTimestamp' => REQUEST_TIME,
    'appVersion' => NULL,
    'browserName' => NULL,
    'browserVersion' => NULL,
    'device' => NULL,
    'deviceHeight' => NULL,
    'deviceWidth' => NULL,
    'os' => NULL,
    'osVersion' => NULL,
    'userAgent' => NULL,
  );

  /**
   * @param object $data
   * @param object $appointment
   */
  function __construct($data, $appointment) {

    $this->dbConnection = $GLOBALS['dbConnection'];

    $this->appointment = $appointment;

    // Flesh out $platformData with real values as may be available
    if (is_object($data->data->platformData)) {
      // Extracting the platform data from assessment submission JSON for upload event submission
      $platformDataObj = $data->data->platformData;

      $tmpArray = (array) $platformDataObj;
      $this->platformData = (object) array_merge($this->platformDataDefaults, $tmpArray);
      $this->platformData->cbaId = $appointment->cba_id;
      $this->platformData->browserName = (isset($platformDataObj->browser)) ? $platformDataObj->browser : NULL;
      $this->platformData->logEventName = 'upload';
    }
    else {
      $this->platformData = (object) $this->platformDataDefaults;
    }
  }

  /**
   * @param string $propertyName
   * @param mixed $newValue
   */
  public function updatePlatformData($propertyName, $newValue) {
    if (is_object($this->platformData) && property_exists($this->platformData, $propertyName)) {
      $this->platformData->$propertyName = $newValue;
    }
  }

  /**
   * Function to inject metadata on an upload event whenever assessment is
   * uploaded, when passed the data array.
   *
   * @return bool
   *   Returns TRUE on successful DB Insert and FALSE otherwise.
   */
  public function savePlatformData() {
    /** @var \PDOStatement $dbStmt */
    $dbStmt = $this->dbConnection->prepare('
      INSERT INTO brainfx_axbi_log_events (
        cba_id,
        log_event_name,
        log_event_timestamp,
        app_version,
        browser_name,
        browser_version,
        device,
        device_height,
        device_width,
        os,
        os_version,
        user_agent
      ) VALUES (
        :cba_id,
        :log_event_name,
        :log_event_timestamp,
        :app_version,
        :browser_name,
        :browser_version,
        :device,
        :device_height,
        :device_width,
        :os,
        :os_version,
        :user_agent
      )
    ');

    $dbStmt->bindParam(':cba_id', $this->platformData->cbaId, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':log_event_name', $this->platformData->logEventName, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':log_event_timestamp', $this->platformData->logEventTimestamp, PDO::PARAM_INT);
    $dbStmt->bindParam(':app_version', $this->platformData->appVersion, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':browser_name', $this->platformData->browserName, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':browser_version', $this->platformData->browserVersion, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':device', $this->platformData->device, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':device_height', $this->platformData->deviceHeight, PDO::PARAM_INT);
    $dbStmt->bindParam(':device_width', $this->platformData->deviceWidth, PDO::PARAM_INT);
    $dbStmt->bindParam(':os', $this->platformData->os, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':os_version', $this->platformData->osVersion, PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':user_agent', $this->platformData->userAgent, PDO::PARAM_STR_CHAR);

    $dbStmtPreparedValues = array(
      ':cba_id' => HelperFunctions::checkPlain($this->platformData->cbaId),
      ':log_event_name' => HelperFunctions::checkPlain($this->platformData->logEventName),
      ':log_event_timestamp' => $this->platformData->logEventTimestamp,
      ':app_version' => HelperFunctions::checkPlain($this->platformData->appVersion),
      ':browser_name' => HelperFunctions::checkPlain($this->platformData->browserName),
      ':browser_version' => HelperFunctions::checkPlain($this->platformData->browserVersion),
      ':device' => HelperFunctions::checkPlain($this->platformData->device),
      ':device_height' => $this->platformData->deviceHeight,
      ':device_width' => $this->platformData->deviceWidth,
      ':os' => HelperFunctions::checkPlain($this->platformData->os),
      ':os_version' => HelperFunctions::checkPlain($this->platformData->osVersion),
      ':user_agent' => HelperFunctions::checkPlain($this->platformData->userAgent),
    );

    // Execute the above SQL query with the required parameters passed in to
    // upload to the event log on the admin server.
    return $dbStmt->execute($dbStmtPreparedValues);
  }

}
