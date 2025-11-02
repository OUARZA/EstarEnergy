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
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class estarenergy extends eqLogic {
    private const LOGIN_URL = 'https://monitor.estarpower.com/platform/api/gateway/iam/auth_login';
    private const DATA_URL = 'https://monitor.estarpower.com/platform/api/gateway/pvm-data/data_count_station_real_data';
    private const TOKEN_CACHE_KEY = 'estarenergy::auth';
    private const TOKEN_MAX_AGE = 3600; // 1 hour
    private const DEFAULT_REFRESH_INTERVAL = 5; // minutes
    private const ALLOWED_REFRESH_INTERVALS = [5, 10, 30, 60];

    private const COMMANDS = [
        'pv_power' => ['Pv_power', 'W'],
        'load_power' => ['Load_power', 'W'],
        'grid_power' => ['Grid_power', 'W'],
        'meter_b_in_eq' => ['meter_b_in_eq', 'Wh'],
        'meter_b_out_eq' => ['meter_b_out_eq', 'Wh'],
        'self_eq' => ['self_eq', 'Wh'],
        'month_eq' => ['month_eq', 'Wh'],
        'today_eq' => ['today_eq', 'Wh'],
        'year_eq' => ['year_eq', 'Wh'],
        'total_eq' => ['total_eq', 'Wh'],
        'plant_tree' => ['plant_tree', 'Arbres'],
        'co2_emission_reduction' => ['co2_emission_reduction', 'kg'],
    ];
    private const ACTION_REFRESH = 'refresh_action';

    /*     * ***********************Methode static*************************** */

    public static function cron5() {
        self::applyConfiguredSchedule();
        foreach (eqLogic::byType('estarenergy', true) as $eqLogic) {
            try {
                $eqLogic->refresh();
            } catch (Exception $exception) {
                log::add('estarenergy', 'error', sprintf(__('Erreur lors de la mise à jour de %s : %s', __FILE__), $eqLogic->getHumanName(), $exception->getMessage()));
            }
        }
    }

    public static function registerCron(?int $interval = null): void {
        $cron = cron::byClassAndFunction(__CLASS__, 'cron5');
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass(__CLASS__);
            $cron->setFunction('cron5');
        }

        $cron->setEnable(1);
        $cron->setDeamon(0);
        $schedule = self::getCronSchedule($interval ?? self::getRefreshInterval());
        if ($cron->getSchedule() !== $schedule) {
            $cron->setSchedule($schedule);
        }
        $cron->save();
    }

    public static function removeCron(): void {
        $cron = cron::byClassAndFunction(__CLASS__, 'cron5');
        if (is_object($cron)) {
            $cron->remove();
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    public function postSave() {
        foreach (self::COMMANDS as $logicalId => $definition) {
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new estarenergyCmd();
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
            }
            $cmd->setName($definition[0]);
            $cmd->setType('info');
            $cmd->setSubType('numeric');
            $cmd->setUnite($definition[1]);
            $cmd->setIsHistorized(1);
            $cmd->save();
        }

        $refreshCmd = $this->getCmd(null, self::ACTION_REFRESH);
        if (!is_object($refreshCmd)) {
            $refreshCmd = new estarenergyCmd();
            $refreshCmd->setEqLogic_id($this->getId());
            $refreshCmd->setLogicalId(self::ACTION_REFRESH);
        }
        $refreshCmd->setName(__('Actualiser', __FILE__));
        $refreshCmd->setType('action');
        $refreshCmd->setSubType('other');
        $refreshCmd->setIsHistorized(0);
        $refreshCmd->setDisplay('icon', 'fas fa-sync-alt');
        $refreshCmd->save();

        try {
            self::updateStationData($this, false);
        } catch (Exception $exception) {
            log::add('estarenergy', 'debug', sprintf(__('Mise à jour différée pour %s : %s', __FILE__), $this->getHumanName(), $exception->getMessage()));
        }
    }

    public function refresh($_force = false) {
        self::updateStationData($this, (bool) $_force);
    }

    private static function updateStationData(estarenergy $eqLogic, bool $forceToken) {
        $stationId = trim((string) $eqLogic->getConfiguration('station_id'));
        if ($stationId === '') {
            log::add('estarenergy', 'warning', sprintf(__('Identifiant de station manquant pour l\'équipement %s', __FILE__), $eqLogic->getHumanName()));
            return;
        }

        try {
            $payload = self::fetchData($stationId, $forceToken);
        } catch (Exception $exception) {
            log::add('estarenergy', 'error', sprintf(__('Impossible de récupérer les données pour %s : %s', __FILE__), $eqLogic->getHumanName(), $exception->getMessage()));
            return;
        }

        if ($payload === null) {
            log::add('estarenergy', 'debug', sprintf(__('Aucune donnée reçue pour %s', __FILE__), $eqLogic->getHumanName()));
            return;
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            log::add('estarenergy', 'error', sprintf(__('Structure de données inattendue pour %s', __FILE__), $eqLogic->getHumanName()));
            log::add('estarenergy', 'debug', json_encode($payload));
            return;
        }

        self::applyDataToEqLogic($eqLogic, $payload);
    }

    private static function getRefreshInterval(): int {
        $configuredInterval = (int) config::byKey('refresh_interval', 'estarenergy', self::DEFAULT_REFRESH_INTERVAL);

        return self::normalizeRefreshInterval($configuredInterval);
    }

    private static function getCronSchedule(int $intervalMinutes): string {
        if ($intervalMinutes === 60) {
            return '0 * * * *';
        }

        return sprintf('*/%d * * * *', $intervalMinutes);
    }

    private static function applyConfiguredSchedule(): void {
        $cron = cron::byClassAndFunction(__CLASS__, 'cron5');
        if (!is_object($cron)) {
            self::registerCron();
            return;
        }

        $desiredSchedule = self::getCronSchedule(self::getRefreshInterval());
        if ($cron->getSchedule() !== $desiredSchedule) {
            $cron->setSchedule($desiredSchedule);
            $cron->save();
        }
    }

    public static function postConfig_update($values) {
        if (isset($values['refresh_interval'])) {
            $interval = self::normalizeRefreshInterval((int) $values['refresh_interval']);
            config::save('refresh_interval', $interval, 'estarenergy');
            self::registerCron($interval);
        } else {
            self::registerCron();
        }

        cache::set(self::TOKEN_CACHE_KEY, []);
        self::resetCookieFile();

        foreach (eqLogic::byType('estarenergy', true) as $eqLogic) {
            try {
                $eqLogic->refresh(true);
            } catch (Exception $exception) {
                log::add('estarenergy', 'error', sprintf(__('Impossible d\'actualiser %s après la sauvegarde de la configuration : %s', __FILE__), $eqLogic->getHumanName(), $exception->getMessage()));
            }
        }
    }

    private static function normalizeRefreshInterval(int $interval): int {
        if (!in_array($interval, self::ALLOWED_REFRESH_INTERVALS, true)) {
            return self::DEFAULT_REFRESH_INTERVAL;
        }

        return $interval;
    }

    private static function applyDataToEqLogic(estarenergy $eqLogic, array $payload): void {
        $globalData = $payload['data'] ?? [];
        $stationData = $globalData['reflux_station_data'] ?? [];

        $mapping = [
            'today_eq' => $globalData,
            'month_eq' => $globalData,
            'year_eq' => $globalData,
            'total_eq' => $globalData,
            'plant_tree' => $globalData,
            'co2_emission_reduction' => $globalData,
            'pv_power' => $stationData,
            'load_power' => $stationData,
            'grid_power' => $stationData,
            'meter_b_in_eq' => $stationData,
            'meter_b_out_eq' => $stationData,
            'self_eq' => $stationData,
        ];

        $updated = false;
        foreach ($mapping as $logicalId => $source) {
            if (!is_array($source) || !array_key_exists($logicalId, $source)) {
                continue;
            }
            $value = $source[$logicalId];
            if (!is_numeric($value)) {
                continue;
            }
            if ($eqLogic->checkAndUpdateCmd($logicalId, (float) $value)) {
                $updated = true;
            }
        }

        if (!$updated) {
            log::add('estarenergy', 'warning', sprintf(__('Aucune mesure valide n\'a été trouvée pour %s', __FILE__), $eqLogic->getHumanName()));
            log::add('estarenergy', 'debug', json_encode($payload));
        }
    }

    private static function fetchData(string $stationId, bool $forceToken): ?array {
        $auth = self::getAuthData($forceToken);
        $token = $auth['token'] ?? '';
        if ($token === '') {
            throw new Exception(__('Token d\'authentification introuvable', __FILE__));
        }

        $response = self::callApi(self::DATA_URL, [
            'body' => [
                'sid' => is_numeric($stationId) ? (int) $stationId : $stationId,
                'mode' => 1,
                'date' => date('Y-m-d'),
            ],
            'WAITING_PROMISE' => true,
        ], [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json;charset=UTF-8',
            'User-Agent: Mozilla/5.0',
            'Cookie: estar_token=' . $token,
        ], false);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception(__('Réponse invalide du service Estar Power', __FILE__));
        }

        if (isset($data['code']) && (int) $data['code'] !== 200) {
            if ($forceToken) {
                throw new Exception(sprintf(__('Code retour inattendu (%s)', __FILE__), $data['code']));
            }
            cache::set(self::TOKEN_CACHE_KEY, []);
            self::resetCookieFile();
            return self::fetchData($stationId, true);
        }

        return $data;
    }

    private static function getAuthData(bool $forceRefresh): array {
        if (!$forceRefresh) {
            $cache = cache::byKey(self::TOKEN_CACHE_KEY);
            $cachedValue = $cache->getValue();
            if (is_array($cachedValue) && isset($cachedValue['token'], $cachedValue['timestamp'])) {
                if ((time() - (int) $cachedValue['timestamp']) < self::TOKEN_MAX_AGE) {
                    return $cachedValue;
                }
            }
        }

        $username = trim((string) config::byKey('username', 'estarenergy'));
        $password = trim((string) config::byKey('password', 'estarenergy'));

        if ($username === '' || $password === '') {
            throw new Exception(__('Identifiants Estar Power non configurés', __FILE__));
        }

        self::resetCookieFile();

        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: fr,en-US;q=0.9,en;q=0.8,vi;q=0.7',
            'Connection: keep-alive',
            'Content-Type: application/json;charset=UTF-8',
            'Origin: https://monitor.estarpower.com',
            'Referer: https://monitor.estarpower.com/platform/login',
            'User-Agent: Mozilla/5.0',
        ];

        $payload = [
            'ERROR_BACK' => true,
            'LOAD' => ['loading' => true],
            'body' => [
                'user_name' => $username,
                'password' => $password,
            ],
            'WAITING_PROMISE' => true,
        ];

        $response = self::callApi(self::LOGIN_URL, $payload, $headers, true);

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['data']['token'])) {
            throw new Exception(__('Connexion impossible au service Estar Power', __FILE__));
        }

        $auth = [
            'token' => $data['data']['token'],
            'timestamp' => time(),
        ];

        cache::set(self::TOKEN_CACHE_KEY, $auth);

        return $auth;
    }

    private static function callApi(string $url, array $payload, array $headers, bool $storeCookies) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $cookieFile = self::getCookieFilePath();
        if ($storeCookies) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        } elseif (file_exists($cookieFile)) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception(sprintf(__('Erreur cURL : %s', __FILE__), $error));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception(sprintf(__('Code HTTP inattendu : %s', __FILE__), $status));
        }

        return $response;
    }

    private static function getCookieFilePath(): string {
        return jeedom::getTmpFolder('estarenergy') . '/cookies.txt';
    }

    private static function resetCookieFile(): void {
        $cookieFile = self::getCookieFilePath();
        if (file_exists($cookieFile)) {
            @unlink($cookieFile);
        }
    }
}

class estarenergyCmd extends cmd {
    public function execute($_options = array()) {
        if ($this->getType() !== 'action') {
            throw new Exception(__('Cette commande n\'est pas exécutable', __FILE__));
        }

        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic)) {
            throw new Exception(__('Équipement introuvable', __FILE__));
        }

        log::add('estarenergy', 'info', sprintf(__('Commande %s exécutée pour %s', __FILE__), $this->getName(), $eqLogic->getHumanName()));
        $eqLogic->refresh(true);

        return true;
    }
}
