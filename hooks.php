<?php /** @noinspection PhpInconsistentReturnPointsInspection */
if(!defined('WHMCS'))
    die('This file cannot be accessed directly');

use Illuminate\Database\Capsule\Manager as Capsule;

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
        $domainfields = $additflds->getFieldsForOutput($key);

        $vars['domains'][$key]['fields'] = $domainfields;
        $vars['domains'][$key]['configtoshow'] = true;
    }

    return $vars;
});

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
                        if(this.value === '0') {
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][1]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][2]"]').closest('.row').hide();
                            $('#frmConfigureDomains input[name="domainfield[<?= $id ?>][3]"]').closest('.row').show();
                        } else if(this.value === '00') {
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