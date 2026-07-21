(function () {
  'use strict';

  var config = document.getElementById('shopvote-easyreviews-config');
  if (!config || typeof window.loadSRT !== 'function') {
    return;
  }

  window.myLanguage = config.dataset.language;
  window.loadSRT(config.dataset.token, 'https');
}());
