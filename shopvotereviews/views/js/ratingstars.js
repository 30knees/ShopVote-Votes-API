/**
 * Loads the validated ShopVote-hosted floating badge after the document exists.
 */
(function () {
    'use strict';

    var config = document.getElementById('shopvote-ratingstars-config');
    if (!config) {
        return;
    }

    var functionName = config.dataset.shopvoteFunction;
    if (!['createRBadge', 'createBadget', 'createVBadge'].includes(functionName)) {
        return;
    }

    var shopId = Number.parseInt(config.dataset.shopvoteShopId, 10);
    var badgeType = Number.parseInt(config.dataset.shopvoteBadgeType, 10);
    var zIndex = config.dataset.shopvoteZIndex === ''
        ? null
        : Number.parseInt(config.dataset.shopvoteZIndex, 10);
    if (!Number.isInteger(shopId) || shopId < 1
        || !Number.isInteger(badgeType) || badgeType < 1 || badgeType > 99
        || (zIndex !== null && (!Number.isInteger(zIndex) || zIndex < 0))) {
        return;
    }

    var language = config.dataset.shopvoteLanguage;
    if (language !== '' && !['DE', 'EN', 'FR', 'IT', 'NL', 'ES'].includes(language)) {
        return;
    }

    var argumentsList;
    try {
        argumentsList = JSON.parse(config.dataset.shopvoteArguments);
    } catch (error) {
        return;
    }
    if (!Array.isArray(argumentsList) || argumentsList.length > 5) {
        return;
    }

    var scriptUrl;
    try {
        scriptUrl = new URL(config.dataset.shopvoteScriptUrl);
    } catch (error) {
        return;
    }
    if (scriptUrl.protocol !== 'https:'
        || scriptUrl.hostname !== 'widgets.shopvote.de'
        || !scriptUrl.pathname.startsWith('/js/')
        || !scriptUrl.pathname.toLowerCase().endsWith('.js')) {
        return;
    }

    window.myShopID = shopId;
    window.myBadgetType = badgeType;
    window.mySrc = 'https';
    if (language !== '') {
        window.myLanguage = language;
    }
    if (zIndex !== null) {
        window.myZIndex = zIndex;
    }

    var script = document.createElement('script');
    script.src = scriptUrl.href;
    script.dataset.shopvoteRatingstars = 'true';
    script.addEventListener('load', function () {
        var initialize = window[functionName];
        if (typeof initialize === 'function') {
            initialize.apply(window, [shopId, badgeType, 'https'].concat(argumentsList));
        }
    }, {once: true});
    document.head.appendChild(script);
}());
