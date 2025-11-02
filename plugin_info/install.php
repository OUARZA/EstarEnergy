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
require_once dirname(__FILE__) . '/../core/class/estarenergy.class.php';

// Fonction exécutée automatiquement après l'installation du plugin
function estarenergy_install() {
  estarenergy::applyRefreshCron();
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function estarenergy_update() {
  estarenergy::applyRefreshCron();
}

// Fonction exécutée automatiquement après la suppression du plugin
function estarenergy_remove() {
  $functions = array('pullData', 'cron5', 'cron10', 'cron30', 'cronHourly');
  foreach ($functions as $function) {
    $cron = cron::byClassAndFunction('estarenergy', $function);
    if (is_object($cron)) {
      $cron->remove();
    }
  }
}
