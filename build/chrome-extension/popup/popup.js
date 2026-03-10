/* ============================================================
   Avidmock SAT Math Scanner — Popup Logic
   ============================================================ */

const API_BASE = 'https://my.sat.avidmock.com';
const API_VISION = `${API_BASE}/api/math-vision.php`;
const DASHBOARD_URL = API_BASE;

// ---- State Management ----

const State = {
  LOGGED_OUT: 'loggedout',
  DASHBOARD: 'dashboard',
  PROCESSING: 'processing',
  RESULT: 'result',
  ERROR: 'error',
};

let currentState = State.LOGGED_OUT;
let userData = null;
let currentResult = null;

// ---- DOM References ----

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const els = {
  stateLoggedOut: $('#state-loggedout'),
  stateDashboard: $('#state-dashboard'),
  stateProcessing: $('#state-processing'),
  stateResult: $('#state-result'),
  stateError: $('#state-error'),
  topbarUser: $('#topbar-user'),
  btnSignin: $('#btn-signin'),
  btnScan: $('#btn-scan'),
  pasteInput: $('#paste-input'),
  btnPasteSend: $('#btn-paste-send'),
  btnBack: $('#btn-back'),
  btnErrorRetry: $('#btn-error-retry'),
  btnPracticeSimilar: $('#btn-practice-similar'),
  btnSaveNotebook: $('#btn-save-notebook'),
  btnClearHistory: $('#btn-clear-history'),
  recentList: $('#recent-list'),
  emptyRecent: $('#empty-recent'),
  statStreak: $('#stat-streak'),
  statScansToday: $('#stat-scans-today'),
  statXp: $('#stat-xp'),
  rateLimitText: $('#rate-limit-text'),
  rateLimitFill: $('#rate-limit-fill'),
  upgradeLink: $('#upgrade-link'),
  resultDomain: $('#result-domain'),
  resultSkill: $('#result-skill'),
  resultDifficulty: $('#result-difficulty'),
  resultProblem: $('#result-problem'),
  resultAnswer: $('#result-answer'),
  resultSteps: $('#result-steps'),
  resultTip: $('#result-tip'),
  xpBanner: $('#xp-banner'),
  xpEarned: $('#xp-earned'),
  errorTitle: $('#error-title'),
  errorMessage: $('#error-message'),
  procStep1: $('#proc-step-1'),
  procStep2: $('#proc-step-2'),
  procStep3: $('#proc-step-3'),
};

// ---- Initialization ----

document.addEventListener('DOMContentLoaded', init);

async function init() {
  bindEvents();
  await checkAuth();
}

function bindEvents() {
  els.btnSignin.addEventListener('click', handleSignIn);
  els.btnScan.addEventListener('click', handleScan);
  els.btnPasteSend.addEventListener('click', handlePasteSend);
  els.btnBack.addEventListener('click', () => showState(State.DASHBOARD));
  els.btnErrorRetry.addEventListener('click', () => showState(State.DASHBOARD));
  els.btnPracticeSimilar.addEventListener('click', handlePracticeSimilar);
  els.btnSaveNotebook.addEventListener('click', handleSaveNotebook);
  els.btnClearHistory.addEventListener('click', handleClearHistory);

  els.pasteInput.addEventListener('input', () => {
    els.btnPasteSend.disabled = els.pasteInput.value.trim().length === 0;
  });

  // Allow Enter to submit paste (Shift+Enter for newline)
  els.pasteInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (els.pasteInput.value.trim()) handlePasteSend();
    }
  });
}

// ---- State Transitions ----

function showState(state) {
  currentState = state;
  const allStates = [
    els.stateLoggedOut,
    els.stateDashboard,
    els.stateProcessing,
    els.stateResult,
    els.stateError,
  ];
  allStates.forEach((el) => el.classList.add('hidden'));

  const map = {
    [State.LOGGED_OUT]: els.stateLoggedOut,
    [State.DASHBOARD]: els.stateDashboard,
    [State.PROCESSING]: els.stateProcessing,
    [State.RESULT]: els.stateResult,
    [State.ERROR]: els.stateError,
  };
  map[state]?.classList.remove('hidden');
}

// ---- Authentication ----

async function checkAuth() {
  try {
    // Attempt to read auth from cookie on .avidmock.com via background
    const response = await chrome.runtime.sendMessage({ type: 'CHECK_AUTH' });

    if (response?.authenticated && response.user) {
      userData = response.user;
      onAuthenticated();
    } else {
      // Fallback: check storage
      const stored = await chrome.storage.local.get(['user', 'session']);
      if (stored.user && stored.session) {
        userData = stored.user;
        onAuthenticated();
      } else {
        showState(State.LOGGED_OUT);
      }
    }
  } catch (err) {
    // If background script isn't available, check storage
    const stored = await chrome.storage.local.get(['user', 'session']);
    if (stored.user && stored.session) {
      userData = stored.user;
      onAuthenticated();
    } else {
      showState(State.LOGGED_OUT);
    }
  }
}

