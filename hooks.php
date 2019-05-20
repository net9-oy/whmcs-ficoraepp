<?php /** @noinspection PhpInconsistentReturnPointsInspection */
if(!defined('WHMCS'))
    die('This file cannot be accessed directly');

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * This hook will validate the additional fields that are required for each registrant type by Ficora
 *
 * An error will be returned if the necessary fields are not filled by the user
 */
add_hook('ShoppingCartValidateDomainsConfig', 50, function ($vars)
{
    global $smarty;
    $errors = [];

    foreach($_SESSION['cart']['domains'] as $key => $domain) {
        if(isset($vars['domainfield'][$key]['registrant_type']) &&
            (substr($domain['domain'], -strlen('.fi')) === '.fi')) {
            switch($vars['domainfield'][$key]['registrant_type']) {
                case '0':
                    if(!$vars['domainfield'][$key]['idNumber']) {
                        $errors[] = 'ID number is required for Finnish residents';
                    }
                    break;
                case '10':
                    if(!$vars['domainfield'][$key]['birthdate']) {
                        $errors[] = 'Birth date is required for foreign private persons';
                    }
                    break;
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                    if(!trim($vars['domainfield'][$key]['registerNumber'])) {
                        $errors[] = 'VAT/Register number is a required field for corporate bodies';
                    }
                    break;
                default:
                    $errors[] = 'Registrant type is missing';
                    break;
            }
        }
    }

    return $errors;
});

/**
 * This hook adds additional fields to transfer orders when the domains are being configured
 *
 * By default WHMCS does not provide additional fields for Transfer order type, but Ficora requires the additional info
 */
add_hook('ClientAreaPage', 1, function ($vars)
{
    if($vars['templatefile'] !== 'configuredomains')
        return;

    if(!array_key_exists('domains', $vars))
        return;

    foreach($vars['domains'] as $key => $domain) {
        if(substr($domain['domain'], -strlen('.fi')) !== '.fi')
            continue;

        if($domain['fields'])
            continue;

        $additflds = new WHMCS\Domains\AdditionalFields();
        $additflds->setTLD('fi');
        $vars['domains'][$key]['fields'] = $additflds->getFieldsForOutput($key);
        $vars['domains'][$key]['configtoshow'] = true;
    }

    return $vars;
});

/**
 * This hook will save additional fields filled on a Transfer order domain configuration screen to $_SESSION so the
 * field data can be used at hook CartTotalAdjustment to save it to the database
 *
 * By default WHMCS does not provide additional fields for Transfer order type, but Ficora requires the additional info
 */
add_hook('ShoppingCartValidateDomainsConfig', 1, function ($vars)
{
    if(!array_key_exists('domainfield', $_POST))
        return;

    foreach ($_SESSION['cart']['domains'] as $key => $domain) {
        if(substr($domain['domain'], -strlen('.fi')) !== '.fi')
            continue;

        if($domain['fields'])
            continue;

        if($domain['type'] !== 'transfer')
            continue;

        if(!array_key_exists($key, $_POST['domainfield']))
            continue;

        $_SESSION['cart']['domains'][$key]['fields'] = $_POST['domainfield'][$key];
    }
});

/**
 * This hook will save additional fields from $_SESSION to the database when the order type is Transfer
 *
 * By default WHMCS does not provide additional fields for Transfer order type, but Ficora requires the additional info
 */
add_hook('CartTotalAdjustment', 1, function ()
{
    foreach ($_SESSION['cart']['domains'] as $key => $domain) {
        if(substr($domain['domain'], -strlen('.fi')) !== '.fi')
            continue;

        if($domain['type'] !== 'transfer')
            continue;

        if(!$domain['fields'])
            continue;

        $id = Capsule::table('tbldomains')
            ->where('domain', $domain['domain'])
            ->value('id');

        if(!$id)
            continue;

        $fields = new WHMCS\Domains\AdditionalFields();
        $fields->setTLD('fi');
        $fields->setFieldValues($domain['fields']);
        $fields->saveToDatabase($id);
    }
});

/**
 * This hook will hide the unnecessary additional fields for different registrant types
 *
 * For example a private person does not need to fill in a company VAT ID. This way the order process is much more clear
 * and simple for customers
 */
add_hook('ClientAreaHeadOutput', 50, function($vars) {
    if($vars['templatefile'] !== 'configuredomains')
        return;

    foreach($_SESSION['cart']['domains'] as $key => $domain) {
        if(substr($domain['domain'], -strlen('.fi')) !== '.fi')
            continue;

        $domains[] = $key;
    }

    ob_start();
    ?>
        <script type="text/javascript">
            $(document).ready(function() {
                <?php foreach($domains as $id): ?>
                    $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][1]"]').closest('.row').show();
                    $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][2]"]').closest('.row').hide();
                    $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][3]"]').closest('.row').hide();
                    $('#frmConfigureDomains select[name="domainfield[<?= $id ?>][0]"]').change(function() {
                        if(this.value === '10') {
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][1]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][2]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][3]"]').closest('.row').show();
                        } else if(this.value === '0') {
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][1]"]').closest('.row').show();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][2]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][3]"]').closest('.row').hide();
                        } else {
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][1]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][2]"]').closest('.row').show();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][3]"]').closest('.row').hide();
                        }
                    });
                <?php endforeach; ?>
            });
        </script>
    <?php
    return ob_get_clean();
});

/**
 * Ficora transfers execute immediately and they either fail or succeed. This is why default WHMCS Transfer Sync
 * functionality does not suffice.
 *
 * Instead on successful transfer, FicoraEPP For WHMCS will automatically update the domain from Pending Transfer to
 * state Active
 */
add_hook('AfterRegistrarTransfer', 50, function($vars) {
    if(@$vars['params']['registrar'] !== 'ficoraepp') {
        return;
    }

    /** @noinspection UnusedFunctionResultInspection */
    RegCallFunction($vars['params'], 'CompleteTransfer');
});