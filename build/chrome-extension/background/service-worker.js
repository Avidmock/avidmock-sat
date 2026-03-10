/* ============================================================
   Avidmock SAT Math Scanner — Background Service Worker
   ============================================================ */

const API_BASE = 'https://my.sat.avidmock.com';
const API_VISION = `${API_BASE}/api/math-vision.php`;
const API_AUTH_CHECK = `${API_BASE}/api/auth/check.php`;

// ---- Context Menu Setup ----

chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({
    id: 'avidmock-solve-image',
    title: 'Solve with Avidmock',
    contexts: ['image'],
  });

  chrome.contextMenus.create({
    id: 'avidmock-solve-selection',
    title: 'Solve with Avidmock',
    contexts: ['selection'],
  });

  // Initialize badge
  updateBadge(0);
});

// ---- Context Menu Handlers ----

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
  if (info.menuItemId === 'avidmock-solve-image') {
    await handleImageContextMenu(info.srcUrl, tab);
  }
  if (info.menuItemId === 'avidmock-solve-selection') {
    await handleSelectionContextMenu(info.selectionText, tab);
  }
});

async function handleImageContextMenu(imageUrl, tab) {
  try {
    // Fetch the image and convert to base64
    const response = await fetch(imageUrl);
    const blob = await response.blob();
    const base64 = await blobToBase64(blob);

    // Show processing notification
    showNotification('Analyzing...', 'Solving the math problem with AI.');

    // Send to API
    const result = await callVisionAPI({ image: base64, source: 'extension' });

    // Send result to content script for floating card
    chrome.tabs.sendMessage(tab.id, {
      type: 'SHOW_RESULT_CARD',
      result,
    });

    // Also notify popup if open
    chrome.runtime.sendMessage({
      type: 'CONTEXT_MENU_RESULT',
      result,
    }).catch(() => {
      // Popup might not be open
    });

    showNotification(
      'Problem Solved!',
      `Answer: ${result.solution?.answer || 'See details'} | +${result.xp_earned || 10} XP`
    );
  } catch (err) {
    console.error('Context menu image error:', err);
    showNotification('Error', 'Could not analyze the image. Please try again.');
  }
}

async function handleSelectionContextMenu(text, tab) {
  if (!text || !text.trim()) return;

  try {
    showNotification('Analyzing...', 'Solving the math problem with AI.');

    const result = await callVisionAPI({ text: text.trim(), source: 'extension' });

    chrome.tabs.sendMessage(tab.id, {
      type: 'SHOW_RESULT_CARD',
      result,
    });

    chrome.runtime.sendMessage({
      type: 'CONTEXT_MENU_RESULT',
      result,
    }).catch(() => {});

    showNotification(
      'Problem Solved!',
      `Answer: ${result.solution?.answer || 'See details'} | +${result.xp_earned || 10} XP`
    );
  } catch (err) {
    console.error('Context menu selection error:', err);
    showNotification('Error', 'Could not analyze the text. Please try again.');
  }
}

// ---- Message Handlers ----

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.type === 'CHECK_AUTH') {
    checkAuthentication().then(sendResponse).catch(() => {
      sendResponse({ authenticated: false });
    });
    return true; // async response
  }

  if (msg.type === 'UPDATE_BADGE') {
    updateBadge(msg.count || 0);
    sendResponse({ ok: true });
  }

  if (msg.type === 'CROP_COMPLETE') {
    // Content script finished cropping, send to API
    handleCropComplete(msg.imageData, sender.tab).then(sendResponse).catch((err) => {
      sendResponse({ error: err.message });
    });
    return true;
  }

  if (msg.type === 'SOLVE_IMAGE') {
    callVisionAPI({ image: msg.image, source: 'extension' })
      .then((result) => sendResponse({ result }))
      .catch((err) => sendResponse({ error: err.message }));
    return true;
  }

  return false;
});

async function handleCropComplete(imageData, tab) {
  try {
    const result = await callVisionAPI({ image: imageData, source: 'extension' });

    // Show floating result card on the page
    chrome.tabs.sendMessage(tab.id, {
      type: 'SHOW_RESULT_CARD',
      result,
    });

    // Notify popup if open
    chrome.runtime.sendMessage({
      type: 'CROP_RESULT',
      imageData,
      result,
    }).catch(() => {});

    showNotification(
      'Problem Solved!',
      `Answer: ${result.solution?.answer || 'See details'} | +${result.xp_earned || 10} XP`
    );

    return { result };
  } catch (err) {
    showNotification('Error', err.message || 'Analysis failed.');
    return { error: err.message };
  }
}

// ---- API Calls ----

async function callVisionAPI(payload) {
  const { session } = await chrome.storage.local.get('session');

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
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const errData = await response.json().catch(() => ({}));
    throw new Error(errData.error || `Server error (${response.status})`);
  }

  const data = await response.json();
  if (data.error) throw new Error(data.error);

  // Update local stats
  const { user } = await chrome.storage.local.get('user');
  if (user) {
    user.xp = (user.xp || 0) + (data.xp_earned || 0);
    user.scansToday = (user.scansToday || 0) + 1;
    await chrome.storage.local.set({ user });
    updateBadge(user.scansToday);
  }

  // Save to history
  const { scanHistory } = await chrome.storage.local.get('scanHistory');
  const history = scanHistory || [];
  history.unshift({ ...data, timestamp: Date.now() });
  await chrome.storage.local.set({ scanHistory: history.slice(0, 20) });

  return data;
}

async function checkAuthentication() {
  try {
    // Try to check auth via the Avidmock API
    const { session } = await chrome.storage.local.get('session');

    if (!session) {
      // Try cookie-based auth
      const cookie = await chrome.cookies?.get({
        url: API_BASE,
        name: 'avidmock_session',
      }).catch(() => null);

      if (!cookie) {
        return { authenticated: false };
      }
    }

    const headers = { Accept: 'application/json' };
    if (session) {
      headers['Authorization'] = `Bearer ${session}`;
    }

    const response = await fetch(API_AUTH_CHECK, {
      method: 'GET',
      headers,
      credentials: 'include',
    });

    if (!response.ok) {
      return { authenticated: false };
    }

    const data = await response.json();
    if (data.authenticated && data.user) {
      // Cache locally
      await chrome.storage.local.set({
        user: data.user,
        session: data.session || session,
      });
      return { authenticated: true, user: data.user };
    }

    return { authenticated: false };
  } catch (err) {
    console.error('Auth check failed:', err);
    return { authenticated: false };
  }
}

// ---- Badge ----

function updateBadge(count) {
  const text = count > 0 ? String(count) : '';
  chrome.action.setBadgeText({ text });
  chrome.action.setBadgeBackgroundColor({ color: '#1fe290' });
  chrome.action.setBadgeTextColor({ color: '#143230' });
}

// ---- Notifications ----

function showNotification(title, message) {
  chrome.notifications.create({
    type: 'basic',
    iconUrl: 'icons/icon-128.png',
    title: `Avidmock: ${title}`,
    message,
  });
}

// ---- Utility ----

function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => {
      // Remove data URL prefix to get pure base64
      const base64 = reader.result.split(',')[1];
      resolve(base64);
    };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

// ---- Keep Auth Synced ----
// Check auth status periodically when extension is active
chrome.alarms?.create('auth-sync', { periodInMinutes: 15 });

chrome.alarms?.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'auth-sync') {
    const result = await checkAuthentication();
    if (result.authenticated) {
      chrome.runtime.sendMessage({
        type: 'AUTH_UPDATED',
        user: result.user,
      }).catch(() => {});
    }
  }
});
