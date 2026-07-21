{* Fixed, module-owned EasyReviews markup. The imported merchant snippet is never rendered. *}
<div id="srt-customer-data" style="display: none;">
    <span id="srt-customer-email">{$shopvote_customer_email|escape:'htmlall':'UTF-8'}</span>
    <span id="srt-customer-reference">{$shopvote_order_reference|escape:'htmlall':'UTF-8'}</span>
</div>

{if $shopvote_order_products|count > 0}
<div id="SHOPVOTECheckoutProducts" style="display: none;" translate="no">
    {foreach from=$shopvote_order_products item=product}
    <span class="SVCheckoutProductItem">
        <span class="sv-i-product-url">{$product.url|escape:'htmlall':'UTF-8'}</span>
        <span class="sv-i-product-image-url">{$product.image_url|escape:'htmlall':'UTF-8'}</span>
        <span class="sv-i-product-name">{$product.name|escape:'htmlall':'UTF-8'}</span>
        <span class="sv-i-product-gtin">{$product.gtin|escape:'htmlall':'UTF-8'}</span>
        <span class="sv-i-product-sku">{$product.sku|escape:'htmlall':'UTF-8'}</span>
        <span class="sv-i-product-brand">{$product.brand|escape:'htmlall':'UTF-8'}</span>
    </span>
    {/foreach}
</div>
{/if}

<span id="shopvote-easyreviews-config"
      hidden
      data-token="{$shopvote_easyreviews_token|escape:'htmlall':'UTF-8'}"
      data-language="{$shopvote_easyreviews_language|escape:'htmlall':'UTF-8'}"></span>
<script src="{$shopvote_easyreviews_script_url|escape:'htmlall':'UTF-8'}" defer></script>
<script src="{$urls.base_url|escape:'htmlall':'UTF-8'}modules/shopvotereviews/views/js/easyreviews.js" defer></script>
