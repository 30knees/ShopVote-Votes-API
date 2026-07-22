{**
 * ShopVote Reviews - Compact Homepage Reviews Strip
 *}

{if $shopvote_has_data}
<section class="shopvote-home-strip" aria-labelledby="shopvote-home-reviews-title">
    <div class="shopvote-home-strip-summary">
        <h2 id="shopvote-home-reviews-title" class="shopvote-home-strip-title">
            {l s='What Our Customers Say' d='Modules.Shopvotereviews.Shop'}
        </h2>

        {if $shopvote_summary}
        <div class="shopvote-home-strip-rating">
            <span class="shopvote-home-strip-stars">{$shopvote_stars_html nofilter}</span>
            <strong class="shopvote-home-strip-score">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</strong>
            <span class="shopvote-home-strip-max">/ 5</span>
        </div>
        <div class="shopvote-home-strip-count">
            {l s='Based on %count% reviews' sprintf=['%count%' => $shopvote_summary.ratings_count|intval] d='Modules.Shopvotereviews.Shop'}
        </div>
        {/if}
    </div>

    {if $shopvote_reviews|count > 0}
    <div class="shopvote-home-strip-reviews">
        {foreach from=$shopvote_reviews item=review}
        <article class="shopvote-home-strip-review{if $review.is_verified} shopvote-home-strip-review--verified{/if}">
            <div class="shopvote-home-strip-review-rating" role="img" aria-label="{l s='Rating: %rating% out of 5 stars' sprintf=['%rating%' => $review.review_rating_stars|number_format:1:'.':''] d='Modules.Shopvotereviews.Shop'}">
                {for $i=1 to 5}
                    <span class="shopvote-star{if $i <= $review.review_rating_stars} shopvote-star-full{else} shopvote-star-empty{/if}" aria-hidden="true">{if $i <= $review.review_rating_stars}★{else}☆{/if}</span>
                {/for}
            </div>
            <blockquote class="shopvote-home-strip-quote">
                <p>{$review.review_text_excerpt|escape:'htmlall':'UTF-8'}</p>
            </blockquote>
            <footer class="shopvote-home-strip-meta">
                {if $review.reviewer}
                    <cite class="shopvote-home-strip-reviewer">{$review.reviewer|escape:'htmlall':'UTF-8'}</cite>
                {/if}
                {if $review.review_date}
                    <time class="shopvote-home-strip-date" datetime="{$review.review_date|date_format:'%Y-%m-%d'}">{$review.review_date|date_format:'%d.%m.%Y'}</time>
                {/if}
                {if $review.is_verified}
                    <span class="shopvote-home-strip-verified">✓ {l s='Verified purchase' d='Modules.Shopvotereviews.Shop'}</span>
                {/if}
            </footer>
        </article>
        {/foreach}
    </div>
    {/if}

    <div class="shopvote-home-strip-actions">
        <a href="{$shopvote_reviews_url|escape:'htmlall':'UTF-8'}" class="shopvote-home-strip-cta btn btn-primary">
            {l s='Latest customer reviews' d='Modules.Shopvotereviews.Shop'}
        </a>
        {if $shopvote_profile_url}
        <a href="{$shopvote_profile_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="shopvote-home-strip-source">
            {l s='View verified source on ShopVote' d='Modules.Shopvotereviews.Shop'}
        </a>
        {/if}
    </div>
</section>
{/if}
