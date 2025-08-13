<?php
/**
 * Dependencies
 */
// Loaded via autoload.php which is included in "gateway.php"
use Symfony\Component\Yaml\Yaml;

/**
 * Responsiblities:
 *   - Identifies current runtime environment based upon $_SERVER['HTTP_HOST'].
 *   - Retrieves and provides data needed by other classes *based upon current
 *     environment*. Config includes:
 *     - Path to Drupal Root for using to load the DB.
 *     - DB credentials. // <- is this needed if we're bootstrapping the Drupal
 *     DB?
 *   - On configuration load, sets the "DRUPAL_ROOT" constant then loads Drupal
 *     for use as an API.
 */
class GatewayConfig {

  const ENV_LOCAL = 1;
  const ENV_STAGING = 2;
  const ENV_PRODUCTION = 3;

  // Runtime Environment Int (corresponding with Constant Value).
  private $runEnv = NULL;
  // Runtime Environment Name (corresponding with Constant Name).
  private $runEnvName = NULL;

  // Variables needed to determine the (runtime) (contextual) environment as
  // as well as the name of the configuration variables file that will be
  // loaded.
  private $envLoadVars = array(
    1 => array(
      'name'       => 'ENV_LOCAL',
      'domains'    => array(
        'gateway.360ax.brainfx.lcl',
        'gateway.tablet.brainfx.lcl',
        'admin.brainfx.lcl',
        'member.brainfx.lcl',
      ),
      'configFile' => 'config/config.local.yml',
    ),
    2 => array(
      'name'       => 'ENV_STAGING',
      'domains'    => array(
        'staging.gateway.360ax.brainfx.com',
        'staging.gateway.360ax.bfx.io',
        'staging.gateway.tablet.brainfx.com',
        'staging.admin.brainfx.com',
        'staging.member.brainfx.com',
        'staging.admin.bfx.io',
      ),
      'configFile' => 'config/config.staging.yml',
    ),
    3 => array(
      'name'       => 'ENV_PRODUCTION',
      'domains'    => array(
        // Gateway domain.
        'gateway.360ax.brainfx.com',
        // 360ax domain.
        '360ax.brainfx.com',
        // Gateway pre-launch domain on SQN.
        'gateway.360ax.bfx.io',
        // Gateway raw Public IP address on SQN.
        '162.250.169.251',
        // Gateway raw Local IP address on SQN.
        '10.200.200.5',
        'gateway.tablet.brainfx.com',
        'tablet.brainfx.com',
        'admin.brainfx.com',
        'member.brainfx.com',
      ),
      'configFile' => 'config/config.production.yml',
    ),
  );

  private $config = NULL;

  /**
   * Initial set up.
   */
  function __construct() {
    global $dbConnection;

    $this->setRuntimeEnv();
    $this->loadEnvConfig();

    if (!defined('DS')) {
      define('DS', DIRECTORY_SEPARATOR);
    }

    $dbConnectionString = "mysql:host={$this->config->dbConf['host']};dbname={$this->config->dbConf['database']};charset=utf8mb4";
    $dbConnection = new PDO($dbConnectionString, $this->config->dbConf['user'], $this->config->dbConf['password']);
  }

  /**
   * Determines the Runtime Environment (is this a local developer machine, the
   * staging server or the production server) based upon the host name detected
   * by PHP (i.e. $_SERVER['HTTP_HOST']).
   *
   * This value is used throughout the rest of the class in order to load and
   * set the configuration variables to be used throughout the rest of the
   * request/data saving process.
   */
  private function setRuntimeEnv() {
    $hostName = $_SERVER['HTTP_HOST'];

    foreach ($this->envLoadVars as $env => $var) {
      $envName = $var['name'];
      foreach ($var['domains'] as $testDomain) {
        if ($hostName == $testDomain) {
          $this->runEnv = $env;
          $this->runEnvName = $envName;
          return;
        }
      }
    }
  }

  /**
   * Load environment specific configuration from the appropriate
   * "config.ENV-TYPE.yml" file in the /config directory.
   *
   * Database credentials have been removed from this project's repository
   * files. They are now expected to be available in *.yml files whose path
   * is provided as $this->config->varsFile. This path can be relative or
   * absolute.
   *
   * The $this->config->varsFile is loaded by the YAML parser as an object and
   * the DB credentials are then injected into the "dbConf" array:
   * $this->config->dbConf.
   */
  private function loadEnvConfig() {
    $configFilePath = $this->envLoadVars[$this->runEnv]['configFile'];

    $this->config = $this->loadYamlObjFromFile($configFilePath);
    $vars = $this->loadYamlObjFromFile($this->config->varsFile);
    $this->config->dbConf['user'] = $vars->u;
    $this->config->dbConf['password'] = $vars->p;
  }

  /**
   * @return Object
   * Object representation of YAML variables loaded from the "config.{ENV}.yml"
   * for detected environment.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * @return Integer
   * The integer representing the type of runtime environment detected.
   */
  public function getRuntimeEnv () {
    return $this->runEnv;
  }

  /**
   * @return String
   * The string name of the runtime environment detected.
   */
  public function getRuntimeEnvName () {
    return $this->runEnvName;
  }

  /**
   * @param string $filePath
   *
   * @return object|bool
   */
  private function loadYamlObjFromFile($filePath) {
    try {
      return (object) Yaml::parse(file_get_contents($filePath));
    }
    catch (Exception $e) {
      $eol = PHP_EOL;
      echo "Was unable to load configuration file: {$filePath}. Please confirm that it exists.{$eol}";
      echo "Caught exception: {$e->getMessage()}.{$eol}";
    }

    return false;
  }
}
