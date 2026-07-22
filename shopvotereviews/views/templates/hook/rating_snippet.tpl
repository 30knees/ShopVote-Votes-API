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
    <a class="shopvote-snippet-link{if $shopvote_header_badge_url} shopvote-snippet-link--with-seal{/if}"
       href="{if $shopvote_profile_url}{$shopvote_profile_url|escape:'htmlall':'UTF-8'}{else}{$shopvote_reviews_url|escape:'htmlall':'UTF-8'}{/if}"
       {if $shopvote_profile_url}
           target="_blank"
           rel="noopener noreferrer"
           data-shopvote-profile-click
           title="{l s='View the full ShopVote profile' d='Modules.Shopvotereviews.Shop'}"
       {else}
           title="{l s='Read our latest customer reviews' d='Modules.Shopvotereviews.Shop'}"
       {/if}
       aria-label="{l s='ShopVote rating: %rating% out of 5 from %count% ratings' sprintf=['%rating%' => $shopvote_summary.rating_value_stars|number_format:1:'.':'', '%count%' => $shopvote_summary.ratings_count|intval] d='Modules.Shopvotereviews.Shop'}">
        {if $shopvote_header_badge_url}
            <img class="shopvote-snippet-seal"
                 src="{$shopvote_header_badge_url|escape:'htmlall':'UTF-8'}"
                 width="50"
                 height="50"
                 alt=""
                 aria-hidden="true"
                 decoding="async">
        {/if}
        <span class="shopvote-snippet-copy">
            <span class="shopvote-snippet-scoreline">
                <span class="shopvote-snippet-stars">{$shopvote_stars_html nofilter}</span>
                <strong class="shopvote-snippet-rating">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</strong>
                <span class="shopvote-snippet-max">/ 5</span>
            </span>
            <span class="shopvote-snippet-details">
                <span class="shopvote-snippet-count">{l s='%count% ratings' sprintf=['%count%' => $shopvote_summary.ratings_count|intval] d='Modules.Shopvotereviews.Shop'}</span>
                <span class="shopvote-snippet-separator" aria-hidden="true">·</span>
                <span class="shopvote-snippet-source">ShopVote.de</span>
            </span>
        </span>
    </a>
</div>
{/if}
