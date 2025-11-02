<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class estarenergy extends eqLogic {
  const AUTH_URL = 'https://monitor.estarpower.com/platform/api/gateway/iam/auth_login';
  const DATA_URL = 'https://monitor.estarpower.com/platform/api/gateway/pvm-data/data_count_station_real_data';
  const TOKEN_MAX_AGE = 3600;
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('estarpower_password');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */
  
  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */
  
  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
   * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
   * lors de la création semi-automatique d'un post sur le forum community
   public static function getConfigForCommunity() {
      // Cette function doit retourner des infos complémentataires sous la forme d'un
      // string contenant les infos formatées en HTML.
      return "les infos essentiel de mon plugin";
   }
   */

  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    $infoCommands = array(
      'Pv_power',
      'Load_power',
      'Grid_power',
      'meter_b_in_eq',
      'meter_b_out_eq',
      'self_eq',
      'month_eq',
      'today_eq',
      'year_eq',
      'total_eq',
      'plant_tree',
      'co2_emission_reduction',
    );

    foreach ($infoCommands as $logicalId) {
      $this->createOrUpdateInfoCommand($logicalId, $logicalId);
    }

    $this->createOrUpdateActionCommand('refresh', __('Actualiser', __FILE__));
  }

  /**
   * Crée ou met à jour une commande info si elle n'existe pas encore.
   */
  protected function createOrUpdateInfoCommand($logicalId, $name) {
    $cmd = $this->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setType('info');
      $cmd->setSubType('numeric');
    }

    $cmd->setName(__($name, __FILE__));
    $cmd->setIsHistorized(1);
    $cmd->setIsVisible(1);
    $cmd->save();
  }

  /**
   * Crée ou met à jour une commande action si elle n'existe pas encore.
   */
  protected function createOrUpdateActionCommand($logicalId, $name) {
    $cmd = $this->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setType('action');
      $cmd->setSubType('other');
    }

    $cmd->setName($name);
    $cmd->setIsVisible(1);
    $cmd->save();
  }

  /**
   * Déclenche une actualisation manuelle de l'équipement.
   */
  public function refresh() {
    if (!$this->getIsEnable()) {
      log::add('estarenergy', 'debug', sprintf("%s désactivé, actualisation ignorée", $this->getHumanName(true, true)));
      return;
    }

    $stationId = trim((string) $this->getConfiguration('station_id'));
    if ($stationId === '') {
      $message = sprintf(
        __("L'identifiant de centrale est manquant pour %s", __FILE__),
        $this->getHumanName(true, true)
      );
      log::add('estarenergy', 'error', $message);
      message::add('estarenergy', $message);
      return;
    }

    $login = trim((string) config::byKey('estarpower_login', 'estarenergy'));
    $password = (string) config::byKey('estarpower_password', 'estarenergy');

    if ($login === '' || $password === '') {
      $message = sprintf(
        __("Identifiants Estar Power manquants pour %s", __FILE__),
        $this->getHumanName(true, true)
      );
      log::add('estarenergy', 'error', $message);
      message::add('estarenergy', $message);
      return;
    }

    log::add('estarenergy', 'info', __('Actualisation des données', __FILE__) . ' : ' . $this->getHumanName());

    try {
      $payload = $this->fetchStationData($login, $password, $stationId);
    } catch (Exception $e) {
      log::add('estarenergy', 'error', $e->getMessage());
      message::add('estarenergy', $e->getMessage());
      return;
    }

    if (!is_array($payload)) {
      log::add('estarenergy', 'warning', __('Aucune donnée reçue depuis l’API Estar Power', __FILE__));
      return;
    }

    $this->applyStationMetrics($payload);
    log::add('estarenergy', 'info', __('Données Estar Power mises à jour', __FILE__) . ' : ' . $this->getHumanName());
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*     * **********************Getteur Setteur*************************** */

  /**
   * @param string $login
   * @param string $password
   * @param string $stationId
   * @return array|null
   * @throws Exception
   */
  protected function fetchStationData($login, $password, $stationId) {
    $token = $this->readSavedToken();
    $cookieFile = $this->getCookieFilePath();

    if (!file_exists($cookieFile)) {
      touch($cookieFile);
    }

    if ($token === null) {
      log::add('estarenergy', 'debug', __('Aucun jeton valide en cache, tentative de connexion à l’API Estar Power', __FILE__));
      $token = $this->retrieveToken($login, $password, $cookieFile);
    }

    if ($token === null) {
      throw new Exception(__('Impossible de récupérer un jeton d’authentification', __FILE__));
    }

    $payload = $this->queryStationData($token, $cookieFile, $stationId);

    if (!is_array($payload) || (isset($payload['code']) && (int) $payload['code'] !== 200)) {
      $token = $this->retrieveToken($login, $password, $cookieFile, true);
      if ($token === null) {
        throw new Exception(__('Token invalide et échec de reconnexion', __FILE__));
      }

      $payload = $this->queryStationData($token, $cookieFile, $stationId);
      if (!is_array($payload) || (isset($payload['code']) && (int) $payload['code'] !== 200)) {
        throw new Exception(__('Données indisponibles après reconnexion à l’API', __FILE__));
      }
    }

    return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
  }

  protected function queryStationData($token, $cookieFile, $stationId) {
    $headers = array(
      'Accept: application/json, text/plain, */*',
      'Content-Type: application/json;charset=UTF-8',
      'User-Agent: Mozilla/5.0',
      'Cookie: estar_token=' . $token,
    );

    $payload = json_encode(array(
      'body' => array(
        'sid' => (int) $stationId,
        'mode' => 1,
        'date' => date('Y-m-d'),
      ),
      'WAITING_PROMISE' => true,
    ));

    $response = $this->sendCurlRequest(self::DATA_URL, $headers, $payload, $cookieFile);
    if ($response === null) {
      return null;
    }

    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
      log::add('estarenergy', 'error', 'Réponse JSON invalide lors de la récupération des données : ' . json_last_error_msg());
    }

    return $decoded;
  }

  protected function retrieveToken($login, $password, $cookieFile, $forceRefresh = false) {
    if (!$forceRefresh) {
      $token = $this->readSavedToken();
      if ($token !== null) {
        return $token;
      }
    }

    $headers = array(
      'Accept: application/json, text/plain, */*',
      'Accept-Language: fr,en-US;q=0.9,en;q=0.8,vi;q=0.7',
      'Connection: keep-alive',
      'Content-Type: application/json;charset=UTF-8',
      'Origin: https://monitor.estarpower.com',
      'Referer: https://monitor.estarpower.com/platform/login',
      'User-Agent: Mozilla/5.0',
    );

    $data = json_encode(array(
      'ERROR_BACK' => true,
      'LOAD' => array('loading' => true),
      'body' => array(
        'user_name' => $login,
        'password' => $password,
      ),
      'WAITING_PROMISE' => true,
    ));

    $response = $this->sendCurlRequest(self::AUTH_URL, $headers, $data, $cookieFile, true);
    if ($response === null) {
      return null;
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['message']) && stripos($decoded['message'], 'failed logins exceeds the daily maximum limit') !== false) {
      $this->handleDailyLoginFailureLimit($decoded['message']);
      return null;
    }

    if (!is_array($decoded) || !isset($decoded['data']['token'])) {
      log::add('estarenergy', 'error', __('Impossible d’extraire le token d’authentification', __FILE__));
      log::add('estarenergy', 'debug', sprintf(__('Réponse reçue lors de la récupération du token : %s', __FILE__), $response));
      return null;
    }

    $token = $decoded['data']['token'];
    $this->writeToken($token);

    log::add('estarenergy', 'debug', __('Nouveau token Estar Power récupéré', __FILE__));
    return $token;
  }

  protected function sendCurlRequest($url, array $headers, $payload, $cookieFile, $storeCookies = false) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);

    if ($storeCookies) {
      curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
    } else {
      curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
    }

    log::add('estarenergy', 'debug', sprintf(__('Appel HTTP vers %s', __FILE__), $url));

    $response = curl_exec($curl);
    if ($response === false) {
      log::add('estarenergy', 'error', sprintf('Erreur cURL (%s) : %s', $url, curl_error($curl)));
      curl_close($curl);
      return null;
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200) {
      log::add('estarenergy', 'error', sprintf('Erreur HTTP %d lors de l’appel %s', $httpCode, $url));
      log::add('estarenergy', 'debug', sprintf(__('Réponse HTTP en erreur (%d) : %s', __FILE__), $httpCode, $response));
      return null;
    }

    log::add('estarenergy', 'debug', sprintf(__('Réponse HTTP 200 reçue depuis %s', __FILE__), $url));

    return $response;
  }

  protected function applyStationMetrics(array $data) {
    $this->updateInfoIfPresent($data, 'today_eq', 'today_eq', __('Production du jour : %s Wh', __FILE__));
    $this->updateInfoIfPresent($data, 'month_eq', 'month_eq', __('Production du mois : %s Wh', __FILE__));
    $this->updateInfoIfPresent($data, 'year_eq', 'year_eq', __('Production de l’année : %s Wh', __FILE__));
    $this->updateInfoIfPresent($data, 'total_eq', 'total_eq', __('Production totale : %s Wh', __FILE__));
    $this->updateInfoIfPresent($data, 'co2_emission_reduction', 'co2_emission_reduction', __('Réduction CO2 : %s', __FILE__));
    $this->updateInfoIfPresent($data, 'plant_tree', 'plant_tree', __('Compensation carbone : %s arbres', __FILE__));

    if (isset($data['reflux_station_data']) && is_array($data['reflux_station_data'])) {
      $reflux = $data['reflux_station_data'];
      $this->updateInfoIfPresent($reflux, 'pv_power', 'Pv_power', __('Production panneau : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'load_power', 'Load_power', __('Consommation maison : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'grid_power', 'Grid_power', __('Puisage réseau : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'meter_b_in_eq', 'meter_b_in_eq', __('Depuis le réseau : %s Wh', __FILE__));
      $this->updateInfoIfPresent($reflux, 'meter_b_out_eq', 'meter_b_out_eq', __('Vers le réseau : %s Wh', __FILE__));
      $this->updateInfoIfPresent($reflux, 'self_eq', 'self_eq', __('Autoconsommation : %s Wh', __FILE__));
    }
  }

  protected function updateInfoIfPresent(array $source, $sourceKey, $logicalId, $logMessage = null) {
    if (!array_key_exists($sourceKey, $source)) {
      return;
    }

    $value = $source[$sourceKey];
    $cmd = $this->getCmd(null, $logicalId);
    if (is_object($cmd)) {
      $cmd->event($value);
    }

    if ($logMessage !== null) {
      log::add('estarenergy', 'debug', sprintf($logMessage, $value));
    }
  }

  protected function getTokenFilePath() {
    return $this->getStorageDirectory() . '/auth_token.json';
  }

  protected function getCookieFilePath() {
    return $this->getStorageDirectory() . '/cookies.txt';
  }

  protected function getStorageDirectory() {
    $directory = jeedom::getTmpFolder('estarenergy');
    if (!is_dir($directory)) {
      mkdir($directory, 0775, true);
    }

    return $directory;
  }

  protected function readSavedToken() {
    $file = $this->getTokenFilePath();
    if (!file_exists($file)) {
      log::add('estarenergy', 'debug', __('Fichier de cache du jeton absent', __FILE__));
      return null;
    }

    $rawContent = @file_get_contents($file);
    if ($rawContent === false || $rawContent === '') {
      log::add('estarenergy', 'debug', __('Impossible de lire le fichier de cache du jeton', __FILE__));
      return null;
    }

    $content = json_decode($rawContent, true);
    if (!is_array($content) || !isset($content['token'], $content['timestamp'])) {
      log::add('estarenergy', 'debug', __('Structure de cache du jeton invalide', __FILE__));
      return null;
    }

    if ((time() - (int) $content['timestamp']) > self::TOKEN_MAX_AGE) {
      log::add('estarenergy', 'debug', __('Jeton Estar Power expiré, une reconnexion est nécessaire', __FILE__));
      return null;
    }

    log::add('estarenergy', 'debug', __('Jeton Estar Power valide trouvé dans le cache', __FILE__));
    return $content['token'];
  }

  protected function writeToken($token) {
    $file = $this->getTokenFilePath();
    $directory = dirname($file);
    if (!is_dir($directory)) {
      mkdir($directory, 0775, true);
    }

    $written = @file_put_contents($file, json_encode(array(
      'token' => $token,
      'timestamp' => time(),
    )));

    if ($written === false) {
      log::add('estarenergy', 'error', __('Impossible d’écrire le fichier de cache du token Estar Power', __FILE__));
      return;
    }

    log::add('estarenergy', 'debug', sprintf(__('Token Estar Power enregistré dans %s', __FILE__), $file));
  }

  protected function handleDailyLoginFailureLimit($apiMessage) {
    $logMessage = __('Le nombre maximum de tentatives de connexion Estar Power a été atteint. Les actualisations automatiques sont suspendues jusqu’à réactivation manuelle.', __FILE__);
    $detailedMessage = $logMessage . ' (' . $apiMessage . ')';
    log::add('estarenergy', 'error', $detailedMessage);
    message::add('estarenergy', $detailedMessage);

    config::save('estarpower_refresh', '', 'estarenergy');
  }
}

class estarenergyCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if (!is_object($eqLogic)) {
      throw new Exception(__('Equipement introuvable', __FILE__));
    }

    switch ($this->getLogicalId()) {
      case 'refresh':
        $eqLogic->refresh();
        return true;
    }

    return null;
  }

  /*     * **********************Getteur Setteur*************************** */
}
