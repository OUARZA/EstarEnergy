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
  const MODULE_DAY_DATA_URL = 'https://neapi.hoymiles.com/pvm-data/api/0/module/data/down_module_day_data';
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
    $this->setIsEnable(1);
    $this->setIsVisible(1);
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
    $renamedLogicalIds = array(
      'production_kwh' => 'production',
      'consumption_kwh' => 'consumption',
      'auto_production_kwh' => 'auto_production_rate',
      'auto_consumption_ratio' => 'auto_consumption_rate',
      'daily_sale_revenue' => 'sale_revenue',
      'daily_purchase_cost' => 'purchase_cost',
    );

    foreach ($renamedLogicalIds as $legacyId => $newId) {
      $legacyCmd = $this->getCmd(null, $legacyId);
      if (!is_object($legacyCmd)) {
        continue;
      }

      $existingTarget = $this->getCmd(null, $newId);
      if (is_object($existingTarget)) {
        continue;
      }

      $legacyCmd->setLogicalId($newId);
      $legacyCmd->save();
    }

    $infoCommands = array(
      'Pv_power' => array('name' => 'Production photovoltaïque', 'unit' => 'W'),
      'Load_power' => array('name' => 'Puissance consommée', 'unit' => 'W'),
      'Grid_power' => array('name' => 'Puissance réseaux', 'unit' => 'W'),
      'meter_b_in_eq' => array('name' => 'Énergie depuis le réseau', 'unit' => 'W'),
      'meter_b_out_eq' => array('name' => 'Énergie vers le réseau', 'unit' => 'W'),
      'self_eq' => array('name' => 'Auto-consommation', 'unit' => 'W'),
      'month_eq' => array('name' => 'Production du mois', 'unit' => 'W'),
      'today_eq' => array('name' => 'Production du jour', 'unit' => 'kWh'),
      'year_eq' => array('name' => 'Production de l\'année', 'unit' => 'W'),
      'total_eq' => array('name' => 'Production totale', 'unit' => 'W'),
      'purchase_cost' => array('name' => 'Achat', 'unit' => '€', 'isHistorized' => 0),
      'sale_revenue' => array('name' => 'Vente', 'unit' => '€', 'isHistorized' => 0),
      'annual_revenue' => array('name' => 'Revenu par an', 'unit' => '€'),
      'total_revenue' => array('name' => 'Revenu total', 'unit' => '€'),
      'production' => array('name' => 'Production', 'unit' => 'W'),
      'consumption' => array('name' => 'Consommation', 'unit' => 'W'),
      'auto_production_rate' => array('name' => 'Taux autoproduction', 'unit' => '%', 'isHistorized' => 0),
      'auto_consumption_rate' => array('name' => 'Taux autoconsommation', 'unit' => '%', 'isHistorized' => 0),
      'plant_tree' => array('name' => 'Compensation des émissions', 'unit' => __('arbres', __FILE__)),
      'co2_emission_reduction' => array('name' => 'Réduction des émissions', 'unit' => 'T'),
      'last_refresh' => array('name' => 'Dernière actualisation', 'unit' => '', 'subType' => 'string', 'isHistorized' => 0),
      'module_day_data' => array('name' => 'Courbe modules (JSON)', 'unit' => '', 'subType' => 'string', 'isHistorized' => 0, 'isVisible' => 0),
    );

    foreach ($infoCommands as $logicalId => $properties) {
      $subType = isset($properties['subType']) ? $properties['subType'] : 'numeric';
      $isHistorized = array_key_exists('isHistorized', $properties) ? (int) $properties['isHistorized'] : 1;
      $isVisible = array_key_exists('isVisible', $properties) ? (int) $properties['isVisible'] : 1;
      $this->createOrUpdateInfoCommand(
        $logicalId,
        $properties['name'],
        $properties['unit'],
        $subType,
        $isHistorized,
        $isVisible
      );
    }

    $this->createOrUpdateActionCommand('refresh', 'Actualiser');
  }

  /**
   * Crée ou met à jour une commande info avec les propriétés attendues.
   */
  protected function createOrUpdateInfoCommand($logicalId, $name, $unit = '', $subType = 'numeric', $isHistorized = 1, $isVisible = 1) {
    $cmd = $this->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
    }

    $cmd->setType('info');
    $cmd->setSubType($subType);
    $cmd->setName(__($name, __FILE__));
    $cmd->setUnite(is_string($unit) ? trim($unit) : '');
    $cmd->setIsHistorized((int) $isHistorized);
    $cmd->setIsVisible((int) $isVisible);
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

    $cmd->setName(__($name, __FILE__));
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
    $this->refreshModuleDayData($login, $password, $stationId);
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
   * Récupère et décode les données journalières détaillées (point 5 minutes) exposées
   * par l'API Hoymiles. Le décodage du corps binaire se fait via un parseur protobuf
   * générique afin de rester compatible avec les évolutions du schéma.
   */
  protected function refreshModuleDayData($login, $password, $stationId) {
    if ((int) $this->getConfiguration('enable_module_day_data', 0) !== 1) {
      return;
    }

    $command = $this->getCmd(null, 'module_day_data');
    if (!is_object($command)) {
      return;
    }

    try {
      $moduleData = $this->fetchModuleDayData($login, $password, $stationId);
    } catch (Exception $e) {
      log::add('estarenergy', 'error', sprintf(__('Données journalières module indisponibles : %s', __FILE__), $e->getMessage()));
      return;
    }

    if (!is_array($moduleData) || count($moduleData) === 0) {
      log::add('estarenergy', 'debug', __('Aucune donnée journalière module décodée', __FILE__));
      return;
    }

    $json = json_encode($moduleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
      log::add('estarenergy', 'error', __('Impossible de sérialiser les données journalières module', __FILE__));
      return;
    }

    $command->event($json);
    log::add('estarenergy', 'debug', sprintf(__('Données journalières module synchronisées (%d entrées)', __FILE__), isset($moduleData['timeline']) ? count($moduleData['timeline']) : 0));
  }

  protected function fetchModuleDayData($login, $password, $stationId) {
    $cookieFile = $this->getCookieFilePath();
    if (!file_exists($cookieFile)) {
      touch($cookieFile);
    }

    $token = $this->readSavedToken();
    if ($token === null) {
      $token = $this->retrieveToken($login, $password, $cookieFile, true);
    }

    if ($token === null) {
      throw new Exception(__('Impossible de récupérer le token Estar Power pour les données module', __FILE__));
    }

    $headers = array(
      'Accept: application/json, text/plain, */*',
      'Accept-Encoding: gzip, deflate, br, zstd',
      'Content-Type: application/json',
      'User-Agent: Mozilla/5.0',
      'Authorization: ' . $token,
      'Origin: https://monitor.estarpower.com',
      'Referer: https://monitor.estarpower.com/',
      'language: fr-fr',
    );

    $payload = json_encode(array(
      'sid' => (int) $stationId,
      'date' => date('Y-m-d'),
    ));

    $response = $this->sendCurlRequest(self::MODULE_DAY_DATA_URL, $headers, $payload, $cookieFile, false, true);
    if ($response === null) {
      return null;
    }

    return $this->decodeModuleDayResponse($response);
  }

  protected function decodeModuleDayResponse($response) {
    $payload = @gzdecode($response);
    if ($payload === false || $payload === '') {
      $payload = $response;
    }

    $decoded = $this->decodeProtobufMessage($payload);
    $summary = $this->summarizeModuleDayData($decoded);
    if ((int) $this->getConfiguration('store_module_raw_payload', 0) === 1) {
      $summary['payload_base64'] = base64_encode($payload);
    }
    $summary = $this->applyModuleAliases($summary);

    return array(
      'date' => $summary['date'],
      'time_slots' => $summary['time_slots'],
      'series_overview' => $summary['series_overview'],
      'timeline' => $summary['timeline'],
      'payload_bytes' => strlen($payload),
    );
  }

  protected function summarizeModuleDayData(array $decoded) {
    $timeSlots = $this->collectStringsMatching($decoded, '/^\d{2}:\d{2}$/');
    $timeSlots = array_values(array_unique($timeSlots));
    $date = $this->findFirstStringMatching($decoded, '/^\d{4}-\d{2}-\d{2}$/');

    $series = $this->collectNumericSeries($decoded);
    $series = array_values(array_filter($series, function ($serie) {
      return isset($serie['values']) && is_array($serie['values']) && count($serie['values']) > 0;
    }));

    $timeline = $this->buildModuleTimeline($timeSlots, $series);
    $seriesOverview = array_map(function ($serie) {
      $values = $serie['values'];
      return array(
        'path' => $serie['path'],
        'count' => count($values),
        'min' => min($values),
        'max' => max($values),
        'missing' => isset($serie['missing']) ? $serie['missing'] : 0,
      );
    }, $series);

    return array(
      'date' => $date,
      'time_slots' => $timeSlots,
      'series_overview' => $seriesOverview,
      'timeline' => $timeline,
    );
  }

  protected function buildModuleTimeline(array $timeSlots, array $series) {
    $timeline = array();
    $slotCount = count($timeSlots);

    for ($i = 0; $i < $slotCount; $i++) {
      $entry = array('time' => $timeSlots[$i]);
      foreach ($series as $index => $serie) {
        $value = array_key_exists($i, $serie['values']) ? $serie['values'][$i] : null;
        if ($value === null && !isset($series[$index]['missing'])) {
          $series[$index]['missing'] = 0;
        }
        if ($value === null) {
          $series[$index]['missing'] = isset($series[$index]['missing']) ? $series[$index]['missing'] + 1 : 1;
        }
        $entry[$serie['path']] = $value;
      }
      $timeline[] = $entry;
    }

    return $timeline;
  }

  protected function collectNumericSeries($data, $path = '') {
    $series = array();

    if (is_array($data)) {
      $isList = array_keys($data) === range(0, count($data) - 1);
      if ($isList && $this->arrayIsNumeric($data)) {
        $series[] = array(
          'path' => $path === '' ? 'values' : $path,
          'values' => array_map('floatval', $data),
        );
        return $series;
      }

      foreach ($data as $key => $value) {
        $childPath = $path === '' ? (string) $key : $path . '.' . $key;
        $series = array_merge($series, $this->collectNumericSeries($value, $childPath));
      }
    }

    return $series;
  }

  protected function arrayIsNumeric($data) {
    if (!is_array($data)) {
      return false;
    }

    foreach ($data as $value) {
      if (!is_numeric($value)) {
        return false;
      }
    }

    return true;
  }

  protected function collectStringsMatching($data, $pattern, &$result = array()) {
    if (is_string($data) && preg_match($pattern, $data)) {
      $result[] = $data;
      return $result;
    }

    if (is_array($data)) {
      foreach ($data as $value) {
        $this->collectStringsMatching($value, $pattern, $result);
      }
    }

    return $result;
  }

  protected function findFirstStringMatching($data, $pattern) {
    $matches = $this->collectStringsMatching($data, $pattern);
    if (count($matches) === 0) {
      return '';
    }

    return $matches[0];
  }

  protected function decodeProtobufMessage($payload, $depth = 0) {
    $length = strlen($payload);
    $offset = 0;
    $result = array();

    while ($offset < $length) {
      list($key, $offset) = $this->decodeVarint($payload, $offset);
      $fieldNumber = $key >> 3;
      $wireType = $key & 0x07;

      switch ($wireType) {
        case 0:
          list($value, $offset) = $this->decodeVarint($payload, $offset);
          break;
        case 1:
          $value = $this->decodeFixed64($payload, $offset);
          $offset += 8;
          break;
        case 2:
          list($value, $offset) = $this->decodeLengthDelimited($payload, $offset, $depth);
          break;
        case 5:
          $value = $this->decodeFixed32($payload, $offset);
          $offset += 4;
          break;
        default:
          $value = null;
          $offset = $length;
          break;
      }

      if (!array_key_exists($fieldNumber, $result)) {
        $result[$fieldNumber] = $value;
      } else {
        if (!is_array($result[$fieldNumber]) || array_keys($result[$fieldNumber]) !== range(0, count($result[$fieldNumber]) - 1)) {
          $result[$fieldNumber] = array($result[$fieldNumber]);
        }
        $result[$fieldNumber][] = $value;
      }
    }

    return $result;
  }

  protected function decodeVarint($payload, $offset) {
    $result = 0;
    $shift = 0;
    $length = strlen($payload);

    while ($offset < $length) {
      $byte = ord($payload[$offset]);
      $offset++;
      $result |= (($byte & 0x7f) << $shift);
      if (($byte & 0x80) === 0) {
        break;
      }
      $shift += 7;
    }

    return array($result, $offset);
  }

  protected function decodeLengthDelimited($payload, $offset, $depth) {
    list($length, $newOffset) = $this->decodeVarint($payload, $offset);
    $segment = substr($payload, $newOffset, $length);
    $offset = $newOffset + $length;

    if ($segment === false) {
      return array('', $offset);
    }

    if ($this->isPrintableUtf8($segment)) {
      return array($segment, $offset);
    }

    $packedFloats = $this->decodePackedFloatArray($segment);
    if ($packedFloats !== null) {
      return array($packedFloats, $offset);
    }

    $packedFixed32 = $this->decodePackedFixed32Array($segment);
    if ($packedFixed32 !== null) {
      return array($packedFixed32, $offset);
    }

    $packedVarints = $this->decodePackedVarintArray($segment);
    if ($packedVarints !== null) {
      return array($packedVarints, $offset);
    }

    if ($depth < 8) {
      $nested = $this->decodeProtobufMessage($segment, $depth + 1);
      if (count($nested) > 0) {
        return array($nested, $offset);
      }
    }

    return array(base64_encode($segment), $offset);
  }

  protected function decodePackedFloatArray($segment) {
    $length = strlen($segment);
    if ($length === 0 || ($length % 4) !== 0) {
      return null;
    }

    $floats = array();
    $chunks = str_split($segment, 4);
    foreach ($chunks as $chunk) {
      $unpacked = unpack('g', $chunk);
      if (!is_array($unpacked)) {
        return null;
      }
      $floats[] = $unpacked[1];
    }

    return $floats;
  }

  protected function decodePackedFixed32Array($segment) {
    $length = strlen($segment);
    if ($length === 0 || ($length % 4) !== 0) {
      return null;
    }

    $ints = array();
    $chunks = str_split($segment, 4);
    foreach ($chunks as $chunk) {
      $unpacked = unpack('V', $chunk);
      if (!is_array($unpacked)) {
        return null;
      }
      $ints[] = (int) $unpacked[1];
    }

    return $ints;
  }

  protected function decodePackedVarintArray($segment) {
    $length = strlen($segment);
    if ($length === 0) {
      return null;
    }

    $offset = 0;
    $values = array();
    while ($offset < $length) {
      list($value, $offset) = $this->decodeVarint($segment, $offset);
      $values[] = (int) $value;
    }

    return $values;
  }

  /**
   * Applique les alias configurés par l'utilisateur sur les séries module (chemins protobuf)
   * pour produire un JSON plus lisible (timeline + series_overview).
   */
  protected function applyModuleAliases(array $moduleData) {
    $aliases = $this->parseModuleSeriesAliases((string) $this->getConfiguration('module_series_alias', ''));
    if (count($aliases) === 0) {
      return $moduleData;
    }

    // Ajout des alias dans series_overview
    if (isset($moduleData['series_overview']) && is_array($moduleData['series_overview'])) {
      foreach ($moduleData['series_overview'] as &$serie) {
        if (!isset($serie['path'])) {
          continue;
        }
        if (isset($aliases[$serie['path']])) {
          $serie['label'] = $aliases[$serie['path']];
        }
      }
      unset($serie);
    }

    // Renommage des clés dans le timeline
    if (isset($moduleData['timeline']) && is_array($moduleData['timeline'])) {
      $newTimeline = array();
      foreach ($moduleData['timeline'] as $entry) {
        if (!is_array($entry)) {
          continue;
        }
        $newEntry = array();
        foreach ($entry as $key => $value) {
          if ($key === 'time') {
            $newEntry[$key] = $value;
            continue;
          }
          $alias = array_key_exists($key, $aliases) ? $aliases[$key] : $key;
          $newEntry[$alias] = $value;
        }
        $newTimeline[] = $newEntry;
      }
      $moduleData['timeline'] = $newTimeline;
    }

    $moduleData['applied_aliases'] = $aliases;

    return $moduleData;
  }

  /**
   * Parse les alias fournis par l'utilisateur.
   * Formats acceptés :
   * - JSON objet : {"3.0.2.5":"Panneau 1"}
   * - Lignes clé=alias, une par ligne
   */
  protected function parseModuleSeriesAliases($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
      return array();
    }

    // Essai JSON
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
      $mapping = array();
      foreach ($decoded as $key => $value) {
        $key = trim((string) $key);
        $value = trim((string) $value);
        if ($key !== '' && $value !== '') {
          $mapping[$key] = $value;
        }
      }
      if (count($mapping) > 0) {
        return $mapping;
      }
    }

    // Fallback lignes clé=alias
    $mapping = array();
    $lines = preg_split('/\\r?\\n/', $raw);
    foreach ($lines as $line) {
      if (strpos($line, '=') === false) {
        continue;
      }
      list($key, $value) = explode('=', $line, 2);
      $key = trim($key);
      $value = trim($value);
      if ($key !== '' && $value !== '') {
        $mapping[$key] = $value;
      }
    }

    return $mapping;
  }

  protected function decodeFixed32($payload, $offset) {
    $slice = substr($payload, $offset, 4);
    if ($slice === false || strlen($slice) < 4) {
      return null;
    }

    $unpacked = unpack('V', $slice);
    if (!is_array($unpacked)) {
      return null;
    }

    return $unpacked[1];
  }

  protected function decodeFixed64($payload, $offset) {
    $slice = substr($payload, $offset, 8);
    if ($slice === false || strlen($slice) < 8) {
      return null;
    }

    $parts = unpack('V2', $slice);
    if (!is_array($parts)) {
      return null;
    }

    return $parts[1] + ($parts[2] << 32);
  }

  protected function isPrintableUtf8($value) {
    if (!is_string($value)) {
      return false;
    }

    if (!function_exists('mb_detect_encoding')) {
      return false;
    }

    if (mb_detect_encoding($value, 'UTF-8', true) === false) {
      return false;
    }

    $printable = preg_replace('/[[:print:][:space:]]/u', '', $value);
    return $printable === '';
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

  protected function sendCurlRequest($url, array $headers, $payload, $cookieFile, $storeCookies = false, $acceptCompressed = false) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    if ($acceptCompressed) {
      curl_setopt($curl, CURLOPT_ENCODING, '');
    }

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
    $this->updateInfoIfPresent($data, 'today_eq', 'today_eq', __('Production du jour : %s kWh', __FILE__), function ($value) {
      $numeric = $this->normalizeNumericValue($value);
      if ($numeric === null) {
        return null;
      }

      return $numeric / 1000.0;
    });
    $this->updateInfoIfPresent($data, 'month_eq', 'month_eq', __('Production du mois : %s W', __FILE__));
    $this->updateInfoIfPresent($data, 'year_eq', 'year_eq', __('Production de l’année : %s W', __FILE__));
    $this->updateInfoIfPresent($data, 'total_eq', 'total_eq', __('Production totale : %s W', __FILE__));
    $this->updateInfoIfPresent($data, 'co2_emission_reduction', 'co2_emission_reduction', __('Réduction CO2 : %s T', __FILE__));
    $this->updateInfoIfPresent($data, 'plant_tree', 'plant_tree', __('Compensation des émissions : %s arbres', __FILE__));

    $reflux = array();
    if (isset($data['reflux_station_data']) && is_array($data['reflux_station_data'])) {
      $reflux = $data['reflux_station_data'];
      $this->updateInfoIfPresent($reflux, 'pv_power', 'Pv_power', __('Production photovoltaïque : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'load_power', 'Load_power', __('Puissance consommée : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'grid_power', 'Grid_power', __('Puissance réseaux : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'meter_b_in_eq', 'meter_b_in_eq', __('Énergie depuis le réseau : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'meter_b_out_eq', 'meter_b_out_eq', __('Énergie vers le réseau : %s W', __FILE__));
      $this->updateInfoIfPresent($reflux, 'self_eq', 'self_eq', __('Auto-consommation : %s W', __FILE__));
    }

    $this->updateEnergyCalculations($data, $reflux);
    $this->updateRevenueCalculations($data, $reflux);

    $this->updateLastRefreshCommand();
  }

  protected function updateInfoIfPresent(array $source, $sourceKey, $logicalId, $logMessage = null, ?callable $transform = null) {
    if (!array_key_exists($sourceKey, $source)) {
      return;
    }

    $value = $source[$sourceKey];
    if ($transform !== null) {
      $value = $transform($value);
      if ($value === null) {
        return;
      }
    }
    $cmd = $this->getCmd(null, $logicalId);
    if (is_object($cmd)) {
      $cmd->event($value);
    }

    if ($logMessage !== null) {
      log::add('estarenergy', 'debug', sprintf($logMessage, $value));
    }
  }

  protected function updateLastRefreshCommand() {
    $cmd = $this->getCmd(null, 'last_refresh');
    if (!is_object($cmd)) {
      return;
    }

    $cmd->event(date('Y-m-d H:i:s'));
  }

  protected function updateEnergyCalculations(array $data, array $reflux) {
    $importedEnergy = $this->getNumericFromArray($reflux, 'meter_b_in_eq');
    $exportedEnergy = $this->getNumericFromArray($reflux, 'meter_b_out_eq');
    $selfConsumptionEnergy = $this->getNumericFromArray($reflux, 'self_eq');

    if ($selfConsumptionEnergy !== null || $exportedEnergy !== null) {
      $production = ($selfConsumptionEnergy !== null ? $selfConsumptionEnergy : 0.0)
        + ($exportedEnergy !== null ? $exportedEnergy : 0.0);
      $this->eventNumericCommand('production', $production);

      if ($production > 0.0) {
        $autoProductionRate = ($selfConsumptionEnergy !== null ? $selfConsumptionEnergy : 0.0) / $production;
      } else {
        $autoProductionRate = 0.0;
      }

      $this->eventNumericCommand('auto_production_rate', $autoProductionRate * 100.0, 1);
    }

    if ($selfConsumptionEnergy !== null || $importedEnergy !== null) {
      $consumption = ($selfConsumptionEnergy !== null ? $selfConsumptionEnergy : 0.0)
        + ($importedEnergy !== null ? $importedEnergy : 0.0);
      $this->eventNumericCommand('consumption', $consumption);

      if ($consumption > 0.0) {
        $autoConsumptionRate = ($selfConsumptionEnergy !== null ? $selfConsumptionEnergy : 0.0) / $consumption;
      } else {
        $autoConsumptionRate = 0.0;
      }

      $this->eventNumericCommand('auto_consumption_rate', $autoConsumptionRate * 100.0, 1);
    }
  }

  protected function updateRevenueCalculations(array $data, array $reflux) {
    $purchasePrice = (float) config::byKey('estarpower_purchase_price_ht', 'estarenergy', 0.0);
    $salePrice = (float) config::byKey('estarpower_sale_price_ht', 'estarenergy', 0.0);

    $importedWh = $this->getNumericFromArray($reflux, 'meter_b_in_eq');
    $exportedWh = $this->getNumericFromArray($reflux, 'meter_b_out_eq');
    $annualProductionWh = $this->getNumericFromArray($data, 'year_eq');
    $totalProductionWh = $this->getNumericFromArray($data, 'total_eq');

    if ($importedWh !== null) {
      $dailyCost = ($importedWh / 1000.0) * $purchasePrice;
      $this->eventNumericCommand('purchase_cost', $dailyCost, 2);
    }

    if ($exportedWh !== null) {
      $dailyIncome = ($exportedWh / 1000.0) * $salePrice;
      $this->eventNumericCommand('sale_revenue', $dailyIncome, 2);
    }

    if ($annualProductionWh !== null) {
      $annualRevenue = ($annualProductionWh / 1000.0) * $salePrice;
      $this->eventNumericCommand('annual_revenue', $annualRevenue, 2);
    }

    if ($totalProductionWh !== null) {
      $totalRevenue = ($totalProductionWh / 1000.0) * $salePrice;
      $this->eventNumericCommand('total_revenue', $totalRevenue, 2);
    }
  }

  protected function getNumericFromArray(array $source, $key) {
    if (!array_key_exists($key, $source)) {
      return null;
    }

    return $this->normalizeNumericValue($source[$key]);
  }

  protected function eventNumericCommand($logicalId, $value, $precision = 3) {
    $cmd = $this->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
      return;
    }

    if ($value === null) {
      return;
    }

    if (is_numeric($value)) {
      $cmd->event(round((float) $value, $precision));
      return;
    }

    $cmd->event($value);
  }

  protected function normalizeNumericValue($value) {
    if (is_numeric($value)) {
      return (float) $value;
    }

    if (is_string($value)) {
      $normalized = str_replace(',', '.', trim($value));
      if ($normalized === '') {
        return null;
      }

      if (is_numeric($normalized)) {
        return (float) $normalized;
      }
    }

    return null;
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
