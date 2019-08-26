<?php
class ImportException extends Exception
{
}

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
    function initFile($filename, $separator = ";",  $headerLine = 6 )
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
            for($i = 0; $i < $headerLine; $i++) {
                $data = $this->readLine();
            }
            /**
             * Recuperation de l'ensemble des donnees
             */
            while(false !== ($data = $this->readLine())){
                $fileContent[] = $data;
            }
            $this->fileClose();
            return $fileContent;
        } else {
            throw new ImportException($filename ." non trouvé ou non lisible",$filename);
        }
    }

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
            for ($i = 0; $i < $nb; $i ++) {
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
