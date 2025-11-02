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
        <sup><i class="fas fa-question-circle tooltips" title="{{Identifiant de connexion sur le site Estar Power}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="estarpower_login" placeholder="{{Identifiant}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mot de passe Estar Power}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Mot de passe de connexion sur le site Estar Power}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="estarpower_password" type="password" placeholder="{{Mot de passe}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Fréquence d'actualisation}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Choisissez la fréquence de rafraîchissement recommandée : 5 minutes par défaut}}"></i></sup>
      </label>
      <div class="col-md-4">
        <select class="configKey form-control" data-l1key="estarpower_refresh">
          <option value="cron5">{{Toutes les 5 minutes (préconisé)}}</option>
          <option value="cron10">{{Toutes les 10 minutes}}</option>
          <option value="cron30">{{Toutes les 30 minutes}}</option>
          <option value="cronHourly">{{Toutes les heures (0 * * * *)}}</option>
          <option value="">{{Jamais}}</option>
        </select>
      </div>
    </div>
  </fieldset>
</form>
