<div class="table-container clearfix">
    <table class="table table-list hidden">
        <thead>
        <tr>
            <th>{$LANG.orderproduct}</th>
            <th>{$LANG.clientareaaddonpricing}</th>
            <th>{$LANG.clientareahostingnextduedate}</th>
            <th>{$LANG.clientareastatus}</th>
            <th class="responsive-edit-button" style="display: none;"></th>
        </tr>
        </thead>
        <tbody>
        {foreach key=num item=service from=$services}
            <tr onclick="clickableSafeRedirect(event, 'clientarea.php?action=productdetails&amp;id={$service.id}', false)">
                <td><strong>{$service.product}</strong>{if $service.domain}<br /><a href="http://{$service.domain}" target="_blank">{$service.domain}</a>{/if}</td>
                <td class="text-center" data-order="{$service.amountnum}">{$service.amount}<br />{$service.billingcycle}</td>
                <td class="text-center"><span class="hidden">{$service.normalisedNextDueDate}</span>{$service.nextduedate}</td>
                <td class="text-center"><span class="label status status-{$service.status|strtolower}">{$service.statustext}</span></td>
                <td class="responsive-edit-button" style="display: none;">
                    <a href="clientarea.php?action=productdetails&amp;id={$service.id}" class="btn btn-block btn-info">
                        {$LANG.manageproduct}
                    </a>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    <div class="text-center" id="tableLoading">
        <p><i class="fas fa-spinner fa-spin"></i> {$LANG.loading}</p>
    </div>
</div>
