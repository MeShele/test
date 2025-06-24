<div class="panel">
    <div class="panel-heading">
        <i class="icon-money"></i> {l s='FreedomPay Payment Information'}
    </div>
    <div class="panel-body">
        {if isset($transaction_id)}
            <div class="alert alert-info">
                <p><strong>{l s='Transaction ID'}:</strong> {$transaction_id}</p>
                <p><strong>{l s='Payment Method'}:</strong> {$payment_method}</p>
                <p><strong>{l s='Amount'}:</strong> {$amount} {$currency}</p>
                <p><strong>{l s='Payment Date'}:</strong> {$payment_date}</p>
            </div>
        {else}
            <div class="alert alert-warning">
                {l s='No payment information available for this order'}
            </div>
        {/if}
    </div>
</div>