<h2>{Lang::trans('DS Records')}</h2>
<div class="table-container clearfix">
    <table class="table table-list">
        <thead>
        <tr>
            <th>{Lang::trans('Key Identifier')}</th>
            <th>{Lang::trans('Algorithm')}</th>
            <th>{Lang::trans('Digest Type')}</th>
            <th>{Lang::trans('Digest')}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$dsRecords item=$record}
            <tr>
                <td>{$record->getKeyTag()}</td>
                <td>{$record->getAlgorithm()}</td>
                <td>{$record->getDigestType()}</td>
                <td>{$record->getDigest()}</td>
            </tr>
        {foreachelse}
            <tr>
                <td colspan="4">No records found</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>
<h2>{Lang::trans('KEY Records')}</h2>
<div class="table-container clearfix">
    <table class="table table-list">
        <thead>
        <tr>
            <th>{Lang::trans('Key Identifier')}</th>
            <th>{Lang::trans('Algorithm')}</th>
            <th>{Lang::trans('Digest Type')}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            {foreach from=$keyRercords item=$record}
                <td>{$record->getFlags()}</td>
                <td>{$record->getAlgorithm()}</td>
                <td>{$record->getPubKey()}</td>
            {foreachelse}
                <td colspan="3">No records found</td>
            {/foreach}
        </tr>
        </tbody>
    </table>
</div>