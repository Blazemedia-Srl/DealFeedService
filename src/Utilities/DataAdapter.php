<?php

namespace Blazemedia\App\Utilities;

use Carbon\Carbon;

class DataAdapter {
    function __construct() {
    }

    public function getData($data) {
        
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s O', $data['dealEndTime']);
        if ($endDate->isPast()){
            echo "Skip ".$endDate->format('d/m/Y');
            return false;
        } 
        $categories = explode("/", $data['subcategoryPath1']);

        unset($data['category']);
        unset($data['imageURL']);
        unset($data['browseNodeId1']);
        unset($data['browseNodeId2']);
        unset($data['subcategoryPath2']);
        unset($data['marketingMessage']);

        $dateStart= Carbon::createFromFormat('Y-m-d H:i:s O', $data['dealStartTime']);
        $dateEnd =  Carbon::createFromFormat('Y-m-d H:i:s O', $data['dealEndTime']);

        $format = 'd/m/Y H:i:s';
        
        if(env('APPEND_CONNECTION','api') == 'mysql'){
            $format = 'Y-m-d H:i:s';
        }

        return [
            'ASIN' => $data['asin'],
            'Titolo' => substr($data['dealTitle'],0,128),
            'Prezzo' => $data['dealPrice'],
            'Sconto' => $data['discountString'],
            'Prezzo di Referenza' => $data['referencePrice'],
            'Tipo Referenza' => $data['referencePriceType'],
            'Data Inizio' => $dateStart->format($format),
            'Data Fine' => $dateEnd->format($format),
            'Categoria' => isset($categories[1]) ? $categories[1] : '',
            'Sub Categoria' => isset($categories[2]) ? $categories[2] : '',
            'Sub Categoria 2' => isset($categories[3]) ? $categories[3] : '',
            'URL' => $data['dealURL'],
            'DealID' => $data['dealID'],
            'DealType' =>  $data['dealType'],
            'DealState' =>  $data['dealState']

        ];
    }

    public function getHeader() {
        return [
            'ASIN', 'Titolo',
            'Prezzo', 'Sconto',
            'Prezzo di Referenza', 'Tipo Referenza',
            'Data Inizio', 'Data Fine', 'Categoria', 'Sub Categoria',
            'Sub Categoria 2', 'URL', 'DealID', 'DealType', 'DealState'
        ];
    }
}
