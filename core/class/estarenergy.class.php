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
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
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
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
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
    $this->createOrUpdateInfoCmd('raw_data', __('Dernière réponse brute', __FILE__));
    $this->createOrUpdateInfoCmd('times', __('Créneaux horaires détectés', __FILE__));
    $this->createOrUpdateInfoCmd('queried_date', __('Date interrogée', __FILE__));
    $this->createOrUpdateActionCmd('refresh', __('Rafraîchir les données', __FILE__));
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /**
   * Appelle l'endpoint Hoymiles pour récupérer les données journalières d'un module.
   *
   * @param array $options
   * @return array
   * @throws Exception
   */
  public static function fetchModuleDayData($options = array()) {
    $moduleId = isset($options['moduleId']) ? $options['moduleId'] : (isset($options['module_id']) ? $options['module_id'] : null);
    $moduleSn = isset($options['moduleSn']) ? $options['moduleSn'] : (isset($options['module_sn']) ? $options['module_sn'] : null);
    $date = isset($options['date']) ? $options['date'] : date('Y-m-d');

    if ($moduleId === null || $moduleId === '') {
      throw new Exception(__('Identifiant de module manquant pour l’appel Hoymiles', __FILE__));
    }

    $baseUrl = trim(config::byKey('api_base_url', 'estarenergy', 'https://neapi.hoymiles.com'));
    $endpointPath = trim(config::byKey('endpoint_path', 'estarenergy', '/pvm-data/api/0/module/data/down_module_day_data'));
    $authorization = trim(config::byKey('authorization_token', 'estarenergy'));
    $language = trim(config::byKey('api_language', 'estarenergy', 'fr-fr'));
    $payloadTemplate = config::byKey('payload_template', 'estarenergy', '{"moduleId":{{moduleId}},"date":"{{date}}"}');

    if ($baseUrl === '') {
      throw new Exception(__('URL de l’API Hoymiles non configurée', __FILE__));
    }
    if ($authorization === '') {
      throw new Exception(__('Jeton d’autorisation non configuré', __FILE__));
    }

    $url = rtrim($baseUrl, '/') . $endpointPath;
    $payload = self::buildPayload($payloadTemplate, array(
      'moduleId' => $moduleId,
      'moduleSn' => $moduleSn,
      'date' => $date,
    ));

    $headers = array(
      'Accept: application/octet-stream',
      'Content-Type: application/json',
      'authorization: ' . $authorization,
      'language: ' . $language,
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
      throw new Exception(__('Erreur cURL : ', __FILE__) . $curlError);
    }

    log::add('estarenergy', 'debug', sprintf('Appel Hoymiles %s | payload=%s | http=%s', $url, $payload, $httpCode));

    $decodedData = self::decodeModuleDayStream($response);

    return array(
      'url' => $url,
      'http_code' => $httpCode,
      'content_type' => $contentType,
      'payload' => $payload,
      'decoded' => $decodedData,
      'raw_base64' => base64_encode($response),
    );
  }

  /**
   * Remplace les placeholders du modèle par les valeurs réelles.
   *
   * @param string $template
   * @param array $variables
   * @return string
   */
  private static function buildPayload($template, $variables) {
    $payload = $template;
    foreach ($variables as $key => $value) {
      $payload = str_replace('{{' . $key . '}}', $value, $payload);
      $payload = str_replace('{{ ' . $key . ' }}', $value, $payload);
    }
    return $payload;
  }

  /**
   * Décode le flux binaire renvoyé par l'API.
   *
   * @param string $stream
   * @return array
   */
  private static function decodeModuleDayStream($stream) {
    $maybeDecoded = self::maybeGzipDecode($stream);
    $jsonData = json_decode($maybeDecoded, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      return array(
        'format' => 'json',
        'data' => $jsonData,
      );
    }

    $date = self::extractDate($maybeDecoded);
    $times = self::extractTimes($maybeDecoded);

    return array(
      'format' => 'binary',
      'date' => $date,
      'times' => $times,
      'printable_excerpt' => self::extractPrintableExcerpt($maybeDecoded),
    );
  }

  private static function extractDate($data) {
    if (preg_match('/\\d{4}-\\d{2}-\\d{2}/', $data, $matches)) {
      return $matches[0];
    }
    return null;
  }

  private static function extractTimes($data) {
    if (preg_match_all('/\\b\\d{2}:\\d{2}\\b/', $data, $matches)) {
      return $matches[0];
    }
    return array();
  }

  private static function extractPrintableExcerpt($data) {
    $printable = preg_replace('/[^\\x20-\\x7E]/', ' ', $data);
    $printable = preg_replace('/\\s+/', ' ', $printable);
    return trim(substr($printable, 0, 1024));
  }

  private static function maybeGzipDecode($data) {
    if (substr($data, 0, 2) === "\x1f\x8b") {
      $decoded = @gzdecode($data);
      if ($decoded !== false) {
        return $decoded;
      }
    }
    return $data;
  }

  /**
   * Rafraîchit les données pour l'équipement courant et met à jour les commandes infos.
   *
   * @throws Exception
   */
  public function refreshData() {
    $options = array(
      'moduleId' => $this->getConfiguration('module_id'),
      'moduleSn' => $this->getConfiguration('module_sn'),
      'date' => $this->getConfiguration('query_date'),
    );
    if ($options['date'] == '') {
      $options['date'] = date('Y-m-d');
    }

    $result = self::fetchModuleDayData($options);

    $decoded = isset($result['decoded']) ? $result['decoded'] : array();
    $date = isset($decoded['date']) && $decoded['date'] !== null ? $decoded['date'] : $options['date'];
    $times = isset($decoded['times']) ? implode(', ', $decoded['times']) : '';

    $this->updateInfoCmd('raw_data', json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $this->updateInfoCmd('queried_date', $date);
    $this->updateInfoCmd('times', $times);
  }

  private function createOrUpdateInfoCmd($logicalId, $name) {
    $cmd = $this->getCmd('info', $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setName($name);
      $cmd->setType('info');
      $cmd->setSubType('string');
      $cmd->setIsHistorized(1);
      $cmd->save();
    } else {
      $cmd->setName($name);
      $cmd->save();
    }
  }

  private function createOrUpdateActionCmd($logicalId, $name) {
    $cmd = $this->getCmd('action', $logicalId);
    if (!is_object($cmd)) {
      $cmd = new estarenergyCmd();
      $cmd->setLogicalId($logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setName($name);
      $cmd->setType('action');
      $cmd->setSubType('other');
      $cmd->save();
    } else {
      $cmd->setName($name);
      $cmd->save();
    }
  }

  private function updateInfoCmd($logicalId, $value) {
    $cmd = $this->getCmd('info', $logicalId);
    if (is_object($cmd)) {
      $cmd->event($value);
    }
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
    if ($this->getLogicalId() == 'refresh') {
      $eqLogic = $this->getEqLogic();
      if (!is_object($eqLogic)) {
        throw new Exception(__('Equipement introuvable pour exécuter la commande', __FILE__));
      }
      $eqLogic->refreshData();
      return;
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}
