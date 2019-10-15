<?php

/**
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
    $message->set("--filetype=txt : extension des fichiers à traiter");
    $message->set("--radical=sambat : radical du nom des fichiers à traiter");
    $message->set('--separator=space : séparateur de champ (, ; tab ou space)');
    $message->set("--headerLine=1 : n° de la ligne d'entête");
    $message->set("--numline=2 : première ligne à traiter");
    $message->set("--noMove=1 : pas de déplacement des fichiers une fois traités");
    $message->set("--mode=debug : affiche les paramètres analysés pour le premier fichier et le tableau des données pour le premier enregistrement, et s'arrête");
    $message->set("La définition des colonnes est décrite dans le fichier param.ini, dans la section csv");
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
        /**
         * Recuperation de la station
         */
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
        $pdo = connect($param["general"]["dsn"], $param["general"]["login"], $param["general"]["password"], $param["general"]["schema"]);
        $measure = new Measure($pdo);
        /**
         * Prepare the structure of the table
         */
        $colonnes = array(
            $param["table"]["measure_id"] => array("type" => 1, "requis" => 1, "key" => 1),
            $param["table"]["date"] => array("type" => 3, "requis" => 1),
            $param["table"]["station"] => array("type" => 1)
        );
        foreach ($param["fields"] as $field) {
            $colonnes[$field] = array("type" => 1);
        }
        /**
         * Init table content
         */
        $measure->init($param["table"]["table"], $colonnes);
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
            $radicalLength = strlen($param["general"]["radical"]);
            while (false !== ($filename = readdir($folder))) {
                /**
                 * Extraction de l'extension
                 */
                $extension = (false === $pos = strrpos($filename, '.')) ? '' : strtolower(substr($filename, $pos + 1));
                if ($extension == $param["general"]["filetype"] && substr($filename, 0, $radicalLength) == $param["general"]["radical"]) {
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
                $import->initFile($param["general"]["source"] . "/" . $file, $param["general"]["separator"]);
                $structure = $import->getStructureCsv($param["general"]["headerLine"], $param["general"]["separator"], $param["csv"]);
                if ($param["general"]["mode"] == "debug") {
                    echo "Structure:" . PHP_EOL;
                    printr($structure);
                    echo "param[csv]:" . PHP_EOL;
                    printr($param["csv"]);
                }
                $data = $import->getContent($param["general"]["numline"]);
                $import->fileClose();
                $pdo->beginTransaction();
                $numline = $param["general"]["numline"];
                foreach ($data as $row) {
                    if (strlen($row[0]) > 0) {
                        $newitem = array(
                            $param["table"]["measure_id"] => 0,
                            $param["table"]["station"] => $param["stations"][$param["general"]["station"]]
                        );
                        $ldate = $row[$param["table"]["datefield"]];
                        if (strlen($ldate) == 16) {
                            $ldate = $ldate . ":00";
                        }
                        $newitem[$param["table"]["date"]] = $ldate;
                        /**
                         * Extract the data of the row
                         */
                        foreach ($structure as $key => $fieldname) {
                            if ($row[$key] > 0) {
                                $newitem[$fieldname] = $row[$key];
                            }
                        }
                        if ($param["general"]["mode"] == "debug") {
                            echo "Data in file:" . PHP_EOL;
                            printr($row);
                            echo "Data ready to import:" . PHP_EOL;
                            printr($newitem);
                            die;
                        }
                        /**
                         * Write data in table
                         */
                        $measure->ecrire($newitem);
                    }
                    $numline++;
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
                $message->set("Ligne potentiellement en erreur : " . $numline);
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
        $line = utf8_decode($line);
    }
    echo ($line . PHP_EOL);
}
echo (PHP_EOL);
