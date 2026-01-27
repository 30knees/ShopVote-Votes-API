{**
 * ShopVote Reviews - Rating Snippet Template
 * Small inline rating display for header/nav
 *}

{if $shopvote_has_data && $shopvote_summary}
<div class="shopvote-rating-snippet">
    <a href="{$shopvote_profile_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" title="{l s='View our ratings on ShopVote' d='Modules.Shopvotereviews.Shop'}">
        <span class="shopvote-snippet-stars">{$shopvote_stars_html nofilter}</span>
        <span class="shopvote-snippet-rating">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</span>
        <span class="shopvote-snippet-count">({$shopvote_summary.ratings_count|intval})</span>
    </a>
    <span class="shopvote-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</span>
</div>
{/if}
