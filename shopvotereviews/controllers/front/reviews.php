<?php
/**
 * Dedicated local customer reviews page.
 */

declare(strict_types=1);

class ShopVoteReviewsReviewsModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        if (!$this->module->isModuleEnabled() || !$this->module->isConfigured()) {
            Tools::redirect($this->context->link->getPageLink('index', true));
        }

        $this->context->smarty->assign($this->module->getWidgetVariables('reviews_block', [
            'limit' => 25,
            'full_text' => true,
            'placement' => 'reviews_page',
        ]));
        $this->setTemplate('module:shopvotereviews/views/templates/front/reviews.tpl');
    }
}