function onAuthenticated() {
  renderUserBar();
  updateStats();
  updateRateLimit();
  loadRecentScans();
  showState(State.DASHBOARD);
}

function renderUserBar() {
  if (!userData) return;
  const initials = (userData.name || 'U')
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  els.topbarUser.innerHTML = `
    <span class="user-name">${escapeHtml(userData.name || 'Student')}</span>
    <div class="user-avatar">${initials}</div>
  `;
}

function updateStats() {
  if (!userData) return;
  els.statStreak.textContent = userData.streak || 0;
  els.statScansToday.textContent = userData.scansToday || 0;
  els.statXp.textContent = formatNumber(userData.xp || 0);
}

function updateRateLimit() {
  if (!userData) return;
  const isPro = userData.tier === 'pro';
  const limit = isPro ? Infinity : 5;
  const used = userData.scansToday || 0;
  const remaining = isPro ? 'Unlimited' : Math.max(0, limit - used);

  els.rateLimitText.textContent = isPro
    ? 'Unlimited scans (Pro)'
    : `${remaining} scan${remaining !== 1 ? 's' : ''} remaining today`;

  if (isPro) {
    els.upgradeLink.style.display = 'none';
    els.rateLimitFill.style.width = '100%';
    els.rateLimitFill.className = 'rate-limit-fill';
  } else {
    els.upgradeLink.style.display = '';
    const pct = ((limit - used) / limit) * 100;
    els.rateLimitFill.style.width = `${Math.max(0, pct)}%`;
    els.rateLimitFill.className = 'rate-limit-fill';
    if (pct <= 20) els.rateLimitFill.classList.add('empty');
    else if (pct <= 40) els.rateLimitFill.classList.add('low');
  }
}

// ---- Sign In ----

function handleSignIn() {
  chrome.tabs.create({ url: `${API_BASE}/login?ref=extension` });
}

// ---- Scan (Screenshot + Crop) ----

async function handleScan() {
  // Check rate limit
  if (userData && userData.tier !== 'pro') {
    const used = userData.scansToday || 0;
    if (used >= 5) {
      showError('Scan Limit Reached', 'You\'ve used all 5 free scans today. Upgrade to Pro for unlimited scans.');
      return;
    }
  }

  try {
    // Send message to content script to show crop overlay
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab) {
      showError('No Active Tab', 'Could not find an active tab to capture.');
      return;
    }

    // First capture the full screenshot
    const screenshotDataUrl = await chrome.tabs.captureVisibleTab(null, {
      format: 'png',
      quality: 100,
    });

    // Send to content script for cropping
    chrome.tabs.sendMessage(tab.id, {
      type: 'START_CROP',
      screenshot: screenshotDataUrl,
    });

    // Close the popup so the user can interact with the crop overlay
    window.close();
  } catch (err) {
    console.error('Scan error:', err);
    showError('Capture Failed', 'Could not capture the screen. Make sure you\'re on a regular web page.');
  }
}

// ---- Paste / Type Problem ----

async function handlePasteSend() {
  const text = els.pasteInput.value.trim();
  if (!text) return;

  // Check rate limit
  if (userData && userData.tier !== 'pro') {
    const used = userData.scansToday || 0;
    if (used >= 5) {
      showError('Scan Limit Reached', 'You\'ve used all 5 free scans today. Upgrade to Pro for unlimited scans.');
      return;
    }
  }

  showState(State.PROCESSING);
  animateProcessingSteps();

  try {
    const result = await sendToAPI({ text, source: 'extension' });
    displayResult(result);
    els.pasteInput.value = '';
    els.btnPasteSend.disabled = true;
  } catch (err) {
    console.error('API error:', err);
    showError('Analysis Failed', err.message || 'Could not analyze the problem. Please try again.');
  }
}

// ---- API Communication ----

