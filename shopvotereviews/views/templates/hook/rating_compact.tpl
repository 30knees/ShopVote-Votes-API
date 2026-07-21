{if $shopvote_has_data && $shopvote_summary}
<aside class="shopvote-rating-compact shopvote-placement-{$shopvote_placement|escape:'htmlall':'UTF-8'}"
       data-shopvote-metric-endpoint="{$shopvote_metric_endpoint|escape:'htmlall':'UTF-8'}"
       data-shopvote-placement="{$shopvote_placement|escape:'htmlall':'UTF-8'}"
       data-shopvote-metric-expires="{$shopvote_metric_expires|intval}"
       data-shopvote-metric-shop="{$shopvote_metric_shop_id|intval}"
       data-shopvote-view-signature="{$shopvote_view_signature|escape:'htmlall':'UTF-8'}"
       data-shopvote-click-signature="{$shopvote_click_signature|escape:'htmlall':'UTF-8'}">
    <a href="{$shopvote_reviews_url|escape:'htmlall':'UTF-8'}" class="shopvote-compact-link">
        <strong>{$shopvote_placement_label|escape:'htmlall':'UTF-8'}:</strong>
        {$shopvote_stars_html nofilter}
        <span>{$shopvote_summary.rating_value_stars|number_format:1:',':''} / 5</span>
        <span>({$shopvote_summary.ratings_count|intval})</span>
    </a>
    <span class="shopvote-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</span>
</aside>
{/if}
