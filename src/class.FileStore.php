<?php
/**
 * Dependencies
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Responsibilities:
 *   - Checks for existence of each directory in folder chain and creates them
 *     if they don't exist.
 *   - Performs actual writing of data to file system.
 */
class FileStore {

  public $fs;

  public function __construct() {
    $this->fs = new Filesystem();
  }

  /**
   * @return String|Boolean
   * The "$path" String if file was saved successfully.
   * FALSE if there was a problem with saving the file.
   */
  public function saveData($data, $fileSavePath) {
    try {
      $this->fs->dumpFile($fileSavePath, $data, 0640);
      return $fileSavePath;
    }
    catch (IOExceptionInterface $e) {
      echo "There was a problem saving the file at: {$e->getPath()}";
      return FALSE;
    }

    return FALSE;
  }

}
