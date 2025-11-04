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
  const REFRESH_CRON_OPTIONS = array(
    'cron5' => '*/5 * * * *',
    'cron10' => '*/10 * * * *',
    'cron30' => '*/30 * * * *',
    'cronHourly' => '0 * * * *',
  );
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
  * Fonction exécutée automatiquement par le cron "CheckUpdate"
  */
  public static function CheckUpdate() {
    $eqLogics = eqLogic::byType(__CLASS__, true);

    if (!is_array($eqLogics) || count($eqLogics) === 0) {
      log::add('estarenergy', 'debug', __('Aucun équipement actif à actualiser (CheckUpdate)', __FILE__));
      return;
    }

    log::add('estarenergy', 'debug', sprintf(__('Déclenchement du cron CheckUpdate pour %d équipement(s)', __FILE__), count($eqLogics)));

    foreach ($eqLogics as $eqLogic) {
      try {
        $eqLogic->refresh();
      } catch (Exception $e) {
        log::add('estarenergy', 'error', sprintf(__('Erreur lors de l’actualisation %s : %s', __FILE__), $eqLogic->getHumanName(true, true), $e->getMessage()));
      }
    }
  }

  public static function postConfig_update() {
    self::synchronizeRefreshCrons();
  }

  public static function synchronizeRefreshCrons($selected = null) {
    if ($selected === null) {
      $selected = trim((string) config::byKey('estarpower_refresh', 'estarenergy', ''));
    } else {
      $selected = trim((string) $selected);
    }
    self::removeLegacyCrons();

    if ($selected === '' || !array_key_exists($selected, self::REFRESH_CRON_OPTIONS)) {
      $cron = cron::byClassAndFunction(__CLASS__, 'CheckUpdate');
      if (is_object($cron)) {
        try {
          $cron->remove();
          log::add('estarenergy', 'info', __('Tâche cron CheckUpdate supprimée', __FILE__));
        } catch (Exception $e) {
          log::add('estarenergy', 'error', sprintf(__('Impossible de supprimer le cron CheckUpdate : %s', __FILE__), $e->getMessage()));
        }
      }
      log::add('estarenergy', 'info', __('Rafraîchissement automatique désactivé', __FILE__));
      return;
    }

    try {
      $cron = cron::byClassAndFunction(__CLASS__, 'CheckUpdate');
      if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass(__CLASS__);
        $cron->setFunction('CheckUpdate');
      }

      $cron->setSchedule(self::REFRESH_CRON_OPTIONS[$selected]);
      $cron->setTimeout(1440);
      $cron->setDeamon(0);
      $cron->setEnable(1);
      $cron->save();

      log::add('estarenergy', 'info', sprintf(__('Rafraîchissement automatique configuré (%s)', __FILE__), $selected));
    } catch (Exception $e) {
      log::add('estarenergy', 'error', sprintf(__('Impossible de configurer le cron CheckUpdate : %s', __FILE__), $e->getMessage()));
    }
  }

  protected static function removeLegacyCrons() {
    $legacyFunctions = array('cron5', 'cron10', 'cron15', 'cron30', 'cronHourly', 'cronDaily');

    foreach ($legacyFunctions as $function) {
      try {
        $cron = cron::byClassAndFunction(__CLASS__, $function);
        if (is_object($cron)) {
          $cron->remove();
        }
      } catch (Exception $e) {
        log::add('estarenergy', 'debug', sprintf(__('Impossible de supprimer l’ancien cron %s : %s', __FILE__), $function, $e->getMessage()));
      }
    }
  }
  
  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  public static function postConfig_estarpower_refresh($value) {
    self::synchronizeRefreshCrons($value);
    return $value;
  }

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
      'Pv_power' => array('name' => 'Puissance photovoltaïque (Pv_power)', 'unit' => 'W'),
      'Load_power' => array('name' => 'Consommation habitation (Load_power)', 'unit' => 'W'),
      'Grid_power' => array('name' => 'Puisage réseau (Grid_power)', 'unit' => 'W'),
      'meter_b_in_eq' => array('name' => 'Énergie importée du réseau (meter_b_in_eq)', 'unit' => 'Wh'),
      'meter_b_out_eq' => array('name' => 'Énergie injectée vers le réseau (meter_b_out_eq)', 'unit' => 'Wh'),
      'self_eq' => array('name' => 'Autoconsommation (self_eq)', 'unit' => 'Wh'),
      'month_eq' => array('name' => 'Production mensuelle (month_eq)', 'unit' => 'Wh'),
      'today_eq' => array('name' => 'Production du jour (today_eq)', 'unit' => 'Wh'),
      'year_eq' => array('name' => 'Production annuelle (year_eq)', 'unit' => 'Wh'),
      'total_eq' => array('name' => 'Production totale (total_eq)', 'unit' => 'Wh'),
      'plant_tree' => array('name' => 'Compensation carbone (plant_tree)', 'unit' => __('arbres', __FILE__)),
      'co2_emission_reduction' => array('name' => 'Réduction des émissions de CO₂ (co2_emission_reduction)', 'unit' => 'kg'),
    );

    foreach ($infoCommands as $logicalId => $properties) {
      $this->createOrUpdateInfoCommand($logicalId, $properties['name'], $properties['unit']);
    }

    $this->createOrUpdateActionCommand('refresh', __('Actualiser', __FILE__));
  }

  /**
   * Crée ou met à jour une commande info si elle n'existe pas encore.
   */
  protected function createOrUpdateInfoCommand($logicalId, $name, $unit = '') {
    $cmd = $this->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setType('info');
      $cmd->setSubType('numeric');
    }

    $cmd->setName(__($name, __FILE__));
    $cmd->setUnite(is_string($unit) ? trim($unit) : '');
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
   * Récupère les données de la centrale, en réutilisant la logique
   * de ton bloc scénario (lecture token → login → appel API → relogin si code != 200).
   *
   * @param string $login
   * @param string $password
   * @param string $stationId
   * @return array|null
   * @throws Exception
   */
  protected function fetchStationData($login, $password, $stationId) {
    $cookieFile = $this->getCookieFilePath();

    // On s'assure que le fichier de cookies existe
    if (!file_exists($cookieFile)) {
      touch($cookieFile);
    }

    // 1. Lecture du token déjà sauvegardé (équivalent à read_saved_token)
    $token = $this->readSavedToken();
    if ($token === null) {
      log::add('estarenergy', 'debug', __('Aucun token valide trouvé. Connexion en cours...', __FILE__));
      // Équivalent à get_token($scenario, $username, $password, $token_file, $cookie_file)
      $token = $this->retrieveToken($login, $password, $cookieFile);
    }

    if ($token === null) {
      // Équivalent à "Erreur critique : Aucun token disponible, arrêt du traitement."
      throw new Exception(__('Erreur critique : Aucun token Estar Power disponible', __FILE__));
    }

    // 2. Récupération des données (équivalent à get_data + json_decode)
    $payload = $this->queryStationData($token, $cookieFile, $stationId);
    log::add('estarenergy', 'debug', 'Données brutes Estar Power : ' . print_r($payload, true));

    // Si pas de payload ou code != 200, on re-tente une connexion comme dans ton bloc
    if (!is_array($payload) || (isset($payload['code']) && (int) $payload['code'] !== 200)) {
      log::add('estarenergy', 'debug', __('Token peut-être expiré ou invalide. Nouvelle tentative de connexion...', __FILE__));

      $token = $this->retrieveToken($login, $password, $cookieFile, true);
      if ($token === null) {
        // Équivalent à "Erreur : Reconnexion impossible. Données non récupérées."
        throw new Exception(__('Erreur : Reconnexion Estar Power impossible. Données non récupérées.', __FILE__));
      }

      $payload = $this->queryStationData($token, $cookieFile, $stationId);
      log::add('estarenergy', 'debug', 'Données brutes Estar Power (après reconnexion) : ' . print_r($payload, true));

      if (!is_array($payload) || (isset($payload['code']) && (int) $payload['code'] !== 200)) {
        // Équivalent à "Erreur : Données toujours indisponibles après reconnexion."
        throw new Exception(__('Erreur : Données Estar Power toujours indisponibles après reconnexion.', __FILE__));
      }
    }

    // Dans l’API Estar, les vraies données sont dans "data"
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

  /**
   * Encode le mot de passe comme le site Estar :
   * md5(password) + '.' + base64(sha256(password))
   */
  protected function encodeEstarPassword($password) {
    $password = (string) $password;

    if ($password === '') {
      return '';
    }

    $md5hex = md5($password);
    $sha256_b64 = base64_encode(hash('sha256', $password, true));

    return $md5hex . '.' . $sha256_b64;
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

    // Encodage du mot de passe selon le format observé côté client
    $encodedPassword = $this->encodeEstarPassword($password);

    $data = json_encode(array(
      'ERROR_BACK' => true,
      'LOAD' => array('loading' => true),
      'body' => array(
        'user_name' => $login,
        'password' => $encodedPassword,
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
    self::synchronizeRefreshCrons('');
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
