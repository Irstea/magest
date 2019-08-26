<?php

class Measure extends ObjetBDD
{

    function __construct($pdo, $param = array())
    {
        $this->table = "";
        $this->colonnes = array(
        );
        $this->connection = $pdo;
        $this->param = $param;
        
    }
    /**
     * Set the parameters for the implementation table
     *
     *  @param string $tableName
     * @param array $colonnes
     * @return void
     */
    function init($tableName, $colonnes) {
        $this->table = $tableName;
        $this->colonnes = $colonnes;
       parent::__construct($this->connection, $this->param) ;
    }

    function write($data) {
        /**
         * Format the date
         */

    }

}
