<div class="box">
    <h3>{l s='Order confirmation' mod='freedompay'}</h3>
    <p>
        {l s='Your order on %s is complete.' sprintf=[$shop_name] mod='freedompay'}
        <br>
        {l s='Your order reference is %s.' sprintf=[$reference] mod='freedompay'}
        <br>
        {l s='We have sent you this information by email.' mod='freedompay'}
    </p>
    
    {if $status == 'ok'}
        <p>
            {l s='Your payment has been successfully processed.' mod='freedompay'}
        </p>
        <p>
            {l s='For any questions or for further information, please contact our' mod='freedompay'}
            <a href="{$contact_url}">{l s='customer support' mod='freedompay'}</a>.
        </p>
    {else}
        <p class="warning">
            {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='freedompay'}
            <a href="{$contact_url}">{l s='customer support' mod='freedompay'}</a>.
        </p>
    {/if}
</div>