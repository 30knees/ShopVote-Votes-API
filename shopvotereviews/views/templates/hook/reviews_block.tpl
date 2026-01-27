{**
 * ShopVote Reviews - Reviews Block Template
 * Full reviews block for homepage/dedicated display
 *}

{if $shopvote_has_data}
<section class="shopvote-reviews-block">
    <div class="shopvote-reviews-header">
        <h2 class="shopvote-reviews-title">{l s='What Our Customers Say' d='Modules.Shopvotereviews.Shop'}</h2>

        {if $shopvote_summary}
        <div class="shopvote-reviews-summary">
            <div class="shopvote-summary-rating">
                <span class="shopvote-summary-stars">{$shopvote_stars_html nofilter}</span>
                <span class="shopvote-summary-score">{$shopvote_summary.rating_value_stars|number_format:1:',':''}</span>
                <span class="shopvote-summary-max">/ 5</span>
            </div>
            <div class="shopvote-summary-count">
                {l s='Based on %count% reviews' sprintf=['%count%' => $shopvote_summary.ratings_count|intval] d='Modules.Shopvotereviews.Shop'}
            </div>
        </div>
        {/if}
    </div>

    {if $shopvote_reviews|count > 0}
    <div class="shopvote-reviews-list">
        {foreach from=$shopvote_reviews item=review}
        <div class="shopvote-review-item{if $review.is_verified} shopvote-verified{/if}">
            <div class="shopvote-review-header">
                <div class="shopvote-review-rating">
                    {for $i=1 to 5}
                        {if $i <= $review.review_rating_stars}
                        <span class="shopvote-star shopvote-star-full">★</span>
                        {else}
                        <span class="shopvote-star shopvote-star-empty">☆</span>
                        {/if}
                    {/for}
                </div>

                <div class="shopvote-review-meta">
                    {if $shopvote_show_reviewer_name && $review.reviewer}
                    <span class="shopvote-reviewer">{$review.reviewer|escape:'htmlall':'UTF-8'}</span>
                    {/if}

                    {if $review.review_date}
                    <span class="shopvote-review-date">{$review.review_date|date_format:'%d.%m.%Y'}</span>
                    {/if}

                    {if $review.is_verified}
                    <span class="shopvote-verified-badge" title="{l s='Verified Purchase' d='Modules.Shopvotereviews.Shop'}">✓</span>
                    {/if}
                </div>
            </div>

            {if $review.review_text_excerpt}
            <div class="shopvote-review-text">
                <p>{$review.review_text_excerpt|escape:'htmlall':'UTF-8'}</p>
                {if $review.has_more && $review.review_url}
                <a href="{$review.review_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="shopvote-read-more">
                    {l s='Read more' d='Modules.Shopvotereviews.Shop'} →
                </a>
                {/if}
            </div>
            {/if}

            {if $shopvote_show_responses && $review.answers|count > 0}
            <div class="shopvote-review-answers">
                {foreach from=$review.answers item=answer}
                <div class="shopvote-answer shopvote-answer-{$answer.answer_type|lower|escape:'htmlall':'UTF-8'}">
                    <div class="shopvote-answer-header">
                        <span class="shopvote-answer-type">
                            {if $answer.answer_type == 'Shop'}
                                {l s='Shop Response' d='Modules.Shopvotereviews.Shop'}
                            {else}
                                {l s='Customer Reply' d='Modules.Shopvotereviews.Shop'}
                            {/if}
                        </span>
                        {if $answer.answer_date}
                        <span class="shopvote-answer-date">{$answer.answer_date|date_format:'%d.%m.%Y'}</span>
                        {/if}
                    </div>
                    <div class="shopvote-answer-text">
                        <p>{$answer.answer_text|escape:'htmlall':'UTF-8'}</p>
                    </div>
                </div>
                {/foreach}
            </div>
            {/if}
        </div>
        {/foreach}
    </div>
    {else}
    <div class="shopvote-no-reviews">
        <p>{l s='No reviews yet.' d='Modules.Shopvotereviews.Shop'}</p>
    </div>
    {/if}

    <div class="shopvote-reviews-footer">
        {if $shopvote_profile_url}
        <a href="{$shopvote_profile_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" class="shopvote-view-all btn btn-primary">
            {l s='View all reviews on ShopVote' d='Modules.Shopvotereviews.Shop'}
        </a>
        {/if}
        <div class="shopvote-attribution">{$shopvote_attribution|escape:'htmlall':'UTF-8'}</div>
    </div>
</section>
{/if}
