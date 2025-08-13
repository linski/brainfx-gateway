<?php
/**
 * Responsiblities:
 *   - Performs query of Appointments table in DB to retrieve Appointment ID
 *     of given Assessment Key.
 *
 */
class AppointmentWrapper {
  /* @var \PDO $dbConnection */
  private $dbConnection = NULL;
  private $appointment = NULL;
  private $cbaWrapper = NULL;

  function __construct($axKey) {
    $this->dbConnection = $GLOBALS['dbConnection'];
    $this->setAppointment($axKey);
  }

  /**
   * Sets the Appointment by running a query against the Drupal Member Portal's
   * Database.
   */
  public function setAppointment($axKey = NULL) {
	$stmt = $this->dbConnection->prepare("SELECT * FROM brainfx_appointments WHERE assessment_key = :axKey");
  	$stmt->bindParam(':axKey', $axKey, PDO::PARAM_STR); // Explicitly bind as string
  	$stmt->execute();

  	$results = $stmt->fetchAll(PDO::FETCH_OBJ);
  	if ($results && is_array($results) && count($results) && is_object($results[0])) {
    		$this->appointment = $results[0];
  	}
  }

  /**
   * @return null|object
   *   Returns an Appointment Object if it was set correctly else, defaults to
   *   NULL.
   */
  public function getAppointment() {
    return $this->appointment;
  }

  /**
   * @param \CbaWrapper $cbaWrapper
   */
  public function setCbaWrapper(CbaWrapper $cbaWrapper): void {
    $this->cbaWrapper = $cbaWrapper;
  }

  /**
   * @return \CbaWrapper|null
   */
  public function getCbaWrapper(): ?CbaWrapper {
    return $this->cbaWrapper;
  }
}
