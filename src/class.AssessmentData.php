<?php
/** @noinspection PhpComposerExtensionStubsInspection */
/**
 * Dependencies
 */

/**
 * Responsibilities:
 *   - Receives raw data from POST and pulls out necessary info for saving it to
 *     correct file structure.
 *   - Composes and provides folder path to save file
 *   - Composes and provides file name to save data to.
 */
class AssessmentData {

  public $data_json_string;
  public $data;
  public $meta;
  public $clientId;
  public $assessmentKey;
  public $assessmentDate;
  public $appointment;
  public $appointmentId;
  public $fileSavePath;

  /**
   * Initialise variables needed to:
   *   - Confirm existence of parent file store directory and create if
   *   missing.
   *   - Construct folder structure for saving received Assessment JSON.
   *
   * @param Object              $data
   *        The data to be saved. A JSON object of the data received from the
   *        tablet. We expect that the object will have only a "data"
   *        property at the top level which will point to an object
   *        containing the data from the tablet. The expected structure is:
   *        <code><pre>
   *        {
   *          data: {
   *            cbaAssessment: {...},
   *            metaData: {...},
   *            platformData: {...}
   *            progressData: {...}
   *          }
   *        }
   *        </pre></code>
   *
   * @param object $appointment
   */
  public function __construct($data, $appointment) {
    $this->data = $data->data;
    // Store a string version of the data as well.
    $this->data_json_string = json_encode($this->data);

    // Set Assessment variables that are directly available from the JSON sent
    // by the 360 app.
    $this->meta = $this->data->metaData;
    $this->clientId = $this->meta->clientID;
    $this->assessmentKey = $this->meta->assessmentKey;
    $this->assessmentDate = $this->meta->date;

    // Pull out appointment from wrapper.
    $this->appointment = $appointment;
    // Set the appointment ID.
    $this->appointmentId = $this->appointment->id;
    // Generate folder path.
    $this->fileSavePath = $this->generateFilePath();
  }

  /**
   * @return String
   *   A valid path compatible with the runtime platform (OS). Folder/file
   *   structure and names will be:
   *   {ClientID}/{AssessmentDate(format=yyyy-mm-ddThhmmss)}_{AssessmentKey}.json
   */
  private function generateFilePath() {
    try {
      $dateTime = new DateTime($this->assessmentDate);
    }
    catch (Exception $exception) {
      print('{"error": "File path generation failed."}'); die();
    }
    $date = $dateTime->format('Y-m-d\TH-i-s');
    $path = "{$this->clientId}@DS@{$this->appointmentId}@DS@{$date}_{$this->assessmentKey}.json";
    return preg_replace('/@DS@/', DS, $path);
  }
}
