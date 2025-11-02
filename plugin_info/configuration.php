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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Identifiant Estar Power}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Identifiant utilisé pour se connecter au portail Estar Power.}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="username" type="text" autocomplete="off" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mot de passe Estar Power}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Mot de passe utilisé pour se connecter au portail Estar Power.}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="password" type="password" autocomplete="off" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Fréquence de mise à jour}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Intervalle utilisé pour planifier l'actualisation des données.}}"></i></sup>
      </label>
      <div class="col-md-4">
        <select class="configKey form-control" data-l1key="refresh_cron">
          <option value="Cron5">{{Toutes les 5 minutes}}</option>
          <option value="Cron10">{{Toutes les 10 minutes}}</option>
          <option value="Cron30">{{Toutes les 30 minutes}}</option>
          <option value="CronHourly">{{Toutes les 60 minutes}}</option>
        </select>
      </div>
    </div>
  </fieldset>
</form>
<script>
  (function () {
    function getCronField() {
      return $('.configKey[data-l1key="refresh_cron"]');
    }

    function ensureDefaultCronValue() {
      var $field = getCronField();
      if (!$field.length) {
        return;
      }
      var allowedValues = {
        'Cron5': true,
        'Cron10': true,
        'Cron30': true,
        'CronHourly': true
      };
      var legacyValues = {
        '*/5 * * * *': 'Cron5',
        '*/10 * * * *': 'Cron10',
        '*/30 * * * *': 'Cron30',
        '0 * * * *': 'CronHourly'
      };
      var current = ($field.val() || '').trim();
      if (allowedValues[current]) {
        return;
      }
      if (legacyValues[current]) {
        $field.val(legacyValues[current]);
        return;
      }
      $field.val('Cron5');
    }

    function getSelectedSchedule() {
      var $field = getCronField();
      if ($field.length) {
        return ($field.val() || '').trim();
      }
      return '';
    }

    function applyCronUpdate() {
      var schedule = getSelectedSchedule();
      $.ajax({
        type: 'POST',
        url: 'plugins/estarenergy/core/ajax/estarenergy.ajax.php',
        data: {
          action: 'applyCronSchedule',
          schedule: schedule
        },
        dataType: 'json',
        error: function (request, status, error) {
          var $alert = $('#div_alert');
          if ($alert.length) {
            $alert.showAlert({
              message: error || request.responseText,
              level: 'danger'
            });
          }
        },
        success: function (data) {
          if (data && data.state !== 'ok') {
            var $alert = $('#div_alert');
            if ($alert.length) {
              $alert.showAlert({
                message: data.result,
                level: 'danger'
              });
            }
          }
        }
      });
    }

    ensureDefaultCronValue();

    var saveButtonSelector = '#bt_savePluginConf, #bt_saveConfig';
    $('body').off('click.estarenergy', saveButtonSelector).on('click.estarenergy', saveButtonSelector, function () {
      applyCronUpdate();
    });
  })();
</script>
