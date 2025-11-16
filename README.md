# Plugin EstarEnergy pour Jeedom

Le plugin **EstarEnergy** interroge l'API Estar Power afin de rapatrier les données de production photovoltaïque, de consommation et de bilans financiers directement dans Jeedom. Il crée automatiquement les commandes d'information nécessaires pour suivre la centrale et propose une commande d'action permettant de lancer un rafraîchissement manuel à tout moment.

## Fonctionnalités principales
- Authentification automatique auprès de `monitor.estarpower.com` avec gestion du cache de jeton pour éviter les blocages d'API.
- Synchronisation planifiée des équipements via la tâche `CheckUpdate` (5 min, 10 min, 30 min ou 1 h) et désactivation automatique si le quota quotidien de connexions échoue.
- Calcul des métriques d'autoproduction/autoconsommation à partir des flux importés/exportés.
- Valorisation économique des imports/exports (coûts et revenus quotidiens, annuels et cumulés) en fonction des tarifs configurés.
- Création automatique des commandes info/Action (production, consommation, revenus, émissions évitées, etc.).

## Prérequis
- Jeedom v4.2 ou supérieure (cf. `plugin_info/info.json`).
- Un compte Estar Power actif et l'accès à l'interface https://monitor.estarpower.com.
- Les identifiants (login/mot de passe) utilisés sur le portail Estar Power et l'identifiant numérique (`detail-id`) de chaque centrale à surveiller.

## Installation
1. Installer le plugin depuis le Market Jeedom ou déployer ce dépôt dans `plugins/estarenergy`.
2. Activer le plugin depuis **Plugins > Gestion des plugins**.
3. Ouvrir la configuration du plugin pour renseigner les identifiants Estar Power et paramétrer les options décrites ci-dessous.

## Configuration du plugin
### Paramètres globaux
Accessible via **Plugins > Programmation > EstarEnergy > Configuration** (`plugin_info/configuration.php`) :

| Champ | Description |
| --- | --- |
| **Identifiant Estar Power** | Identifiant utilisé sur le portail Estar Power. |
| **Mot de passe Estar Power** | Mot de passe associé. Il est chiffré automatiquement dans Jeedom. |
| **Fréquence d'actualisation** | Détermine la planification du cron `CheckUpdate`. Choisissez 5 min (par défaut), 10 min, 30 min, 1 h ou « Jamais » pour un rafraîchissement manuel. |
| **Prix d'achat HT (€/kWh)** | Tarif réseau appliqué lors de l'import d'énergie ; utilisé pour calculer `purchase_cost`. |
| **Prix de vente HT (€/kWh)** | Tarif de rachat appliqué lors de l'export ; utilisé pour `sale_revenue`, `annual_revenue` et `total_revenue`. |

> Astuce : en cas de dépassement du nombre quotidien de connexions autorisées par Estar Power, le plugin désactive automatiquement la planification pour éviter des blocages. Il suffit de ressaisir/sauvegarder la fréquence une fois le blocage levé.

### Création d'un équipement
1. Depuis la page du plugin, cliquer sur **Ajouter** pour créer une centrale.
2. Renseigner les paramètres généraux (nom, objet parent, catégories, visibilité) puis activer l'équipement.
3. Dans la section **Paramètres spécifiques**, saisir l'**identifiant de centrale** (`station_id`). Il correspond au nombre affiché dans l'URL de monitor.estarpower.com après `detail-id` lorsque vous ouvrez la centrale (voir l'encart d'aide et la capture incluse dans `desktop/php/estarenergy.php`).
4. Sauvegarder. Les commandes info/action sont créées automatiquement et un premier rafraîchissement peut être lancé via le bouton **Actualiser** ou la commande `refresh`.

## Commandes créées
Toutes les commandes info sont historisées par défaut sauf mention contraire. Les données sont automatiquement mises à jour lors de chaque rafraîchissement.

| ID logique | Libellé | Type | Unité | Historique |
| --- | --- | --- | --- | --- |
| `Pv_power` | Production photovoltaïque | Info | W | Oui |
| `Load_power` | Puissance consommée | Info | W | Oui |
| `Grid_power` | Puissance réseaux | Info | W | Oui |
| `meter_b_in_eq` | Énergie depuis le réseau | Info | W | Oui |
| `meter_b_out_eq` | Énergie vers le réseau | Info | W | Oui |
| `self_eq` | Auto-consommation | Info | W | Oui |
| `month_eq` | Production du mois | Info | W | Oui |
| `today_eq` | Production du jour | Info | kWh | Oui |
| `year_eq` | Production de l'année | Info | W | Oui |
| `total_eq` | Production totale | Info | W | Oui |
| `purchase_cost` | Achat | Info | € | Non |
| `sale_revenue` | Vente | Info | € | Non |
| `annual_revenue` | Revenu annuel | Info | € | Oui |
| `total_revenue` | Revenu total | Info | € | Oui |
| `production` | Production totale (jour) | Info | W | Oui |
| `consumption` | Consommation totale (jour) | Info | W | Oui |
| `auto_production_rate` | Taux d'autoproduction | Info | % | Non |
| `auto_consumption_rate` | Taux d'autoconsommation | Info | % | Non |
| `plant_tree` | Compensation des émissions | Info | arbres | Oui |
| `co2_emission_reduction` | Réduction des émissions | Info | t | Oui |
| `last_refresh` | Dernière actualisation | Info (string) | — | Non |
| `refresh` | Actualiser | Action | — | — |

> Les commandes `purchase_cost`, `sale_revenue`, `annual_revenue` et `total_revenue` sont recalculées à partir des tarifs HT déclarés dans la configuration globale. Les taux d'autoproduction/autoconsommation sont fournis en pourcentage (0–100).

## Conseils d'exploitation
- Utilisez les historiques Jeedom pour tracer l'évolution de la production, de la consommation ou de la rentabilité sur différentes périodes.
- En cas d'erreur d'authentification, vérifiez les identifiants globaux et les journaux (`Analyse > Logs > estarenergy`). Le plugin journalise également la récupération du token et les réponses API.
- Si la centrale ne remonte plus de données, vérifiez le champ `station_id` et relancez un rafraîchissement manuel. Les fichiers temporaires (`/tmp/estarenergy/`) sont régénérés automatiquement si supprimés.

## Ressources
- Documentation générique Jeedom : https://doc.jeedom.com/fr_FR/dev/
- Documentation officielle du plugin : https://doc.jeedom.com/fr_FR/plugins/programming/estarenergy
