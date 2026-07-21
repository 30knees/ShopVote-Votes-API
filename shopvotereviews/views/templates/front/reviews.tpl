{extends file='page.tpl'}

{block name='page_title'}
    {l s='Latest customer reviews' d='Modules.Shopvotereviews.Shop'}
{/block}

{block name='page_content'}
    {include file='module:shopvotereviews/views/templates/hook/reviews_block.tpl'}
{/block}