async function sendToAPI(payload) {
  const session = (await chrome.storage.local.get('session')).session;

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (session) {
    headers['Authorization'] = `Bearer ${session}`;
  }

  const response = await fetch(API_VISION, {
    method: 'POST',
    headers,
    credentials: 'include',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const errData = await response.json().catch(() => ({}));
    if (response.status === 429) {
      throw new Error('Rate limit exceeded. Please wait or upgrade to Pro.');
    }
    throw new Error(errData.error || `Server error (${response.status})`);
  }

  const data = await response.json();
  if (data.error) {
    throw new Error(data.error);
  }

  // Update local user data
  if (data.xp_earned) {
    userData.xp = (userData.xp || 0) + data.xp_earned;
    userData.scansToday = (userData.scansToday || 0) + 1;
    await chrome.storage.local.set({ user: userData });
    updateStats();
    updateRateLimit();
  }

  // Save to history
  await saveToHistory(data);

  // Update badge
  chrome.runtime.sendMessage({
    type: 'UPDATE_BADGE',
    count: userData.scansToday,
  });

  return data;
}

// ---- Display Result ----

function displayResult(result) {
  currentResult = result;

  // Domain & skill tags
  const mapping = result.sat_mapping || {};
  els.resultDomain.textContent = mapping.domain || 'Math';
  els.resultSkill.textContent = mapping.skill || '';

  // Difficulty
  const diff = (result.difficulty || 'medium').toLowerCase();
  els.resultDifficulty.textContent = capitalize(diff);
  els.resultDifficulty.className = `tag tag-difficulty ${diff}`;

  // Problem text
  els.resultProblem.innerHTML = renderMath(result.problem || 'Problem not recognized');

  // Answer
  els.resultAnswer.innerHTML = renderMath(result.solution?.answer || '');

  // Steps
  const steps = result.solution?.steps || [];
  els.resultSteps.innerHTML = steps
    .map(
      (step, i) => `
    <div class="step-item">
      <div class="step-number">${i + 1}</div>
      <div class="step-text">${renderMath(step)}</div>
    </div>
  `
    )
    .join('');

  // Strategy tip
  els.resultTip.textContent = result.strategy_tip || '';

  // XP
  if (result.xp_earned) {
    els.xpEarned.textContent = result.xp_earned;
    els.xpBanner.style.display = 'flex';
  } else {
    els.xpBanner.style.display = 'none';
  }

  // Reset notebook button
  els.btnSaveNotebook.innerHTML = 'Save to Notebook';
  els.btnSaveNotebook.disabled = false;

  showState(State.RESULT);
}

// ---- Processing Animation ----

function animateProcessingSteps() {
  const steps = [els.procStep1, els.procStep2, els.procStep3];
  steps.forEach((s) => {
    s.className = 'proc-step';
  });
  steps[0].classList.add('active');

  setTimeout(() => {
    steps[0].classList.replace('active', 'done');
    steps[1].classList.add('active');
  }, 1200);

  setTimeout(() => {
    steps[1].classList.replace('active', 'done');
    steps[2].classList.add('active');
  }, 2400);
}

// ---- History ----

async function loadRecentScans() {
  const data = await chrome.storage.local.get('scanHistory');
  const history = data.scanHistory || [];

  if (history.length === 0) {
    els.emptyRecent.style.display = '';
    return;
  }

  els.emptyRecent.style.display = 'none';

  // Show last 5
  const recent = history.slice(0, 5);
  els.recentList.innerHTML = recent
    .map(
      (item, i) => `
    <div class="recent-item" data-index="${i}">
      <div class="recent-item-icon">${getDomainEmoji(item.sat_mapping?.domain)}</div>
      <div class="recent-item-body">
        <div class="recent-item-title">${escapeHtml(truncate(item.problem || 'Problem', 50))}</div>
        <div class="recent-item-meta">
          <span>${item.sat_mapping?.domain || 'Math'}</span>
          <span>${capitalize(item.difficulty || 'medium')}</span>
          <span>${formatTimeAgo(item.timestamp)}</span>
        </div>
      </div>
      <div class="recent-item-arrow">&#8250;</div>
    </div>
  `
    )
    .join('');

  // Click handlers
  els.recentList.querySelectorAll('.recent-item').forEach((el) => {
    el.addEventListener('click', () => {
      const idx = parseInt(el.dataset.index, 10);
      const item = recent[idx];
      if (item) displayResult(item);
    });
  });
}

async function saveToHistory(result) {
  const data = await chrome.storage.local.get('scanHistory');
  const history = data.scanHistory || [];
  history.unshift({
    ...result,
    timestamp: Date.now(),
  });
  // Keep last 20
  await chrome.storage.local.set({
    scanHistory: history.slice(0, 20),
  });
}

function handleClearHistory() {
  chrome.storage.local.set({ scanHistory: [] });
  els.recentList.innerHTML = '';
  els.emptyRecent.style.display = '';
  els.recentList.appendChild(els.emptyRecent);
  showToast('History cleared');
}

// ---- Practice Similar ----

function handlePracticeSimilar() {
  if (!currentResult?.sat_mapping) return;
  const { domain_key, skill_key } = currentResult.sat_mapping;
  const url = `${DASHBOARD_URL}/practice/${domain_key || 'algebra'}/${skill_key || ''}`;
  chrome.tabs.create({ url });
}

// ---- Save to Notebook ----

async function handleSaveNotebook() {
  if (!currentResult) return;

  els.btnSaveNotebook.disabled = true;
  els.btnSaveNotebook.innerHTML = 'Saving...';

  try {
    const session = (await chrome.storage.local.get('session')).session;
    const response = await fetch(`${API_BASE}/api/notebook/save.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${session}`,
      },
      credentials: 'include',
      body: JSON.stringify({
        problem: currentResult.problem,
        solution: currentResult.solution,
        sat_mapping: currentResult.sat_mapping,
        difficulty: currentResult.difficulty,
        source: 'extension',
      }),
    });

    if (response.ok) {
      els.btnSaveNotebook.innerHTML = '<span class="saved-check">&#10003; Saved</span>';
      showToast('Saved to your Smart Notebook!');
    } else {
      throw new Error('Save failed');
    }
  } catch (err) {
    els.btnSaveNotebook.innerHTML = 'Save to Notebook';
    els.btnSaveNotebook.disabled = false;
    showToast('Could not save. Please try again.');
  }
}

