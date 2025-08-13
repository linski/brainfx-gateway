<?php
require_once 'class.HelperFunctions.php';

/**
 * A Class dedicated to saving "Business Intelligence" metadata received from
 * BrainFx client applications into the admin.brainfx.com web app's database
 * in the `brainfx_axbi_consumer_feedback` table.
 */
class BusinessIntelligenceWrapper {
  /* @var \PDO $dbConnection */
  private $dbConnection = NULL;
  private $appointment = NULL;
  private $businessIntelligenceArr = NULL;
  private $businessIntelligenceDefaultsArr = array(
    'cbaId' => 0,
    'assessmentKey' => NULL,
    'submissionTimestamp' => REQUEST_TIME,
    'ratingNumSelected' => NULL,
    'ratingNumMax' => NULL,
    'comments' => NULL
  );

  /**
   * @param object $data
   * @param object $appointment
   */
  function __construct($data, $appointment) {
    $this->dbConnection = $GLOBALS['dbConnection'];

    $this->appointment = $appointment;

    // Inject the businessIntelligence with relevant data
    if (is_object($data->data->businessIntelligence)) {
      // Extracting the businessData data from assessment submission JSON for upload event submission
      $businessIntelDataObj = $data->data->businessIntelligence;
      $this->businessIntelligenceArr = $this->businessIntelligenceDefaultsArr;
      // Parsing the values from the passed data JSON object to the businessIntelligence array
      $this->businessIntelligenceArr['cbaId'] = $appointment->cba_id;
      $this->businessIntelligenceArr['assessmentKey'] = $appointment->assessment_key;
      $this->businessIntelligenceArr['submissionTimestamp'] = (int) $businessIntelDataObj->submissionTimestamp;
      $this->businessIntelligenceArr['ratingNumSelected'] = $businessIntelDataObj->consumerFeedbackRating->ratingNumSelected;
      $this->businessIntelligenceArr['ratingNumMax'] = $businessIntelDataObj->consumerFeedbackRating->ratingNumMax;
      $this->businessIntelligenceArr['comments'] = $businessIntelDataObj->comments;
    }
    else {
      $this->businessIntelligenceArr = $this->businessIntelligenceDefaultsArr;
    }
  }

  /**
   * Function to inject metadata on an upload event whenever assessment is
   * uploaded, when passed the data array.
   *
   * @return bool
   *   Returns TRUE on successful DB Insert and FALSE otherwise.
   */
  public function saveBusinessIntelligenceData() {
    /** @var \PDOStatement $dbStmt */
    $dbStmt = $this->dbConnection->prepare('
      INSERT INTO brainfx_axbi_consumer_feedback (
        cba_id,
        submission_timestamp,
        assessment_key,
        rating_num_selected,
        rating_num_max,
        comments
      ) VALUES (
        :cba_id,
        :submission_timestamp,
        :assessment_key,
        :rating_num_selected,
        :rating_num_max,
        :comments
      )
    ');

    $dbStmt->bindParam(':cba_id', $this->businessIntelligenceArr['cbaId'], PDO::PARAM_INT);
    $dbStmt->bindParam(':submission_timestamp', $this->businessIntelligenceArr['submissionTimestamp'], PDO::PARAM_INT);
    $dbStmt->bindParam(':assessment_key', $this->businessIntelligenceArr['assessmentKey'], PDO::PARAM_STR_CHAR);
    $dbStmt->bindParam(':rating_num_selected', $this->businessIntelligenceArr['ratingNumSelected'], PDO::PARAM_INT);
    $dbStmt->bindParam(':rating_num_max', $this->businessIntelligenceArr['ratingNumMax'], PDO::PARAM_INT);
    $dbStmt->bindParam(':comments', $this->businessIntelligenceArr['comments'], PDO::PARAM_STR_CHAR);

    $dbStmtPreparedValues = array(
      ':cba_id' => $this->businessIntelligenceArr["cbaId"],
      ':submission_timestamp' => $this->businessIntelligenceArr["submissionTimestamp"],
      ':assessment_key' => $this->businessIntelligenceArr["assessmentKey"],
      ':rating_num_selected' => $this->businessIntelligenceArr["ratingNumSelected"],
      ':rating_num_max' => $this->businessIntelligenceArr["ratingNumMax"],
      ':comments' => HelperFunctions::stringFindAndEscapeSqlArtifacts($this->businessIntelligenceArr["comments"]),
    );

    // Execute the above SQL query with the required parameters passed in
    // to feedback table on the admin server.
    return $dbStmt->execute($dbStmtPreparedValues);
  }
}
