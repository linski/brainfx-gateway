<?php

class CbaWrapper {

  /* @var \PDO $dbConnection */
  private $dbConnection;

  private $cba = NULL;

  function __construct() {
    $this->dbConnection = $GLOBALS['dbConnection'];
  }

  public function setCbaByUid(int $cbaUid) {
    $stmt = $this->dbConnection->query("SELECT name, uid, uuid, mail, token_date as tokenDate FROM users WHERE uid={$cbaUid}", PDO::FETCH_OBJ);

    $results = $stmt->fetchAll();

    if ($results && is_array($results) && count($results) && is_object($results[0])) {
      $this->cba = $results[0];
    }
  }

  public function setCbaByUuid(string $cbaUuid) {
    $stmt = $this->dbConnection->query("SELECT name, uid, uuid, mail, token_date as tokenDate FROM users WHERE uuid='{$cbaUuid}'", PDO::FETCH_OBJ);

    $results = $stmt->fetchAll();

    if ($results && is_array($results) && count($results) && is_object($results[0])) {
      $this->cba = $results[0];
    }
  }

  public function getCba() {
    return $this->cba;
  }

  /**
   * @throws \Exception
   */
  public function createCbaAuthHash(): string {
    if (!empty($this->cba) && !empty($this->cba->tokenDate)) {
      return md5(
        $this->cba->name . $this->cba->uuid . $this->cba->tokenDate
      );
    }

    throw new Exception('Creating the hash failed.');
  }

}
