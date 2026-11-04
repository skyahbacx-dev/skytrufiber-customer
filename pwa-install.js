(function () {
  'use strict';
  var card = document.getElementById('pwaInstallCard');
  var installButton = document.getElementById('pwaInstallButton');
  var dismissButton = document.getElementById('pwaDismissButton');
  var help = document.getElementById('pwaInstallHelp');
  var deferredPrompt = null;
  var dismissedKey = 'skytrufiber-pwa-install-dismissed';
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (error) {
        console.warn('[PWA] Service worker registration failed:', error);
      });
    });
  }

  function showCard() {
    if (!card || isStandalone || localStorage.getItem(dismissedKey) === '1') return;
    card.hidden = false;
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    showCard();
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    if (card) card.hidden = true;
    if (help) {
      help.textContent = 'SkyTruFiber has been added to your home screen. Open the SkyTruFiber icon to access the login page.';
      help.classList.add('is-visible');
    }
  });

  if (!isStandalone) window.setTimeout(showCard, 1800);

  if (installButton) installButton.addEventListener('click', async function () {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      var choice = await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (choice && choice.outcome === 'accepted') {
        if (card) card.hidden = true;
      }
      return;
    }

    if (!help) return;
    if (isIOS) {
      help.textContent = 'On your iPhone or iPad: tap the Share button in Safari, choose “Add to Home Screen,” then tap “Add.” Open the new SkyTruFiber icon to see the login page.';
    } else {
      help.textContent = 'If your browser shows an install option, open its menu and choose “Install app” or “Add to Home Screen.” Then open the SkyTruFiber icon. If no option appears, try Chrome and make sure you are using the secure website.';
    }
    help.classList.add('is-visible');
  });

  if (dismissButton) dismissButton.addEventListener('click', function () {
    if (card) card.hidden = true;
    try { localStorage.setItem(dismissedKey, '1'); } catch (error) {}
  });
})();
