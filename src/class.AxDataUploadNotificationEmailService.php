<?php

use PHPMailer\PHPMailer\PHPMailer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Responsibilities:
 *    - Intended for specifically sending out email notifications to the BrainFx
 *      Support team, informing them of each time that a 360 Ax JSON payload is
 *      received and saved as a file on the server.
 *
 */
class AxDataUploadNotificationEmailService {

  private Twig\Loader\FilesystemLoader $twigLoader;

  private Twig\Environment $twig;

  private $emailRecipients;

  private $appointment;

  private AppointmentWrapper $appointmentWrapper;

  private $axData;

  private $fileSaveStatus;

  private Monolog\Logger $lgrObj;

  private PHPMailer $mail;

  /**
   * AxDataUploadNotificationEmailService constructor.
   *
   * @param $cfgObj
   * @param $appointmentWrapper
   * @param $axData
   * @param $fileSaveStatus
   * @param $lgrObj
   *
   * @throws \PHPMailer\PHPMailer\Exception
   */
  function __construct($cfgObj,
    $appointmentWrapper,
    $axData,
    $fileSaveStatus,
    $lgrObj
  ) {
    $this->appointmentWrapper = $appointmentWrapper;
    $this->appointment = $appointmentWrapper->getAppointment();
    $this->axData = $axData;
    $this->fileSaveStatus = $fileSaveStatus;
    $this->lgrObj = $lgrObj;

    $this->emailRecipients = $cfgObj->notificationEmailRecipients;

    $this->twigLoader = new FilesystemLoader(APP_ROOT . '/templates');
    $this->twig = new Environment($this->twigLoader);

    $this->mail = new PHPMailer();
    $this->mail->isHTML(TRUE);
    $this->mail->setFrom('system@gateway.360ax.brainfx.com');
  }

  private function renderEmail(array $variables = []) {
    return $this->twig
      ->load('emailDataUpload.twig')
      ->render($variables);
  }

  public function sendEmail() {
    try {
      $cbaEmail = $this->appointmentWrapper->getCbaWrapper()->getCba()->mail;
      $clientId = $this->appointment->client_id;
      $axEndDate = $this->axData->meta->dateEnd;

      $twigVars = [
        'assessmentKey' => $this->axData->assessmentKey,
        'cbaEmail' => $cbaEmail,
        'clientId' => $clientId,
        'cbaId' => $this->appointment->cba_id,
        'dateStart' => $this->axData->meta->dateStart,
        'dateEnd' => $axEndDate,
        'filePath' => $this->axData->fileSavePath,
        'fileSaveStatus' => ($this->fileSaveStatus)
          ? 'The JSON file was saved successfully.'
          : 'There was an error with saving the JSON file.',
      ];

      $this->mail->addAddress(array_shift($this->emailRecipients));
      $this->mail->Subject = "BrainFx 360 Upload by: {$cbaEmail}, for client: {$clientId}, Ax completed on: {$axEndDate}.";

      $this->mail->Body = $this->renderEmail($twigVars);

      if (count($this->emailRecipients) > 0) {
        foreach ($this->emailRecipients as $recipient) {
          $this->mail->addCC($recipient);
        }
      }

      $this->mail->send();
    }
    catch (Exception $e) {
      $mailErrorMsg = "Error: Unable to send Ax Data upload notification email.";
      $mailErrorMsg .= "PHPMailer Error: {$this->mail->ErrorInfo}";

      $this->lgrObj
        ->withName("ax-key-{$this->axData->assessmentKey}_send-email")
        ->error(
          $mailErrorMsg,
          ['Assessment Key: ' => $this->appointment->assessment_key]
        );
    }
  }

}
