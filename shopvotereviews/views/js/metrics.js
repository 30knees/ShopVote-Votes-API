(function () {
  'use strict';

  function send(element, eventName, signatureAttribute) {
    if (!element) {
      return;
    }
    var signature = element.dataset[signatureAttribute];
    if (!signature || !element.dataset.shopvoteMetricEndpoint) {
      return;
    }

    var body = new URLSearchParams({
      event: eventName,
      placement: element.dataset.shopvotePlacement,
      expires: element.dataset.shopvoteMetricExpires,
      shop: element.dataset.shopvoteMetricShop,
      signature: signature
    });

    window.fetch(element.dataset.shopvoteMetricEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    }).catch(function () {});
  }

  var widgets = document.querySelectorAll('[data-shopvote-view-signature]');
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          send(entry.target, 'widget_view', 'shopvoteViewSignature');
          observer.unobserve(entry.target);
        }
      });
    }, {threshold: 0.25});
    widgets.forEach(function (widget) { observer.observe(widget); });
  } else {
    widgets.forEach(function (widget) { send(widget, 'widget_view', 'shopvoteViewSignature'); });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-shopvote-profile-click]');
    if (link) {
      send(link.closest('[data-shopvote-view-signature]'), 'shopvote_profile_click', 'shopvoteClickSignature');
    }
  });
}());
