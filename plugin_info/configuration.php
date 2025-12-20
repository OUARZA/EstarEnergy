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
      <label class="col-md-4 control-label">{{URL de l'API Hoymiles}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Adresse de base pour les appels (ex : https://neapi.hoymiles.com)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_base_url" placeholder="https://neapi.hoymiles.com" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Jeton d'autorisation}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Valeur de l'en-tête authorization retournée par monitor.estarpower.com}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control inputPassword" data-l1key="authorization_token" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Langue des requêtes}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Valeur envoyée dans l'en-tête language (ex : fr-fr)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_language" placeholder="fr-fr" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Chemin de l'endpoint}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Chemin relatif utilisé pour récupérer les données journalières du module}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="endpoint_path" placeholder="/pvm-data/api/0/module/data/down_module_day_data" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Modèle de payload par défaut}}
        <sup><i class="fas fa-question-circle tooltips" title="{{JSON envoyé dans le POST. Utilisez les variables {{moduleId}}, {{moduleSn}} et {{date}}}}"></i></sup>
      </label>
      <div class="col-md-6">
        <textarea class="configKey form-control" rows="3" data-l1key="payload_template">{"moduleId":{{moduleId}},"date":"{{date}}"}</textarea>
        <span class="help-block">{{Les marqueurs seront remplacés avant l'envoi. Laissez les guillemets dans le modèle.}}</span>
      </div>
    </div>
  </fieldset>
</form>
