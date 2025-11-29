<?php
use MLocati\ComuniItaliani\Finder;

function checkAndGenerateCF($cognome, $data_nascita, $sesso, $codice_catastale)
{

    //return $cf_calcolato;
}

function checkCF($cf_calcolato)
{

}

function getCodiceCatastale($comune)
{
    $finder = new Finder();
    $municipalities = $finder->findMunicipalitiesByName($comune, false);
    if (count($municipalities) != 1) {
        return false;
    }
    $comune = reset($municipalities);
    return $comune->getCadastralCode();
}