// ---- Error Display ----

function showError(title, message) {
  els.errorTitle.textContent = title;
  els.errorMessage.textContent = message;
  showState(State.ERROR);
}

// ---- Toast Notifications ----

function showToast(message) {
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.getElementById('app').appendChild(toast);

  setTimeout(() => toast.remove(), 3000);
}

// ---- Listen for messages from background/content ----

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.type === 'CROP_RESULT') {
    // Received cropped image from content script via background
    handleCroppedImage(msg.imageData);
    sendResponse({ ok: true });
  }
  if (msg.type === 'AUTH_UPDATED') {
    userData = msg.user;
    onAuthenticated();
    sendResponse({ ok: true });
  }
  if (msg.type === 'CONTEXT_MENU_RESULT') {
    displayResult(msg.result);
    sendResponse({ ok: true });
  }
  return true;
});

async function handleCroppedImage(base64Image) {
  showState(State.PROCESSING);
  animateProcessingSteps();

  try {
    const result = await sendToAPI({
      image: base64Image,
      source: 'extension',
    });
    displayResult(result);
  } catch (err) {
    console.error('API error:', err);
    showError('Analysis Failed', err.message || 'Could not analyze the image. Please try again.');
  }
}

// ---- Utility Functions ----

function renderMath(text) {
  if (!text) return '';
  // Basic LaTeX-to-HTML rendering for common patterns
  let html = escapeHtml(text);

  // Inline math: $...$ or \(...\)
  html = html.replace(/\$([^$]+)\$/g, '<span class="math-inline">$1</span>');
  html = html.replace(/\\\(([^)]+)\\\)/g, '<span class="math-inline">$1</span>');

  // Superscript: x^2 or x^{n+1}
  html = html.replace(/\^{([^}]+)}/g, '<sup>$1</sup>');
  html = html.replace(/\^(\w)/g, '<sup>$1</sup>');

  // Subscript: x_1 or x_{n}
  html = html.replace(/_{([^}]+)}/g, '<sub>$1</sub>');
  html = html.replace(/_(\w)/g, '<sub>$1</sub>');

  // Fractions: \frac{a}{b}
  html = html.replace(
    /\\frac{([^}]+)}{([^}]+)}/g,
    '<span class="math-inline">($1)/($2)</span>'
  );

  // Square root: \sqrt{x}
  html = html.replace(/\\sqrt{([^}]+)}/g, '<span class="math-inline">&radic;($1)</span>');

  // Common symbols
  html = html.replace(/\\pi/g, '&pi;');
  html = html.replace(/\\times/g, '&times;');
  html = html.replace(/\\div/g, '&divide;');
  html = html.replace(/\\pm/g, '&plusmn;');
  html = html.replace(/\\leq/g, '&le;');
  html = html.replace(/\\geq/g, '&ge;');
  html = html.replace(/\\neq/g, '&ne;');
  html = html.replace(/\\infty/g, '&infin;');

  return html;
}

function escapeHtml(text) {
  const el = document.createElement('div');
  el.textContent = text;
  return el.innerHTML;
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function truncate(str, len) {
  return str.length > len ? str.slice(0, len) + '...' : str;
}

function formatNumber(num) {
  if (num >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  return String(num);
}

function formatTimeAgo(timestamp) {
  if (!timestamp) return '';
  const diff = Date.now() - timestamp;
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

function getDomainEmoji(domain) {
  const map = {
    Algebra: '&#120016;',
    'Advanced Math': '&#120017;',
    'Problem-Solving and Data Analysis': '&#128202;',
    Geometry: '&#9651;',
    'Geometry and Trigonometry': '&#9651;',
  };
  return map[domain] || '&#128290;';
}
