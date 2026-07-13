<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'student' && $_SESSION['user_type'] != 'lecturer')) {
    header("Location: index.php?error=login_required");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grammar Checker - SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        .header {
            background: #003366;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 24px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #003366;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            text-decoration: underline;
            transform: translateX(-4px);
        }
        
        /* Warning Banner */
        .warning-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }
        
        .warning-banner.show {
            display: flex;
        }
        
        .warning-banner .warning-icon {
            color: #d97706;
        }
        
        .warning-banner .warning-text {
            color: #92400e;
            flex: 1;
        }
        
        .warning-banner .warning-close {
            cursor: pointer;
            color: #92400e;
            font-size: 18px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,51,102,0.1);
        }
        
        .stat-icon {
            width: 44px;
            height: 44px;
            background: #e8f0fe;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon span {
            font-size: 24px;
            color: #003366;
        }
        
        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
            font-family: 'Inter', sans-serif;
        }
        
        .stat-info p {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Limit indicator */
        .limit-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            padding: 6px 12px;
            background: #f1f5f9;
            border-radius: 20px;
            margin-top: 8px;
        }
        
        .limit-bar {
            width: 100px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .limit-fill {
            height: 100%;
            background: #28a745;
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        
        .limit-fill.warning {
            background: #d97706;
        }
        
        .limit-fill.danger {
            background: #dc2626;
        }
        
        /* Two Column Layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 0;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 900px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            .suggestions-panel {
                border-left: none;
                border-top: 1px solid #e2e8f0;
            }
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Editor Section with Highlight Overlay */
        .editor-section {
            position: relative;
            background: white;
        }
        
        .editor-header {
            background: #f8fafc;
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f1f5f9;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
        }
        
        .status-badge.analyzing {
            background: #e0e7ff;
            color: #1e40af;
        }
        
        .status-badge.analyzing .pulse {
            width: 8px;
            height: 8px;
            background: #1e40af;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-icon:hover {
            background: #f8fafc;
            border-color: #003366;
            color: #003366;
        }
        
        .btn-icon.primary {
            background: #003366;
            border-color: #003366;
            color: white;
        }
        
        .btn-icon.primary:hover {
            background: #1a4d8c;
        }
        
        /* Highlight Container - overlay for underlined text */
        .highlight-container {
            position: relative;
            width: 100%;
        }
        
        .grammar-textarea {
            width: 100%;
            min-height: 380px;
            padding: 24px;
            font-size: 15px;
            line-height: 1.7;
            font-family: 'Inter', monospace;
            border: none;
            resize: vertical;
            outline: none;
            color: #1e293b;
            background: transparent;
            position: relative;
            z-index: 2;
            caret-color: #003366;
        }
        
        /* Highlight overlay */
        .highlight-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            padding: 24px;
            font-size: 15px;
            line-height: 1.7;
            font-family: 'Inter', monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: transparent;
            overflow: hidden;
        }
        
        .highlight-overlay mark {
            background-color: transparent;
            border-bottom: 2px solid #dc2626;
            text-decoration: none;
            color: transparent;
            pointer-events: none;
        }
        
        .char-counter {
            font-size: 11px;
            color: #94a3b8;
            padding: 10px 24px;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Suggestions Panel */
        .suggestions-panel {
            background: #f8fafc;
            border-left: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        
        .suggestions-header {
            padding: 16px 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .suggestions-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
        }
        
        .suggestions-list {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            max-height: 450px;
        }
        
        .suggestion-item {
            background: white;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            border-left: 4px solid #dc2626;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .suggestion-item:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        
        .suggestion-message {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .suggestion-badge {
            background: #fee2e2;
            color: #dc2626;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        .suggestion-context {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 10px;
            font-family: monospace;
        }
        
        .suggestion-replacements {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        
        .replacement-badge {
            background: #e0e7ff;
            color: #1e40af;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .replacement-badge:hover {
            background: #c7d2fe;
            transform: scale(1.02);
        }
        
        .loading-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #003366; border-radius: 10px; }
    </style>
</head>
<body>

<div class="header">
    <h1>📝 Grammar Checker</h1>
</div>

<main class="main-container">
    
    <a href="student_dashboard_2.php" class="back-link">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        Back to Dashboard
    </a>
    
    <!-- Warning Banner -->
    <div id="warningBanner" class="warning-banner">
        <span class="material-symbols-outlined warning-icon">warning</span>
        <span class="warning-text" id="warningText">⚠️ You have exceeded the 2000 character limit. Please reduce your text for grammar checking.</span>
        <span class="material-symbols-outlined warning-close" onclick="closeWarning()">close</span>
    </div>
    
    <div class="stats-row" id="statsRow" style="display: none;">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">abc</span>
            </div>
            <div class="stat-info">
                <h3 id="charCount">0</h3>
                <p>Characters</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">counter_1</span>
            </div>
            <div class="stat-info">
                <h3 id="wordCount">0</h3>
                <p>Words</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">error</span>
            </div>
            <div class="stat-info">
                <h3 id="errorCount">0</h3>
                <p>Issues</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">verified</span>
            </div>
            <div class="stat-info">
                <h3 id="score">100</h3>
                <p>Score</p>
            </div>
        </div>
    </div>
    
    <div class="two-column">
        <!-- Left: Editor with Highlighting -->
        <div class="editor-section">
            <div class="editor-header">
                <div class="status-badge" id="statusBadge">
                    <span class="material-symbols-outlined text-[16px]">edit_note</span>
                    <span>Ready</span>
                </div>
                <div class="action-buttons">
                    <button class="btn-icon" id="clearBtn" onclick="clearText()">
                        <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                        Clear
                    </button>
                    <button class="btn-icon primary" id="copyBtn" onclick="copyText()">
                        <span class="material-symbols-outlined text-[16px]">content_copy</span>
                        Copy
                    </button>
                </div>
            </div>
            <div class="highlight-container" id="highlightContainer">
                <div class="highlight-overlay" id="highlightOverlay"></div>
                <textarea id="essayText" class="grammar-textarea" placeholder="✍️ Start writing or paste your essay here... (Maximum 2000 characters)&#10;&#10;Example:&#10;The quick brown fox jump over the lazy dog. This is a grate day for learning. I have many idea to share." maxlength="2000"></textarea>
            </div>
            <div class="char-counter">
                <div>
                    <span id="currentChars">0</span> / <span id="maxChars">2000</span> characters
                </div>
                <div class="limit-indicator">
                    <span>Limit:</span>
                    <div class="limit-bar">
                        <div id="limitFill" class="limit-fill" style="width: 0%"></div>
                    </div>
                    <span id="limitPercent">0</span>%
                </div>
            </div>
        </div>
        
        <!-- Right: Suggestions -->
        <div class="suggestions-panel">
            <div class="suggestions-header">
                <span class="material-symbols-outlined text-blue-700">lightbulb</span>
                <h3>Grammar Suggestions</h3>
            </div>
            <div class="suggestions-list" id="suggestionsList">
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2">edit_document</span>
                    <p>Start typing to get grammar suggestions</p>
                    <p class="text-xs mt-2">Maximum 2000 characters per check</p>
                </div>
            </div>
        </div>
    </div>
    
</main>

<script>
    // ==========================================
    // CHARACTER LIMIT CONSTANTS
    // ==========================================
    const MAX_CHARS = 2000;
    const WARNING_THRESHOLD = 1800; // Show warning at 1800 characters
    
    let debounceTimer;
    let currentMatches = [];
    let currentText = '';
    let isOverLimit = false;
    
    const textarea = document.getElementById('essayText');
    const highlightOverlay = document.getElementById('highlightOverlay');
    const suggestionsList = document.getElementById('suggestionsList');
    const charCounter = document.getElementById('currentChars');
    const charCountSpan = document.getElementById('charCount');
    const wordCountSpan = document.getElementById('wordCount');
    const errorCountSpan = document.getElementById('errorCount');
    const scoreSpan = document.getElementById('score');
    const statsRow = document.getElementById('statsRow');
    const statusBadge = document.getElementById('statusBadge');
    const warningBanner = document.getElementById('warningBanner');
    const warningText = document.getElementById('warningText');
    const limitFill = document.getElementById('limitFill');
    const limitPercent = document.getElementById('limitPercent');
    const maxCharsSpan = document.getElementById('maxChars');
    
    // Set max chars display
    maxCharsSpan.textContent = MAX_CHARS;
    textarea.maxLength = MAX_CHARS;
    
    // Update character limit UI
    function updateLimitUI(charCount) {
        const percent = (charCount / MAX_CHARS) * 100;
        limitFill.style.width = percent + '%';
        limitPercent.textContent = Math.round(percent);
        
        // Change color based on percentage
        if (percent >= 90) {
            limitFill.classList.add('danger');
            limitFill.classList.remove('warning');
        } else if (percent >= 75) {
            limitFill.classList.add('warning');
            limitFill.classList.remove('danger');
        } else {
            limitFill.classList.remove('warning', 'danger');
        }
        
        // Show warning banner if approaching limit
        if (charCount >= WARNING_THRESHOLD && charCount < MAX_CHARS) {
            showWarning(`⚠️ Approaching limit! ${MAX_CHARS - charCount} characters remaining.`);
        } else if (charCount >= MAX_CHARS) {
            showWarning(`❌ Character limit reached! Maximum ${MAX_CHARS} characters allowed. Please reduce your text.`);
            isOverLimit = true;
        } else {
            if (!isOverLimit) {
                closeWarning();
            }
            isOverLimit = false;
        }
    }
    
    function showWarning(message) {
        warningText.textContent = message;
        warningBanner.classList.add('show');
    }
    
    function closeWarning() {
        warningBanner.classList.remove('show');
    }
    
    // Update stats with limit checking
    function updateStats(text) {
        currentText = text;
        const charCount = text.length;
        const wordCount = text.trim().length === 0 ? 0 : text.trim().split(/\s+/).length;
        
        charCounter.textContent = charCount;
        if (charCountSpan) charCountSpan.textContent = charCount;
        if (wordCountSpan) wordCountSpan.textContent = wordCount;
        
        // Update limit UI
        updateLimitUI(charCount);
        
        if (charCount > 0) {
            statsRow.style.display = 'grid';
        } else {
            statsRow.style.display = 'none';
        }
        
        return charCount;
    }
    
    function calculateScore(text, errorCount) {
        if (text.length === 0) return 100;
        const deduction = Math.min(errorCount * 3, 60);
        return Math.max(100 - deduction, 0);
    }
    
    function setStatus(isAnalyzing = false) {
        if (isAnalyzing) {
            statusBadge.className = 'status-badge analyzing';
            statusBadge.innerHTML = '<span class="pulse"></span><span>Analyzing...</span>';
        } else {
            statusBadge.className = 'status-badge';
            statusBadge.innerHTML = '<span class="material-symbols-outlined text-[16px]">check_circle</span><span>Ready</span>';
        }
    }
    
    // Update highlight overlay with underlined text
    function updateHighlights(matches, fullText) {
        if (!matches || matches.length === 0 || !fullText) {
            highlightOverlay.innerHTML = '';
            return;
        }
        
        const sortedMatches = [...matches].sort((a, b) => a.offset - b.offset);
        
        let html = '';
        let lastIndex = 0;
        
        for (const match of sortedMatches) {
            const offset = match.offset;
            const length = match.length;
            // Ensure offset and length are within bounds
            if (offset + length > fullText.length) continue;
            
            const beforeText = escapeHtml(fullText.substring(lastIndex, offset));
            const errorText = escapeHtml(fullText.substring(offset, offset + length));
            
            html += beforeText;
            html += `<mark class="grammar-error" data-offset="${offset}" data-length="${length}" style="border-bottom: 2px solid #dc2626; cursor: pointer;" title="${escapeHtml(match.message)}">${errorText}</mark>`;
            lastIndex = offset + length;
        }
        
        html += escapeHtml(fullText.substring(lastIndex));
        
        highlightOverlay.innerHTML = html;
        
        highlightOverlay.scrollTop = textarea.scrollTop;
        highlightOverlay.scrollLeft = textarea.scrollLeft;
    }
    
    textarea.addEventListener('scroll', function() {
        highlightOverlay.scrollTop = textarea.scrollTop;
        highlightOverlay.scrollLeft = textarea.scrollLeft;
    });
    
    function copyText() {
        const text = textarea.value;
        if (!text) {
            alert('Nothing to copy.');
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            const copyBtn = document.getElementById('copyBtn');
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span> Copied!';
            setTimeout(() => {
                copyBtn.innerHTML = originalText;
            }, 2000);
        }).catch(() => {
            alert('Failed to copy.');
        });
    }
    
    function clearText() {
        if (textarea.value && confirm('Clear all text?')) {
            textarea.value = '';
            updateStats('');
            highlightOverlay.innerHTML = '';
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2">edit_document</span>
                    <p>Start typing to get grammar suggestions</p>
                    <p class="text-xs mt-2">Maximum 2000 characters per check</p>
                </div>
            `;
            if (errorCountSpan) errorCountSpan.textContent = '0';
            if (scoreSpan) scoreSpan.textContent = '100';
            setStatus(false);
            closeWarning();
        }
    }
    
    function scrollToError(offset) {
        const textBefore = textarea.value.substring(0, offset);
        const lines = textBefore.split('\n');
        const lineNumber = lines.length - 1;
        const lineHeight = 26;
        const scrollPosition = lineNumber * lineHeight;
        
        textarea.scrollTop = Math.max(0, scrollPosition - 100);
        textarea.focus();
        textarea.setSelectionRange(offset, offset + 1);
        
        const errorMark = document.querySelector(`mark[data-offset="${offset}"]`);
        if (errorMark) {
            errorMark.style.backgroundColor = '#fecaca';
            errorMark.style.transition = 'background-color 0.3s';
            setTimeout(() => {
                errorMark.style.backgroundColor = 'transparent';
            }, 1000);
        }
    }
    
    async function checkGrammar(text) {
        // Check character limit first
        if (text.length > MAX_CHARS) {
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2" style="color: #dc2626;">error</span>
                    <p style="color: #dc2626; font-weight: 500;">Character limit exceeded!</p>
                    <p class="text-sm mt-2">Maximum ${MAX_CHARS} characters allowed.</p>
                    <p class="text-xs text-gray-500 mt-1">Current: ${text.length} characters</p>
                    <p class="text-xs text-gray-500">Please reduce your text to continue checking.</p>
                </div>
            `;
            highlightOverlay.innerHTML = '';
            if (errorCountSpan) errorCountSpan.textContent = '0';
            if (scoreSpan) scoreSpan.textContent = '0';
            setStatus(false);
            return;
        }
        
        if (text.length < 5) {
            highlightOverlay.innerHTML = '';
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2">keyboard</span>
                    <p>Write at least 5 characters to start checking</p>
                    <p class="text-xs mt-2">Maximum ${MAX_CHARS} characters per check</p>
                </div>
            `;
            if (errorCountSpan) errorCountSpan.textContent = '0';
            if (scoreSpan) scoreSpan.textContent = '100';
            return;
        }
        
        setStatus(true);
        suggestionsList.innerHTML = `
            <div class="loading-state">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="animation: pulse 1.5s infinite;">autorenew</span>
                    <span>Checking grammar...</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Processing ${text.length} characters</p>
            </div>
        `;
        
        try {
            const response = await fetch('api/grammar_check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'text=' + encodeURIComponent(text)
            });
            const data = await response.json();
            
            if (data.error) {
                suggestionsList.innerHTML = `
                    <div class="empty-state">
                        <span class="material-symbols-outlined text-4xl mb-2">error</span>
                        <p>⚠️ ${data.error}</p>
                    </div>
                `;
                setStatus(false);
                return;
            }
            
            currentMatches = data.matches || [];
            displaySuggestions(currentMatches, text);
            updateHighlights(currentMatches, text);
            
            const errorCount = currentMatches.length;
            if (errorCountSpan) errorCountSpan.textContent = errorCount;
            const score = calculateScore(text, errorCount);
            if (scoreSpan) scoreSpan.textContent = score;
            
            setStatus(false);
        } catch (err) {
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2">wifi_off</span>
                    <p>Network error. Please try again.</p>
                </div>
            `;
            setStatus(false);
        }
    }
    
    function displaySuggestions(matches, fullText) {
        if (!matches || matches.length === 0) {
            suggestionsList.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-outlined text-4xl mb-2" style="color: #16a34a;">check_circle</span>
                    <p style="color: #16a34a; font-weight: 500;">No errors found!</p>
                    <p style="font-size: 12px; margin-top: 4px;">Your writing looks great ✨</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        matches.forEach((match, index) => {
            const replacements = match.replacements || [];
            const replacementList = replacements.slice(0, 5).map(r => r.value);
            
            html += `
                <div class="suggestion-item" onclick="scrollToError(${match.offset})">
                    <div class="suggestion-message">
                        <span class="suggestion-badge">${index + 1}</span>
                        <span>${escapeHtml(match.message.replace(/\.$/, ''))}</span>
                    </div>
                    <div class="suggestion-context">
                        “…${escapeHtml(match.context.text)}…”
                    </div>
                    ${replacementList.length > 0 ? `
                        <div class="suggestion-replacements">
                            ${replacementList.map(r => `<span class="replacement-badge" onclick="event.stopPropagation(); applyReplacement('${escapeHtml(r)}', ${match.offset}, ${match.length})">${escapeHtml(r)}</span>`).join('')}
                        </div>
                    ` : '<div class="text-xs text-slate-400 mt-2">📝 Manual correction needed</div>'}
                </div>
            `;
        });
        suggestionsList.innerHTML = html;
    }
    
    function applyReplacement(replacement, offset, length) {
        const currentText = textarea.value;
        const before = currentText.substring(0, offset);
        const after = currentText.substring(offset + length);
        const newText = before + replacement + after;
        
        // Check if replacement would exceed limit
        if (newText.length > MAX_CHARS) {
            alert(`Cannot apply replacement. Would exceed ${MAX_CHARS} character limit.`);
            return;
        }
        
        textarea.value = newText;
        updateStats(newText);
        checkGrammar(newText);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Handle paste event to check character limit
    textarea.addEventListener('paste', function(e) {
        setTimeout(() => {
            const text = this.value;
            if (text.length > MAX_CHARS) {
                showWarning(`❌ Character limit exceeded! Maximum ${MAX_CHARS} characters allowed. Please reduce your text.`);
                isOverLimit = true;
                // Truncate to max chars
                this.value = text.substring(0, MAX_CHARS);
                updateStats(this.value);
                checkGrammar(this.value);
            }
        }, 10);
    });
    
    textarea.addEventListener('input', function() {
        const text = this.value;
        const charCount = updateStats(text);
        
        // Don't check grammar if over limit
        if (charCount > MAX_CHARS) {
            if (!isOverLimit) {
                showWarning(`❌ Character limit exceeded! Maximum ${MAX_CHARS} characters allowed.`);
                isOverLimit = true;
            }
            return;
        }
        
        isOverLimit = false;
        
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            checkGrammar(text);
        }, 800);
    });
    
    // Initial setup
    updateStats('');
    
    // Make functions global
    window.copyText = copyText;
    window.clearText = clearText;
    window.applyReplacement = applyReplacement;
    window.scrollToError = scrollToError;
    window.closeWarning = closeWarning;
</script>

</body>
</html>