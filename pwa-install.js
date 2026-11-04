(function () {
  'use strict';

  var card = document.getElementById('pwaInstallCard');
  var installButton = document.getElementById('pwaInstallButton');
  var dismissButton = document.getElementById('pwaDismissButton');
  var help = document.getElementById('pwaInstallHelp');
  var deferredPrompt = null;
  var dismissedForPage = false;

  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  var isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
    window.navigator.standalone === true;

  function showCard() {
    if (!card || isStandalone || dismissedForPage) return;
    card.hidden = false;
  }

  function showHelp(message) {
    if (!help) return;
    help.textContent = message;
    help.classList.add('is-visible');
  }

  // Register the worker for installability. It deliberately does not cache
  // login pages, PHP responses, sessions, or private subscriber information.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (error) {
        console.warn('[PWA] Service worker registration failed:', error);
      });
    });
  }

  // Keep the install invitation visible even if the browser has not yet
  // decided that it can offer a native install prompt.
  if (!isStandalone) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', showCard, { once: true });
    } else {
      showCard();
    }
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    showCard();
    if (installButton) installButton.textContent = 'Add to Home Screen';
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    if (card) card.hidden = true;
    showHelp('SkyTruFiber has been added to your home screen. Tap the SkyTruFiber icon to open the subscriber login page.');
  });

  if (installButton) {
    installButton.addEventListener('click', async function () {
      // Android/Chromium: this opens the real browser-controlled install sheet.
      if (deferredPrompt) {
        try {
          deferredPrompt.prompt();
          var choice = await deferredPrompt.userChoice;
          deferredPrompt = null;

          if (choice && choice.outcome === 'accepted') {
            if (card) card.hidden = true;
          } else {
            showHelp('No problem. You can install SkyTruFiber later from your browser menu using “Install app” or “Add to Home Screen.”');
          }
        } catch (error) {
          console.warn('[PWA] Native install prompt could not be opened:', error);
          showManualInstructions();
        }
        return;
      }

      showManualInstructions();
    });
  }

  function showManualInstructions() {
    if (isIOS) {
      showHelp('To install on iPhone or iPad: open this page in Safari, tap the Share button, choose “Add to Home Screen,” then tap “Add.” Open the SkyTruFiber icon to reach the login page.');
      return;
    }

    var isAndroid = /android/i.test(navigator.userAgent);
    if (isAndroid) {
      showHelp('To install now: in Chrome, tap the ⋮ menu at the top, choose “Install app” or “Add to Home screen,” then confirm. Open the SkyTruFiber icon to reach the login page. If the install option is missing, update Chrome and reopen this page over the internet.');
    } else {
      showHelp('Your browser has not offered its install prompt yet. Try opening this secure page in Google Chrome or Microsoft Edge, then use the browser menu and choose “Install app.”');
    }
  }

  if (dismissButton) {
    dismissButton.addEventListener('click', function () {
      dismissedForPage = true;
      if (card) card.hidden = true;
    });
  }
})();
