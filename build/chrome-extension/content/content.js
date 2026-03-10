/* ============================================================
   Avidmock SAT Math Scanner — Content Script
   Handles crop overlay injection and floating result cards.
   ============================================================ */

(function () {
  'use strict';

  // Prevent double-initialization
  if (window.__avidmockContentInit) return;
  window.__avidmockContentInit = true;

  const DASHBOARD_URL = 'https://my.sat.avidmock.com';
  let activeResultCard = null;
  let processingIndicator = null;

  // ---- Message Listener ----

  chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
    switch (msg.type) {
      case 'START_CROP':
        handleStartCrop(msg.screenshot);
        sendResponse({ ok: true });
        break;

      case 'SHOW_RESULT_CARD':
        showResultCard(msg.result);
        sendResponse({ ok: true });
        break;

      case 'PING':
        sendResponse({ ok: true });
        break;

      default:
        break;
    }
    return true;
  });

  // ---- Crop Flow ----

  async function handleStartCrop(screenshotDataUrl) {
    if (!window.__AvidmockCropOverlay) {
      console.error('Avidmock: CropOverlay not loaded');
      return;
    }

    const crop = new window.__AvidmockCropOverlay();
    const croppedBase64 = await crop.activate(screenshotDataUrl);

    if (!croppedBase64) {
      // User cancelled
      return;
    }

    // Show processing indicator
    showProcessingIndicator();

    // Send cropped image to background for API call
    try {
      const response = await chrome.runtime.sendMessage({
        type: 'CROP_COMPLETE',
        imageData: croppedBase64,
      });

      hideProcessingIndicator();

      if (response?.result) {
        showResultCard(response.result);
      } else if (response?.error) {
        showErrorCard(response.error);
      }
    } catch (err) {
      hideProcessingIndicator();
      console.error('Avidmock: Error sending crop data:', err);
      showErrorCard('Could not communicate with the extension. Please try again.');
    }
  }

  // ---- Processing Indicator ----

  function showProcessingIndicator() {
    hideProcessingIndicator();

    processingIndicator = document.createElement('div');
    processingIndicator.className = 'avidmock-ext-processing-indicator';
    processingIndicator.innerHTML = `
      <div class="avidmock-ext-processing-spinner"></div>
      <span class="avidmock-ext-processing-text">Analyzing with AI...</span>
    `;
    document.body.appendChild(processingIndicator);
  }

  function hideProcessingIndicator() {
    if (processingIndicator) {
      processingIndicator.remove();
      processingIndicator = null;
    }
  }

  // ---- Floating Result Card ----

  function showResultCard(result) {
    removeResultCard();

    const card = document.createElement('div');
    card.className = 'avidmock-ext-result-card';

    const difficulty = (result.difficulty || 'medium').toLowerCase();
    const mapping = result.sat_mapping || {};
    const solution = result.solution || {};
    const steps = solution.steps || [];

    card.innerHTML = `
      <div class="avidmock-ext-result-header">
        <div class="avidmock-ext-result-header-left">
          <div class="avidmock-ext-result-logo">A</div>
          <span class="avidmock-ext-result-header-title">Solution Found</span>
        </div>
        <button class="avidmock-ext-result-close" data-action="close">&times;</button>
      </div>
      <div class="avidmock-ext-result-body">
        <div class="avidmock-ext-tag-row">
          <span class="avidmock-ext-tag avidmock-ext-tag-domain">${escapeHtml(mapping.domain || 'Math')}</span>
          <span class="avidmock-ext-tag avidmock-ext-tag-difficulty ${difficulty}">${capitalize(difficulty)}</span>
        </div>
        <div class="avidmock-ext-answer">${escapeHtml(solution.answer || '')}</div>
        <div class="avidmock-ext-steps">
          ${steps
            .map(
              (step, i) => `
            <div class="avidmock-ext-step">
              <div class="avidmock-ext-step-num">${i + 1}</div>
              <div class="avidmock-ext-step-text">${escapeHtml(step)}</div>
            </div>
          `
            )
            .join('')}
        </div>
      </div>
      <div class="avidmock-ext-result-footer">
        <button class="avidmock-ext-footer-btn avidmock-ext-footer-btn-primary" data-action="practice">Practice Similar</button>
        <button class="avidmock-ext-footer-btn avidmock-ext-footer-btn-secondary" data-action="expand">Open in Popup</button>
      </div>
    `;

    // Event delegation
    card.addEventListener('click', (e) => {
      const action = e.target.closest('[data-action]')?.dataset.action;
      if (!action) return;

      switch (action) {
        case 'close':
          removeResultCard();
          break;
        case 'practice':
          const domainKey = mapping.domain_key || 'algebra';
          const skillKey = mapping.skill_key || '';
          window.open(`${DASHBOARD_URL}/practice/${domainKey}/${skillKey}`, '_blank');
          break;
        case 'expand':
          // Store result and open popup
          chrome.storage.local.set({ pendingResult: result });
          // The popup will check for pendingResult on open
          removeResultCard();
          break;
      }
    });

    document.body.appendChild(card);
    activeResultCard = card;

    // Auto-dismiss after 30 seconds
    setTimeout(() => {
      if (activeResultCard === card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.3s ease';
        setTimeout(() => removeResultCard(), 300);
      }
    }, 30000);
  }

  function showErrorCard(errorMessage) {
    removeResultCard();

    const card = document.createElement('div');
    card.className = 'avidmock-ext-result-card';
    card.innerHTML = `
      <div class="avidmock-ext-result-header">
        <div class="avidmock-ext-result-header-left">
          <div class="avidmock-ext-result-logo">A</div>
          <span class="avidmock-ext-result-header-title">Error</span>
        </div>
        <button class="avidmock-ext-result-close" data-action="close">&times;</button>
      </div>
      <div class="avidmock-ext-result-body" style="text-align: center; padding: 24px 16px;">
        <p style="color: #9cb8b5; font-size: 13px; margin-bottom: 12px;">${escapeHtml(errorMessage)}</p>
        <button class="avidmock-ext-btn avidmock-ext-btn-primary" data-action="close" style="font-size: 13px; padding: 8px 20px;">Dismiss</button>
      </div>
    `;

    card.addEventListener('click', (e) => {
      if (e.target.closest('[data-action="close"]')) {
        removeResultCard();
      }
    });

    document.body.appendChild(card);
    activeResultCard = card;
  }

  function removeResultCard() {
    if (activeResultCard) {
      activeResultCard.remove();
      activeResultCard = null;
    }
  }

  // ---- Utilities ----

  function escapeHtml(text) {
    const el = document.createElement('div');
    el.textContent = text || '';
    return el.innerHTML;
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
})();
