<?php
class ImportException extends Exception
{ }

/**
 * Classe de gestion des imports csv
 * 
 * @author quinton
 *        
 */
class Import
{

    private $separator = ";";

    private $utf8_encode = false;

    private $handle;

    private $header = array();

    public $minuid, $maxuid;


    /**
     * Init file function
     * Get the first line for header
     * 
     * @param string  $filename
     * @param string  $separator
     * @param boolean $utf8_encode
     * 
     * @throws ImportException
     */
    function initFile($filename, $separator = ";")
    {
        if ($separator == "tab" || $separator == "t") {
            $separator = "\t";
        }
        $this->separator = $separator;
        /*
         * File open
         */
        if ($this->handle = fopen($filename, 'r')) {
            $fileContent = array();
            /**
             * Positionnement après la ligne d'entete
             */
            for ($i = 1; $i < $headerLine; $i++) {
                $data = $this->readLine();
            }
            /**
             * Recuperation de l'ensemble des donnees
             */
            while (false !== ($data = $this->readLine())) {
                $fileContent[] = $data;
            }
            $this->fileClose();
            return $fileContent;
        } else {
            throw new ImportException($filename . " non trouvé ou non lisible", $filename);
        }
    }

    /**
     * Generate the structure of the file by reading the first lines
     * The lines beginning with V give the structure
     *
     * @param integer $headerLine: first line to be reading to get the structure
     * @return array
     */
    function getStructure($headerLine = 2)
    {
        $firstChar = "V";
        /**
         * Positionnement après la ligne d'entete
         */
        for ($i = 1; $i < $headerLine; $i++) {
            fgets($this->handle);
        }
        $eot = false;
        $structure = array();
        while (!eot) {
            $row = fgets($this->handle);
            if (substr($row[0], 0, 1) == $firstChar) {
                $fields = explode(",",$row);
                $radical = strtolower(substr($fields[1], 0, 4));
                if (in_array($radical, array("temp", "sali", "turb","oxyg", "fluo", "cond"))) {
                    /**
                     * Get the position of the field
                     */
                    $col1 = explode(":",$fields[0]);
                    $structure[$radical.$fields[2]] = $col1[1];
                }
            }
        }
        rewind($this->handle);
        return $structure;
    }

    function getContent()
    { }

    /**
     * Read a line
     *
     * @return array|NULL
     */
    function readLine()
    {
        if ($this->handle) {
            return fgetcsv($this->handle, 0, $this->separator);
        } else {
            return false;
        }
    }

    /**
     * Read the csv file, and return an associative array
     * 
     * @return mixed[][]
     */
    function getContentAsArray()
    {
        $data = array();
        $nb = count($this->header);
        while (($line = $this->readLine()) !== false) {
            $dl = array();
            for ($i = 0; $i < $nb; $i++) {
                $dl[$this->header[$i]] = $line[$i];
            }
            $data[] = $dl;
        }
        return $data;
    }

    /**
     * Close the file
     */
    function fileClose()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }
}
