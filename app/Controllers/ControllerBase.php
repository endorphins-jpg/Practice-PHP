<?php

namespace App\Controllers; 

class ControllerBase
{
    /**
    * Retorna parametros enviados por post já higienizados.
    * @param array|string|null $index Index para resgatar do $_POST.
    * @author Brunoggdev
    */
    public function dadosPost(null|string|array $index = null, $higienizar = true):mixed
    {
       $retorno = match ( gettype($index) ) {
           'string' => $_POST[$index]??null,
           'array' => array_intersect_key($_POST, $index),
           default => $_POST
       };

       unset($retorno['_method']); 
       
       return $higienizar ? higienizar($retorno) : $retorno;
    }


    /**
    * Retorna parametros enviados por get já higienizados.
    * @author Brunoggdev
    */
    public function dadosGet(null|string|array $index = null, $higienizar = true):mixed
    {
        $retorno = match ( gettype($index) ) {
            'string' => $_GET[$index]??null,
            'array' => array_intersect_key($_GET, $index),
            default => $_GET
        };
 
        unset($retorno['_method']);
        
        return $higienizar ? higienizar($retorno) : $retorno;
    }
}
