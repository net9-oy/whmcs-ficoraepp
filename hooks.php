<?php
if(!defined("WHMCS"))
    die("This file cannot be accessed directly");

use Illuminate\Database\Capsule\Manager as Capsule;

add_hook("ShoppingCartValidateCheckout", 1, function ($vars)
{
    global $smarty;
    new DateTime();

    if(!array_key_exists('domains', $_SESSION['cart']))
        return null;

    if(empty($_SESSION['cart']['domains']))
        return null;

    if(count($_SESSION['cart']['domains']) < 1)
        return null;

    $fields = collect(Capsule::table('tblregistrars')
        ->where('registrar', '=', 'ficoraepp')
        ->whereIn('setting', ['ficora_companyid_field', 'ficora_personid_field'])
        ->pluck('value', 'setting'))->transform(function($item) {
             return decrypt($item);
        });

    if($vars['custtype'] === 'existing') {
        $client = $smarty->get_template_vars('clientsdetails');
        $personId = $client["customfields{$fields->get('ficora_personid_field')}"] ?? null;
        $companyId = $client["customfields{$fields->get('ficora_companyid_field')}"] ?? null;
    } else {
        $client = $vars;
        $personId = $client['customfield'][$fields->get('ficora_personid_field')] ?? null;
        $companyId = $client['customfield'][$fields->get('ficora_companyid_field')] ?? null;
    }

    foreach($_SESSION['cart']['domains'] as $domain) {
        if(substr($domain['domain'], -strlen(".fi")) === ".fi") {
            if($client['firstname'] === "")
                $errors[] = "clientareaerrorfirstname";

            if($client['lastname'] === "")
                $errors[] = "clientareaerrorlastname";

            if($client['address1'] === "")
                $errors[] = "clientareaerroraddress1";

            if($client['city'] === "")
                $errors[] = "clientareaerrorcity";

            if($client['postcode'] === "")
                $errors[] = "clientareaerrorpostcode";

            //if($client['phonenumber'] === "" || !preg_match('/^\+\d+\.\d+/', $client['phonenumber']))
            //    $errors[] = "clientareaerrorphonenumber";

            if($client['country'] === 'Finland') {
                if (!$personId && !$companyId) {
                    $errors[] = "clientareaerrorid";
                }
            }

            return $errors ?? null;
        }
    }
});