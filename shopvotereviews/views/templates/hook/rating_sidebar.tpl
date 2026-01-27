{**
 * ShopVote Reviews - Rating Sidebar Template
 * Sidebar widget for left/right columns
 *}

{if $shopvote_has_data && $shopvote_summary}
<div class="shopvote-sidebar block">
    <h4 class="shopvote-sidebar-title">{l s='Customer Reviews' d='Modules.Shopvotereviews.Shop'}</h4>

    <div class="shopvote-sidebar-content">
        <div class="shopvote-sidebar-rating">
            <div class="shopvote-sidebar-stars">{$shopvote_stars_html nofilter}</div>
            <div class="shopvote-sidebar-score">
                <span class="shopvote-big-rating">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</span>
                <span class="shopvote-max-rating">/ 5</span>
            </div>
        </div>

        <div class="shopvote-sidebar-stats">
            <div class="shopvote-stat">
                <span class="shopvote-stat-count">{$shopvote_summary.ratings_count|intval}</span>
                <span class="shopvote-stat-label">{l s='Total Ratings' d='Modules.Shopvotereviews.Shop'}</span>
            </div>
            <div class="shopvote-stat-breakdown">
                <div class="shopvote-stat-item shopvote-positive">
                    <span class="shopvote-stat-icon">+</span>
                    <span class="shopvote-stat-value">{$shopvote_summary.ratings_positive|intval}</span>
                </div>
                <div class="shopvote-stat-item shopvote-neutral">
                    <span class="shopvote-stat-icon">○</span>
                    <span class="shopvote-stat-value">{$shopvote_summary.ratings_neutral|intval}</span>
                </div>
                <div class="shopvote-stat-item shopvote-negative">
                    <span class="shopvote-stat-icon">-</span>
                    <span class="shopvote-stat-value">{$shopvote_summary.ratings_negative|intval}</span>
                </div>
            </div>
        </div>

        <a href="{$shopvote_profile_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="shopvote-sidebar-link btn btn-outline-primary btn-sm">
            {l s='View all reviews' d='Modules.Shopvotereviews.Shop'}
        </a>

        <div class="shopvote-sidebar-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</div>
    </div>
</div>
{/if}
