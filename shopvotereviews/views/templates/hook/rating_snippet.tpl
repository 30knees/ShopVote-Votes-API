{**
 * ShopVote Reviews - Rating Snippet Template
 * Small inline rating display for header/nav
 *}

{if $shopvote_has_data && $shopvote_summary}
<div class="shopvote-rating-snippet"
     data-shopvote-metric-endpoint="{$shopvote_metric_endpoint|escape:'htmlall':'UTF-8'}"
     data-shopvote-placement="{$shopvote_placement|escape:'htmlall':'UTF-8'}"
     data-shopvote-metric-expires="{$shopvote_metric_expires|intval}"
     data-shopvote-metric-shop="{$shopvote_metric_shop_id|intval}"
     data-shopvote-view-signature="{$shopvote_view_signature|escape:'htmlall':'UTF-8'}"
     data-shopvote-click-signature="{$shopvote_click_signature|escape:'htmlall':'UTF-8'}">
    <a href="{$shopvote_reviews_url|escape:'htmlall':'UTF-8'}" title="{l s='Read our latest customer reviews' d='Modules.Shopvotereviews.Shop'}">
        <span class="shopvote-snippet-stars">{$shopvote_stars_html nofilter}</span>
        <span class="shopvote-snippet-rating">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</span>
        <span class="shopvote-snippet-count">({$shopvote_summary.ratings_count|intval})</span>
    </a>
    <span class="shopvote-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</span>
</div>
{/if}
