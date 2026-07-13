/**
 * socratic-hints.js
 * Drop this into your chat page. It handles the hint button strip and
 * wires up the progressive disclosure calls to socratic_chat.php.
 *
 * Dependencies: none (vanilla JS). Assumes you already have:
 *   - #chat-form        <form> wrapping the chat input
 *   - #chat-input       <textarea> or <input> for the student's message
 *   - #chat-messages    container where bubbles are appended
 *   - #session-id       hidden <input> holding the current session_id
 *   - window.CHAT_URL   path to socratic_chat.php (set before this script)
 *
 * Usage:
 *   <script>window.CHAT_URL = 'ajax/socratic_chat.php';</script>
 *   <script src="socratic-hints.js"></script>
 */

(function () {
  'use strict';

  // ── Config ────────────────────────────────────────────────────────────────

  const CHAT_URL = window.CHAT_URL || 'ajax/socratic_chat.php';

  const HINT_LABELS = {
    1: { icon: '💬', text: 'Ask again',      title: 'Another guiding question' },
    2: { icon: '💡', text: 'Get a hint',     title: 'Nudge + analogy'          },
    3: { icon: '📖', text: 'Show example',   title: 'Worked example (similar problem)' }
  };

  // Colours match your existing chat bubble styles — adjust as needed
  const HINT_BADGE_STYLES = {
    2: 'background:#fef9c3;border:1px solid #fde68a;color:#92400e;',   // amber tint
    3: 'background:#ede9fe;border:1px solid #c4b5fd;color:#4c1d95;'    // purple tint
  };

  // ── State ─────────────────────────────────────────────────────────────────

  // Tracks the last message sent so hint requests can reference it
  let lastStudentMessage = '';
  let hintStripEl        = null;   // the currently shown button strip

  // ── DOM helpers ───────────────────────────────────────────────────────────

  function getSessionId() {
    const el = document.getElementById('session-id');
    return el ? el.value : '';
  }

  function setSessionId(id) {
    const el = document.getElementById('session-id');
    if (el) el.value = id;
  }

  function appendBubble(html, extraClass = '') {
    const messages = document.getElementById('chat-messages');
    if (!messages) return;
    const div = document.createElement('div');
    div.className = 'chat-bubble ai-bubble ' + extraClass;
    div.innerHTML = html;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  // ── Hint strip ────────────────────────────────────────────────────────────

  /**
   * Render the hint button strip below the last AI response.
   * Only shows buttons for levels ABOVE the one just used.
   *
   * @param {number} currentLevel  The level that was just used (1, 2, or 3)
   */
  function renderHintStrip(currentLevel) {
    removeHintStrip();   // clear any previous strip

    if (currentLevel >= 3) return;   // already at max — nothing more to offer

    const strip = document.createElement('div');
    strip.id = 'hint-strip';
    strip.style.cssText = [
      'display:flex',
      'gap:8px',
      'align-items:center',
      'margin:6px 0 10px 0',
      'flex-wrap:wrap'
    ].join(';');

    const label = document.createElement('span');
    label.style.cssText = 'font-size:12px;color:#6b7280;';
    label.textContent   = 'Still stuck?';
    strip.appendChild(label);

    // Show buttons for every level above current
    for (let lvl = currentLevel + 1; lvl <= 3; lvl++) {
      const btn = document.createElement('button');
      btn.type        = 'button';
      btn.dataset.lvl = lvl;
      btn.title       = HINT_LABELS[lvl].title;
      btn.style.cssText = [
        'display:inline-flex',
        'align-items:center',
        'gap:4px',
        'padding:4px 12px',
        'border-radius:20px',
        'border:1px solid #d1d5db',
        'background:#ffffff',
        'font-size:13px',
        'cursor:pointer',
        'transition:background 0.15s'
      ].join(';');
      btn.innerHTML = HINT_LABELS[lvl].icon + ' ' + HINT_LABELS[lvl].text;

      btn.addEventListener('mouseenter', () => { btn.style.background = '#f3f4f6'; });
      btn.addEventListener('mouseleave', () => { btn.style.background = '#ffffff'; });
      btn.addEventListener('click',      () => requestHint(lvl));

      strip.appendChild(btn);
    }

    const messages = document.getElementById('chat-messages');
    if (messages) {
      messages.appendChild(strip);
      messages.scrollTop = messages.scrollHeight;
    }

    hintStripEl = strip;
  }

  function removeHintStrip() {
    if (hintStripEl && hintStripEl.parentNode) {
      hintStripEl.parentNode.removeChild(hintStripEl);
    }
    hintStripEl = null;
  }

  // ── API call ──────────────────────────────────────────────────────────────

  /**
   * Send a hint request to socratic_chat.php for the given level.
   */
  async function requestHint(level) {
    removeHintStrip();

    // Show loading placeholder
    const placeholder = appendBubble(
      '<em style="color:#9ca3af">Getting ' + HINT_LABELS[level].text.toLowerCase() + '…</em>',
      'hint-loading'
    );

    const formData = new FormData();
    formData.append('message',    lastStudentMessage);
    formData.append('session_id', getSessionId());
    formData.append('hint_level', level);

    try {
      const res  = await fetch(CHAT_URL, { method: 'POST', body: formData });
      const data = await res.json();

      // Replace placeholder with real reply
      if (placeholder && placeholder.parentNode) {
        const badge = level > 1
          ? '<span style="' + HINT_BADGE_STYLES[level] + 'font-size:11px;padding:2px 8px;border-radius:12px;font-weight:500;margin-right:6px;">'
            + HINT_LABELS[level].icon + ' ' + HINT_LABELS[level].title
            + '</span>'
          : '';

        // Preserve newlines (e.g. code blocks in level 3)
        const safeText = (data.reply || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
        const formatted = '<pre style="white-space:pre-wrap;font-family:inherit;margin:0;">' + safeText + '</pre>';

        placeholder.innerHTML = badge + formatted;
        placeholder.classList.remove('hint-loading');
      }

      if (data.session_id) setSessionId(data.session_id);

      // Show next level strip (if any)
      renderHintStrip(level);

    } catch (err) {
      if (placeholder) placeholder.innerHTML = '<em style="color:#ef4444">Could not load hint. Please try again.</em>';
      renderHintStrip(level - 1);  // restore previous strip so they can retry
    }
  }

  // ── Hook into existing chat form ──────────────────────────────────────────

  /**
   * Wrap the existing submit handler so we can:
   *  1. Capture lastStudentMessage
   *  2. Show the hint strip after the AI responds
   *
   * Call this AFTER your own form submit listener is attached,
   * OR replace your fetch call with sendMessage() below.
   */
  async function sendMessage(message) {
    lastStudentMessage = message;
    removeHintStrip();

    const formData = new FormData();
    formData.append('message',    message);
    formData.append('session_id', getSessionId());
    formData.append('hint_level', 1);

    const placeholder = appendBubble(
      '<em style="color:#9ca3af">Thinking…</em>',
      'ai-loading'
    );

    try {
      const res  = await fetch(CHAT_URL, { method: 'POST', body: formData });
      const data = await res.json();

      if (placeholder && placeholder.parentNode) {
        const safeText = (data.reply || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        placeholder.innerHTML = safeText;
        placeholder.classList.remove('ai-loading');
      }

      if (data.session_id) setSessionId(data.session_id);

      // Show hint strip starting from level 1 (so buttons 2 & 3 appear)
      renderHintStrip(1);

    } catch (err) {
      if (placeholder) placeholder.innerHTML = '<em style="color:#ef4444">Error. Please try again.</em>';
    }
  }

  // ── Auto-hook if form exists ──────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    const form  = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    if (!form || !input) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const msg = input.value.trim();
      if (!msg) return;

      // Append student bubble
      appendBubble(
        '<span>' + msg.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>',
        'student-bubble'
      );

      input.value = '';
      sendMessage(msg);
    });
  });

  // ── Public API ────────────────────────────────────────────────────────────
  // Expose sendMessage so you can call it from other scripts if needed
  window.SocraticChat = { sendMessage, requestHint };

})();
