# Plugin Estar Energy

Le plugin **Estar Energy** permet de collecter les informations disponibles sur le portail Estar Power afin d'alimenter votre domotique Jeedom avec les données de votre installation photovoltaïque.

## Configuration du plugin

1. **Identifiants globaux** : dans la configuration du plugin, indiquez l'identifiant et le mot de passe utilisés sur https://monitor.estarpower.com.
2. **Création d'un équipement** : dans l'onglet du plugin, ajoutez un nouvel équipement puis saisissez le SID de la centrale à superviser (visible dans l'URL du portail Estar Power).
3. **Sauvegarde** : enregistrez l'équipement ; toutes les commandes infos sont créées automatiquement.

Le plugin interroge le portail toutes les 5 minutes. Une cadence plus rapide est déconseillée afin d'éviter les limitations imposées par Estar Power.

## Commandes disponibles

Chaque équipement expose les commandes infos suivantes :

* `Pv_power` : puissance photovoltaïque instantanée (W)
* `Load_power` : puissance consommée par le foyer (W)
* `Grid_power` : puissance prélevée/injectée sur le réseau (W)
* `meter_b_in_eq` : énergie importée depuis le réseau (Wh)
* `meter_b_out_eq` : énergie exportée vers le réseau (Wh)
* `self_eq` : énergie issue de l'autoconsommation (Wh)
* `today_eq` : production du jour (Wh)
* `month_eq` : production du mois (Wh)
* `year_eq` : production de l'année (Wh)
* `total_eq` : production totale (Wh)
* `plant_tree` : estimation du nombre d'arbres compensés
* `co2_emission_reduction` : estimation de la réduction d'émissions de CO₂ (kg)

Toutes les commandes sont historisées par défaut pour faciliter le suivi énergétique.

## Dépannage

* Vérifiez que les identifiants Estar Power saisis dans la configuration du plugin sont corrects.
* Assurez-vous que le SID renseigné correspond bien à la centrale désirée.
* Consultez les logs du plugin (`estarenergy`) pour obtenir le détail des éventuelles erreurs de connexion ou de récupération de données.
