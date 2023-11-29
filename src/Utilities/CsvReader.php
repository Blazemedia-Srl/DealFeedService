<?php

namespace Blazemedia\App\Utilities;

/**
 * importa un CSV e consente di effettuare delle query a partire da uno o più campi
 */
class CsvReader {

    protected $handle;
    protected $fields;

    function __construct( string $csvFilePath, bool $firstLineHeader = true) {

        $this->handle = fopen( $csvFilePath, "r"); 

        $this->fields = $this->getFields( $firstLineHeader );
    }

    function __destruct() {
    
        fclose($this->handle);
    }

    function getFields( $firstLineHeader ) {

        if( $firstLineHeader ) { 

            if ( $this->handle !== FALSE ) {

                if ( ( $data = fgetcsv( $this->handle ) ) !== FALSE) {
                
                    return $data;                
                }
            } 
        }

        return [];
    }

    function forEach( callable $callback , $associative_array = true) {

        if ( $this->handle !== FALSE ) {

            rewind( $this->handle );

            while ( ( $data = fgetcsv( $this->handle ) ) !== FALSE) {

                $callback( ($associative_array)?$this->addFieldNames( $data ): $data );
            }
            return;
        }
    }

    function addFieldNames( $data ) {

        $fields = empty( $this->fields ) ? range( 0, count($data) -1 ) : $this->fields;

        $associative_array = [];

        foreach( $data as $key => $item ){

            $associative_array[ (isset($fields[$key]))?$fields[$key]:'' ] = $item;
        }

        return $associative_array;
    }


    /**
     * Undocumented function
     *
     * @param array $conditions - associative array with pairs field => value
     * @return void
     */
    function query( $conditions ) {

        $result = [];
        
        $this->forEach( function ( $data ) use( $conditions, &$result ) {

            $verify = true;
            
            foreach( $conditions as $field => $value ) {
                $verify = $verify && $data[ $field ] == $value;
            }

            if( $verify ) $result[] = $data;
        });

        return $result;
    }
}
