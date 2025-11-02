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
          <option value="*/5 * * * *">{{Toutes les 5 minutes}}</option>
          <option value="*/10 * * * *">{{Toutes les 10 minutes}}</option>
          <option value="*/30 * * * *">{{Toutes les 30 minutes}}</option>
          <option value="0 * * * *">{{Toutes les 60 minutes}}</option>
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
        '*/5 * * * *': true,
        '*/10 * * * *': true,
        '*/30 * * * *': true,
        '0 * * * *': true
      };
      var current = ($field.val() || '').trim();
      if (!allowedValues[current]) {
        $field.val('*/5 * * * *');
      }
    }

    function applyCronUpdate() {
      $.ajax({
        type: 'POST',
        url: 'plugins/estarenergy/core/ajax/estarenergy.ajax.php',
        data: {
          action: 'applyCronSchedule'
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

    $('#bt_savePluginConf').off('click.estarenergy').on('click.estarenergy', function () {
      window.setTimeout(applyCronUpdate, 1000);
    });
  })();
</script>
