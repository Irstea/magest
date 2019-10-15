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
        $this->separator = $this->normalizeSeparator($separator);
        /*
         * File open
         */
        if (!$this->handle = fopen($filename, 'r')) {
            throw new ImportException($filename . " non trouvé ou non lisible", $filename);
        }
    }

    /**
     * Normalize the separator
     *
     * @param string $separator
     * @return string
     */
    private function normalizeSeparator($separator) {
        if ($separator == "tab" || $separator == "t") {
            $separator = "\t";
        }
        if ($separator == "space") {
            $separator = " ";
        }
        return $separator;
    }

    /**
     * Generate the structure of the file by reading the first lines
     * The lines beginning with V give the structure
     *
     * @param integer $headerLine: first line to be reading to get the structure
     * @return array: fields: list of positions of values, numline: number of the first line with data
     */
    function getStructure($firstLine = 2, $separator=",", $unitFieldNumber=2)
    {
        $firstChar = "V";
        $numline = 1;
        $separator = $this->normalizeSeparator($separator);
        /**
         * Positionnement après la ligne d'entete
         */
        for ($i = 1; $i < $firstLine; $i++) {
            fgets($this->handle);
            $numline++;
        }
        $eot = false;
        $structure = array();
        while (!$eot) {
            $row = fgets($this->handle);
            $numline++;
            if (substr($row[0], 0, 1) == $firstChar) {
                /**
                 * Extract the first field if separator is not a tab
                 */
                if ($separator != "\t") {
                    $row = explode("\t",$row)[0];
                }
                $fields = explode($separator, $row);
                $radical = substr($fields[1], 0, 4);
                if (in_array($radical, array("Temp", "Sali", "Turb", "Oxyg", "Fluo", "Cond"))) {
                    /**
                     * Extract the unit and remove end of line
                     */
                    $unit = str_replace("\r",'', utf8_encode($fields[$unitFieldNumber]));
                    $unit = str_replace("\n", '', $unit);

                    /**
                     * Get the position of the field
                     */
                    $col1 = explode(":", $fields[0]);
                    $structure["fields"][$radical . $unit] = $col1[1] + 1;
                }
            } else {
                $eot = true;
            }
        }
        /**
         * Rewind the file at the beginning
         */
        rewind($this->handle);
        /**
         * set the first line of data
         */
        $structure["numline"] = $numline - 1;
        return $structure;
    }
    /**
     * Get the content of data of the file
     *
     * @param int $firstLine: first line where data are
     * @return array
     */
    function getContent($firstLine)
    {
        if (!$this->handle) {
            throw new ImportException("Le fichier n'a pas été ouvert en lecture");
        }
        /**
         * Positionnement après la ligne d'entete
         */
        for ($i = 1; $i < $firstLine; $i++) {
            $this->readLine();
        }
        /**
         * Recuperation de l'ensemble des donnees
         */
        while (false !== ($data = $this->readLine())) {
            $fileContent[] = $data;
        }
        return $fileContent;
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
