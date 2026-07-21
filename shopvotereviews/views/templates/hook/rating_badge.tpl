{**
 * ShopVote Reviews - Rating Badge Template
 * Badge display for footer
 *}

{if $shopvote_has_data && $shopvote_summary}
<div class="shopvote-rating-badge"
     data-shopvote-metric-endpoint="{$shopvote_metric_endpoint|escape:'htmlall':'UTF-8'}"
     data-shopvote-placement="{$shopvote_placement|escape:'htmlall':'UTF-8'}"
     data-shopvote-metric-expires="{$shopvote_metric_expires|intval}"
     data-shopvote-metric-shop="{$shopvote_metric_shop_id|intval}"
     data-shopvote-view-signature="{$shopvote_view_signature|escape:'htmlall':'UTF-8'}"
     data-shopvote-click-signature="{$shopvote_click_signature|escape:'htmlall':'UTF-8'}">
    <a href="{$shopvote_reviews_url|escape:'htmlall':'UTF-8'}" class="shopvote-badge-link" title="{l s='Read our latest customer reviews' d='Modules.Shopvotereviews.Shop'}">
        <div class="shopvote-badge-content">
            <div class="shopvote-badge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </div>
            <div class="shopvote-badge-info">
                <div class="shopvote-badge-stars">{$shopvote_stars_html nofilter}</div>
                <div class="shopvote-badge-rating">
                    <strong>{$shopvote_summary.rating_value_stars|number_format:1:',':''}</strong> / 5
                </div>
                <div class="shopvote-badge-count">
                    {l s='%count% ratings' sprintf=['%count%' => $shopvote_summary.ratings_count|intval] d='Modules.Shopvotereviews.Shop'}
                </div>
            </div>
        </div>
        <div class="shopvote-badge-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</div>
    </a>
</div>
{/if}
