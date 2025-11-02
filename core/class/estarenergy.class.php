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
    private const DEFAULT_CRON_KEY = 'Cron5';
    private const CRON_SCHEDULES = [
        'Cron5' => '*/5 * * * *',
        'Cron10' => '*/10 * * * *',
        'Cron30' => '*/30 * * * *',
        'CronHourly' => '0 * * * *',
    ];
    private const LOGIN_URL = 'https://monitor.estarpower.com/platform/api/gateway/iam/auth_login';
    private const DATA_URL = 'https://monitor.estarpower.com/platform/api/gateway/pvm-data/data_count_station_real_data';
    private const TOKEN_CACHE_KEY = 'estarenergy::auth';
    private const TOKEN_MAX_AGE = 3600; // 1 hour

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

    /*     * ***********************Methode static*************************** */

    public static function cron5() {
        self::synchronizeCronTask();

        if (self::hasActiveCronTask()) {
            return;
        }

        self::refreshAllEquipments();
    }

    public static function refreshFromCron() {
        self::refreshAllEquipments();
    }

    public static function postConfig() {
        self::synchronizeCronTask();
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

        try {
            self::updateStationData($this, false);
        } catch (Exception $exception) {
            log::add('estarenergy', 'debug', sprintf(__('Mise à jour différée pour %s : %s', __FILE__), $this->getHumanName(), $exception->getMessage()));
        }
    }

    public function refresh() {
        self::updateStationData($this, false);
    }

    private static function refreshAllEquipments(): void {
        foreach (eqLogic::byType('estarenergy', true) as $eqLogic) {
            try {
                $eqLogic->refresh();
            } catch (Exception $exception) {
                log::add('estarenergy', 'error', sprintf(__('Erreur lors de la mise à jour de %s : %s', __FILE__), $eqLogic->getHumanName(), $exception->getMessage()));
            }
        }
    }

    public static function synchronizeCronTask(?string $preferredSchedule = null): bool {
        $cronKey = self::normalizeCronKey($preferredSchedule ?? self::getConfiguredCronKey());
        $schedule = self::getCronExpression($cronKey);

        $cron = cron::byClassAndFunction('estarenergy', 'refreshFromCron');

        $needSave = false;
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass('estarenergy');
            $cron->setFunction('refreshFromCron');
            $cron->setOnce(0);
            $needSave = true;
        }

        try {
            if ($cron->getSchedule() !== $schedule) {
                $cron->setSchedule($schedule);
                $needSave = true;
            }
        } catch (Exception $exception) {
            log::add('estarenergy', 'error', sprintf(__('Expression cron invalide "%s" : %s', __FILE__), $schedule, $exception->getMessage()));
            return false;
        }

        if ($cron->getEnable() != 1) {
            $cron->setEnable(1);
            $needSave = true;
        }

        if ($cron->getTimeout() != 1440) {
            $cron->setTimeout(1440);
            $needSave = true;
        }

        if ($needSave) {
            $cron->save();
        }

        return true;
    }

    public static function applyCronSchedule(string $schedule): void {
        $normalizedCronKey = self::normalizeCronKey($schedule);
        config::save('refresh_cron', $normalizedCronKey, 'estarenergy');
        if (!self::synchronizeCronTask($normalizedCronKey)) {
            throw new Exception(__('Impossible de mettre à jour la planification, vérifiez la sélection du cron.', __FILE__));
        }
    }

    private static function getConfiguredCronKey(): string {
        $storedValue = trim((string) config::byKey('refresh_cron', 'estarenergy', self::DEFAULT_CRON_KEY));
        $normalizedValue = self::normalizeCronKey($storedValue);

        if ($storedValue !== $normalizedValue) {
            config::save('refresh_cron', $normalizedValue, 'estarenergy');
        }

        return $normalizedValue;
    }

    private static function normalizeCronKey(string $schedule): string {
        $schedule = trim($schedule);

        if ($schedule === '') {
            return self::DEFAULT_CRON_KEY;
        }

        if (array_key_exists($schedule, self::CRON_SCHEDULES)) {
            return $schedule;
        }

        $legacyKey = array_search($schedule, self::CRON_SCHEDULES, true);
        if ($legacyKey !== false) {
            return $legacyKey;
        }

        return self::DEFAULT_CRON_KEY;
    }

    private static function getCronExpression(string $cronKey): string {
        return self::CRON_SCHEDULES[$cronKey] ?? self::CRON_SCHEDULES[self::DEFAULT_CRON_KEY];
    }

    private static function hasActiveCronTask(): bool {
        $cron = cron::byClassAndFunction('estarenergy', 'refreshFromCron');
        return is_object($cron) && $cron->getEnable() == 1;
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

        self::applyDataToEqLogic($eqLogic, $payload);
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

        foreach ($mapping as $logicalId => $source) {
            if (!is_array($source) || !array_key_exists($logicalId, $source)) {
                continue;
            }
            $value = $source[$logicalId];
            if (!is_numeric($value)) {
                continue;
            }
            $eqLogic->checkAndUpdateCmd($logicalId, (float) $value);
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
                'sid' => (int) $stationId,
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
}
