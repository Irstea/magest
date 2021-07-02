# Importation de fichiers contenant des paramètres physico-chimiques

## Objectif

Le programme permet d'importer des données physico-chimiques relevées à partir de sondes automatiques dans une table PostgreSQL (non testé avec d'autres SGBD).

Deux scripts sont disponibles :
- magest.php permet de traiter des fichiers dans un format "brut" issu des sondes ;
- magest-csv.php est dédié à l'importation des fichiers csv (une colonne par paramètre).

## Utilisation de magest-csv.php
### Paramétrage

Renommez le fichier param-dist.ini en param.ini, et modifiez les sections ainsi :
- general : paramètres par défaut. Ils sont tous modifiables en arguments dans la ligne de commande (--argument=valeur)
- table : description de la table cible :
	- table : nom de la table
	- measure_id : nom de la clé primaire, qui doit être de type *serial*
	- station  : nom de la colonne contenant l'identifiant de la station (sous forme numérique)
	- date : nom de la colonne contenant la date
	- les autres champs seront déduits des colonnes trouvées dans les fichiers (cf. sections fields ou csv)
- stations : transcodage des stations saisies en ligne de commande pour récupérer l'identifiant qui sera inséré dans la table
- fields : à gauche, liste des libellés présents dans l'entête du fichier csv, à droite, nom du champ dans la table Cette rubrique n'est utilisée que pour les imports dans les formats "bruts"
- csv : idem à fields, mais pour les imports en CSV

### Utilisation

Le programme nécessite d'installer php 7.2 ou ultérieur dans la machine exécutant les scripts, avec les bibliothèques nécessaires pour la connexion à la base de données Postgresql.

Les fichiers doivent être déposés dans le dossier *import*. Le programme va traiter tous les fichiers présents dans le dossier, **mais ceux-ci ne doivent concerner que la même station** et avoir la même structure, au moins en ce qui concerne l'emplacement du champ *date*.

Exemple d'utilisation :

~~~
php magest-csv.php --param=param2021.ini --source=import --treated=treated --separator=";" --numline=3 --datefield=0 --station=branne
~~~

