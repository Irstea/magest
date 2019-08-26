<?php

/**
 * Script d'integration des coefficients de maree fournis par le SHOM
 * Realisation : Eric Quinton - mai 2019
 * Copyright © Irstea 2019
 */

error_reporting(E_ERROR);
require_once 'lib/Message.php';
require_once 'lib/fonctions.php';
require_once 'lib/import.class.php';
require_once 'lib/vue.class.php';
require_once 'lib/ObjetBDD_functions.php';
require_once 'lib/ObjetBDD.php';
require_once 'lib/measure.class.php';
$message = new Message();
/**
 * End of Treatment
 */
$eot = false;

/**
 * Options par defaut
 */
$message->set("Magest : importation des données MAGEST");
$message->set("Licence : MIT. Copyright © 2019 - Éric Quinton, pour Irstea - EABX - Cestas");
/**
 * Traitement des options de la ligne de commande
 */
if ($argv[1] == "-h" || $argv[1] == "--help") {

    $message->set("Options :");
    $message->set("-h ou --help : ce message d'aide");
    $message->set("--station=stationName : nom de la station (obligatoire). Le nom doit correspondre à une entrée dans le fichier ini (section [stations]");
    $message->set("--dsn=pgsql:host=server;dbname=database;sslmode=require : PDO dsn (adresse de connexion au serveur selon la nomenclature PHP-PDO)");
    $message->set("--login= : nom du login de connexion");
    $message->set("--password= : mot de passe associé");
    $message->set("--schema=public : nom du schéma contenant les tables");
    $message->set("--source=source : nom du dossier contenant les fichiers source");
    $message->set("--treated=treated : nom du dossier où les fichiers sont déplacés après traitement");
    $message->set("--param=param.ini : nom du fichier de paramètres (ne pas modifier sans bonne raison)");
    $message->set("--filetype=csv : extension des fichiers à traiter");
    $message->set("--noMove=1 : pas de déplacement des fichiers une fois traités");
    $message->set("Les fichiers à traiter doivent être déposés dans le dossier import");
    $message->set("Une fois traités, les fichiers sont déplacés dans le dossier treated");
    $eot = true;
} else {
    /**
     * Processing args
     */
    $moveFile = true;
    $params = array();
    for ($i = 1; $i <= count($argv); $i++) {
        $arg = explode("=", $argv[$i]);
        $params[$arg[0]] = $arg[1];
    }
}
if (!$eot) {
    if (!isset($params["param"])) {
        $params["param"] = "./param.ini";
    }
    /**
     * Recuperation des parametres depuis le fichier param.ini
     * 
     */
    if (!file_exists($params["param"])) {
        $message->set("Le fichier de paramètres " . $params["param"] . " n'existe pas");
        $eot = true;
    } else {
        $param = parse_ini_file($params["param"], true);
        foreach ($params as $key => $value) {
            $param["general"][substr($key, 2)] = $value;
        }

        if (strlen($param["general"]["station"]) > 0) {
            if (!isset($param["stations"][$param["general"]["station"]])) {
                $message->set("La station n'existe pas dans le fichier param.ini: " . $param["general"]["station"]);
                $eot = true;
            }
        } else {
            $message->set("La station n'a pas été renseignée");
            $eot = true;
        }
    }

    /**
     * Connexion à la base de données et initialisation de la table
     */
    try {
        $pdo = connect($param["general"]["dsn"], $param["general"]["user"], $param["general"]["password"], $param["general"]["schema"]);
        $measure = new Measure($pdo, $ObjetBDDParam);
        /**
         * Creation of fields
         */
        $fieldnames = array("measure_id", "station", "temperature", "turbidity_ntu", "salinity_mgl", "oxygen_mgl");
        $fields = array("measure_id" => $param["fields"]["measure_id"], "date" => $param["fields"]["date"]);
        $colonnes = array(
            [$fields["measure_id"]] => array("type" => 1, "requis" => 1, "key" => 1),
            $fields["date"] => array("type" => 3, "requis" => 1)
        );
        foreach ($fieldnames as $field) {
            if (isset($param["fields"][$field])) {
                $colonnes[] = $param["fields"][$field] = array("type" => 1);
                $fields[$field] = $param["fields"][$field];
            }
        }
        $measure->init($param["fields"]["table"], $colonnes);
    } catch (Exception $e) {
        $message->set("Erreur de connexion à la base de données :");
        $message->set($e->getMessage());
        $eot = true;
    }
}
if (!$eot) {
    /**
     * Recuperation de la liste des fichiers a traiter
     */
    $files = array();
    try {
        $folder = opendir($param["general"]["source"]);
        if ($folder) {
            $filesOnly = array();
            $radicalLength = strlen($param["file"]["radical"]);
            while (false !== ($filename = readdir($folder))) {
                /**
                 * Extraction de l'extension
                 */
                $extension = (false === $pos = strrpos($filename, '.')) ? '' : strtolower(substr($filename, $pos + 1));
                if ($extension == $param["general"]["filetype"] && substr($filename, 0, $radicalLength) == $param["file"]["radical"]) {
                    $files[] = $filename;
                }
            }
            closedir($folder);
        } else {
            $message->set("Le dossier " . $param["general"]["source"] . " n'existe pas");
        }
    } catch (Exception $e) {
        $message->set("Le dossier " . $param["general"]["source"] . " n'existe pas");
    }

    if (count($files) > 0) {
        /**
         * Declenchement de la lecture
         */
        $import = new Import();

        foreach ($files as $file) {
            try {
                $data = $import->initFile($param["general"]["source"] . "/" . $file, $param["file"]["separator"], $param["file"]["firstLine"]);
                $pdo->beginTransaction();
                foreach ($data as $row) {
                    $newitem = array(
                        $fields["measure_id"] => 0,
                        $fields["station"] => $param["stations"][$param["general"]["station"]]
                    );
                    /**
                     * Extract all data from the current row
                     */
                    foreach (array("date", "temperature", "turbidity_ntu", "salinity_mgl", "oxygen_mgl") as $field) {
                        $newitem[$fields[$field]] = $row[$param["file"][$field]];
                    }
                    /**
                     * Reformate the date
                     */
                    $ldate = $newitem[$fields["date"]];
                    if (strlen($ldate) == 11) {
                        $ldate = "0" . $ldate;
                    }
                    $newitem[$fields["date"]] = substr($ldate, 0, 2) . "/"
                        . substr($ldate, 2, 2) . "/20"
                        . substr($ldate, 4, 2) . " "
                        . substr($ldate, 6, 2) . ":"
                        . substr($ldate, 8, 2) . ":"
                        . substr($ldate, 10, 2);
                    /**
                     * Write data in table
                     */
                    $measure->ecrire($newitem);
                }
                /**
                 * Deplacement du fichier
                 */
                if ($param["general"]["noMove"] != 1) {
                    rename($param["general"]["source"] . "/" . $file, $param["general"]["treated"] . "/" . $file);
                }
                $message->set("Fichier $file traité");
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $message->set("Echec d'importation des fichiers");
                $message->set($e->getMessage());
            }
        }
    } else {
        $message->set("Pas de fichiers à traiter dans le dossier " . $param["general"]["folder"]);
    }
}

/**
 * Display messages
 */
if (!stripos(PHP_OS, "WIN")) {
    $windows = false;
} else {
    $windows = true;
}
foreach ($message->get() as $line) {
    if ($windows) {
        utf8_decode($line);
    }
    echo ($line . PHP_EOL);
}
echo (PHP_EOL);
