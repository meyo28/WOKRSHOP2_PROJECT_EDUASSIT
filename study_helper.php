<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];
$current_session_id = isset($_GET['session']) ? intval($_GET['session']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Helper - AI Tutor | SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; height: 100vh; overflow: hidden; }

        /* Header */
        .header {
            background: #003366;
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 22px; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); }

        /* Main Layout */
        .main-layout { display: flex; height: calc(100vh - 70px); }

        /* Sidebar */
        .sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-header {
            padding: 20px;
            background: #f8f9fc;
            border-bottom: 1px solid #e0e0e0;
        }
        .sidebar-header h3 { font-size: 16px; color: #003366; margin-bottom: 10px; }
        .new-chat-btn {
            width: 100%;
            padding: 12px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .new-chat-btn:hover { background: #1a4d8c; }
        .sessions-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .session-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8f9fc;
            border: 1px solid #e8eef5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .session-item:hover { background: #e8f0fe; }
        .session-item.active {
            background: #003366;
            color: white;
            border-color: #003366;
        }
        .session-item.active .session-title { color: white; }
        .session-item.active .session-date { color: rgba(255,255,255,0.7); }
        .session-info { flex: 1; cursor: pointer; }
        .session-title { font-weight: 500; font-size: 14px; margin-bottom: 5px; color: #333; }
        .session-date { font-size: 11px; color: #888; }
        .session-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .session-item:hover .session-actions { opacity: 1; }
        .rename-btn, .delete-session-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            border-radius: 5px;
            transition: background 0.2s;
        }
        .rename-btn { color: #003366; }
        .rename-btn:hover { background: rgba(0,51,102,0.1); }
        .delete-session-btn { color: #c00; }
        .delete-session-btn:hover { background: rgba(204,0,0,0.1); }
        .session-item.active .rename-btn { color: white; }
        .session-item.active .rename-btn:hover { background: rgba(255,255,255,0.2); }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f5f7fb;
        }
        .chat-header {
            padding: 15px 25px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header h2 { font-size: 18px; color: #003366; }
        .chat-header p { font-size: 12px; color: #666; margin-top: 5px; }

        /* Message Container */
        .chat-box {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Custom Scrollbar */
        .chat-box::-webkit-scrollbar,
        .sessions-list::-webkit-scrollbar {
            width: 6px;
        }
        .chat-box::-webkit-scrollbar-track,
        .sessions-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .chat-box::-webkit-scrollbar-thumb,
        .sessions-list::-webkit-scrollbar-thumb {
            background: #003366;
            border-radius: 3px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            animation: fadeIn 0.3s ease;
            position: relative;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.student { justify-content: flex-end; }
        .message.ai { justify-content: flex-start; }

        .bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 20px;
            line-height: 1.6;
            font-size: 14px;
            position: relative;
        }
        .student .bubble {
            background: #003366;
            color: white;
            border-bottom-right-radius: 5px;
        }
        .ai .bubble {
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Hint Badge on AI Messages */
        .ai .bubble .hint-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .ai .bubble .hint-badge i {
            margin-right: 4px;
        }

        /* Message Actions */
        .message-actions {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s;
            background: white;
            padding: 5px 8px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .message.student .message-actions {
            right: calc(100% + 10px);
        }
        .message.ai .message-actions {
            left: calc(100% + 10px);
        }
        .message:hover .message-actions { opacity: 1; }

        .edit-btn, .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            padding: 5px 8px;
            border-radius: 15px;
            transition: background 0.2s;
        }
        .edit-btn { color: #003366; }
        .edit-btn:hover { background: #e8f0fe; }
        .copy-btn { color: #2e7d32; }
        .copy-btn:hover { background: #e8f5e9; }

        .message-time {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.6;
        }
        .student .message-time { text-align: right; color: #003366; }
        .ai .message-time { color: #888; }

        /* Typing Indicator */
        .typing-indicator {
            background: white;
            padding: 12px 18px;
            border-radius: 20px;
            border-bottom-left-radius: 5px;
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #888;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-8px); opacity: 1; }
        }

        /* Input Area */
        .input-area {
            background: white;
            padding: 15px 25px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 12px;
        }
        .input-area input {
            flex: 1;
            padding: 14px 18px;
            border: 1.5px solid #e0e0e0;
            border-radius: 30px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        .input-area input:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }
        .input-area button {
            background: #003366;
            color: white;
            border: none;
            padding: 0 28px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .input-area button:hover:not(:disabled) { background: #1a4d8c; }
        .input-area button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-chat {
            text-align: center;
            padding: 60px 20px;
            color: #888;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .empty-chat .icon { font-size: 60px; margin-bottom: 20px; }
        .empty-chat h3 { color: #003366; margin-bottom: 10px; }
        .empty-chat p { color: #888; font-size: 14px; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
        }
        .modal-content h3 {
            margin-bottom: 15px;
            color: #003366;
        }
        .modal-content input, .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 15px;
            font-family: inherit;
        }
        .modal-content textarea { min-height: 100px; resize: vertical; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; }
        .modal-save { background: #003366; color: white; }
        .modal-cancel { background: #ccc; color: #333; }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .toast.show { opacity: 1; }

        @media (max-width: 768px) {
            .sidebar { width: 80px; }
            .sidebar-header h3, .session-title, .session-date, .rename-btn, .delete-session-btn { display: none; }
            .new-chat-btn { font-size: 20px; padding: 10px; }
            .session-item { justify-content: center; padding: 12px 5px; }
            .message-actions { opacity: 1; }
            .message.student .message-actions {
                right: auto;
                left: calc(100% + 5px);
            }
            .bubble { max-width: 85%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🤖 Study Helper</h1>
        <a href="student_dashboard_2.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>📋 Chat History</h3>
                <button class="new-chat-btn" onclick="startNewChat()">+ New Conversation</button>
            </div>
            <div class="sessions-list" id="sessionsList">
                <div style="text-align: center; padding: 20px; color: #888;">Loading conversations...</div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-header">
                <div>
                    <h2 id="chatTitle">AI Tutor</h2>
                    <p>💡 Get hints, ideas, and guidance to help you learn</p>
                </div>
            </div>

            <div class="chat-box" id="chatBox">
                <div class="empty-chat" id="emptyChat">
                    <div class="icon">💬</div>
                    <h3>Start a conversation</h3>
                    <p>Ask any question and get helpful hints to guide your learning.</p>
                </div>
            </div>

            <div class="input-area">
                <input type="text" id="userInput" placeholder="Type your question here..." autocomplete="off">
                <button id="sendBtn">Send</button>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <h3>Rename Conversation</h3>
            <input type="text" id="renameInput" placeholder="Enter new name">
            <div class="modal-buttons">
                <button class="modal-cancel" onclick="closeRenameModal()">Cancel</button>
                <button class="modal-save" onclick="confirmRename()">Save</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Your Message</h3>
            <textarea id="editInput" placeholder="Edit your message..."></textarea>
            <div class="modal-buttons">
                <button class="modal-cancel" onclick="closeEditModal()">Cancel</button>
                <button class="modal-save" onclick="confirmEdit()">Save & Regenerate</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let currentSessionId = <?php echo $current_session_id ? $current_session_id : 'null'; ?>;
        let isLoading = false;
        let currentMessages = [];
        let pendingRenameSessionId = null;
        let pendingEditMessage = null;

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.background = isError ? '#c00' : '#2e7d32';
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // SESSION MANAGEMENT
        // ============================================

        async function loadSessions() {
            try {
                const response = await fetch('api/get_sessions.php');
                const data = await response.json();
                const sessionsList = document.getElementById('sessionsList');
                
                if (data.sessions && data.sessions.length > 0) {
                    sessionsList.innerHTML = '';
                    data.sessions.forEach(session => {
                        const sessionDiv = document.createElement('div');
                        sessionDiv.className = `session-item ${currentSessionId == session.session_id ? 'active' : ''}`;
                        sessionDiv.innerHTML = `
                            <div class="session-info" onclick="loadChatHistory(${session.session_id})">
                                <div class="session-title">${escapeHtml(session.title.substring(0, 40))}${session.title.length > 40 ? '...' : ''}</div>
                                <div class="session-date">${session.updated_at}</div>
                            </div>
                            <div class="session-actions">
                                <button class="rename-btn" onclick="event.stopPropagation(); openRenameModal(${session.session_id}, '${escapeHtml(session.title)}')"><i class="fas fa-edit"></i></button>
                            </div>
                        `;
                        sessionsList.appendChild(sessionDiv);
                    });
                } else {
                    sessionsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #888;">No conversations yet.<br>Start a new chat!</div>';
                }
            } catch (err) {
                console.error('Error loading sessions:', err);
            }
        }

        async function startNewChat() {
            currentSessionId = null;
            currentMessages = [];
            
            const url = new URL(window.location);
            url.searchParams.delete('session');
            window.history.pushState({}, '', url);
            
            const chatBox = document.getElementById('chatBox');
            chatBox.innerHTML = `
                <div class="empty-chat" id="emptyChat">
                    <div class="icon">💬</div>
                    <h3>Start a conversation</h3>
                    <p>Ask any question and get helpful hints to guide your learning.</p>
                </div>
            `;
            
            document.getElementById('chatTitle').innerHTML = '✨ New Conversation';
            await loadSessions();
        }

        // ============================================
        // CHAT HISTORY
        // ============================================

        async function loadChatHistory(sessionId) {
            if (isLoading) return;
            isLoading = true;
            
            currentSessionId = sessionId;
            
            const url = new URL(window.location);
            url.searchParams.set('session', sessionId);
            window.history.pushState({}, '', url);
            
            document.querySelectorAll('.session-item').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('.session-item').forEach(item => {
                const btn = item.querySelector('.rename-btn');
                if (btn && btn.getAttribute('onclick')?.includes(`openRenameModal(${sessionId}`)) {
                    item.classList.add('active');
                }
            });
            
            try {
                const response = await fetch(`api/get_chat_history.php?session_id=${sessionId}`);
                const data = await response.json();
                
                const chatBox = document.getElementById('chatBox');
                chatBox.innerHTML = '';
                currentMessages = [];
                
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach((msg) => {
                        currentMessages.push({
                            id: msg.message_id,
                            text: msg.message,
                            sender: msg.sender,
                            time: msg.time
                        });
                        appendMessageToChat(msg.message, msg.sender === 'student', msg.time, msg.message_id);
                    });
                } else {
                    chatBox.innerHTML = `
                        <div class="empty-chat" id="emptyChat">
                            <div class="icon">💬</div>
                            <h3>No messages yet</h3>
                            <p>Start the conversation by asking a question!</p>
                        </div>
                    `;
                }
                
                document.getElementById('chatTitle').innerHTML = `📝 Conversation ${sessionId}`;
                
            } catch (err) {
                console.error('Error loading chat history:', err);
            }
            
            isLoading = false;
        }

        // ============================================
        // MESSAGE DISPLAY
        // ============================================

        function appendMessageToChat(message, isStudent, time = null, messageId = null) {
            const chatBox = document.getElementById('chatBox');
            const empty = chatBox.querySelector('.empty-chat');
            if (empty) empty.remove();
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isStudent ? 'student' : 'ai'}`;
            messageDiv.setAttribute('data-message-id', messageId || '');
            
            let actionsHtml = '';
            if (isStudent) {
                actionsHtml = `
                    <div class="message-actions">
                        <button class="edit-btn" onclick="openEditModal('${messageId}', '${escapeHtml(message)}')"><i class="fas fa-pen"></i></button>
                        <button class="copy-btn" onclick="copyMessage('${escapeHtml(message)}')"><i class="fas fa-copy"></i></button>
                    </div>
                `;
            } else {
                actionsHtml = `
                    <div class="message-actions">
                        <button class="copy-btn" onclick="copyMessage('${escapeHtml(message)}')"><i class="fas fa-copy"></i></button>
                    </div>
                `;
            }
            
            // Format message with bold and emojis
            let formattedMessage = escapeHtml(message);
            formattedMessage = formattedMessage.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            formattedMessage = formattedMessage.replace(/\n/g, '<br>');
            
            const bubbleContent = isStudent ? 
                `${formattedMessage}` :
                `<div class="hint-badge"><i class="fas fa-lightbulb"></i> Hint & Idea</div>
                 ${formattedMessage}`;
            
            messageDiv.innerHTML = `
                ${actionsHtml}
                <div class="bubble">
                    ${bubbleContent}
                    ${time ? `<div class="message-time">${time}</div>` : ''}
                </div>
            `;
            chatBox.appendChild(messageDiv);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // ============================================
        // MESSAGE ACTIONS
        // ============================================

        async function copyMessage(text) {
            try {
                await navigator.clipboard.writeText(text);
                showToast('Copied to clipboard!');
            } catch (err) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!');
            }
        }

        function openEditModal(messageId, currentText) {
            pendingEditMessage = messageId;
            document.getElementById('editInput').value = currentText;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            pendingEditMessage = null;
        }

        async function confirmEdit() {
            const newMessage = document.getElementById('editInput').value.trim();
            if (!newMessage) {
                showToast('Message cannot be empty', true);
                return;
            }
            
            try {
                const formData = new URLSearchParams();
                formData.append('message_id', pendingEditMessage);
                formData.append('new_message', newMessage);
                
                const response = await fetch('api/edit_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showToast('Message edited! Reloading conversation...');
                    await loadChatHistory(currentSessionId);
                } else {
                    showToast(data.error || 'Failed to edit message', true);
                }
            } catch (err) {
                showToast('Error editing message', true);
            }
            closeEditModal();
        }

        function openRenameModal(sessionId, currentTitle) {
            pendingRenameSessionId = sessionId;
            document.getElementById('renameInput').value = currentTitle;
            document.getElementById('renameModal').style.display = 'flex';
        }

        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
            pendingRenameSessionId = null;
        }

        async function confirmRename() {
            const newTitle = document.getElementById('renameInput').value.trim();
            if (!newTitle) {
                showToast('Title cannot be empty', true);
                return;
            }
            
            try {
                const formData = new URLSearchParams();
                formData.append('session_id', pendingRenameSessionId);
                formData.append('new_title', newTitle);
                
                const response = await fetch('api/rename_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    showToast('Conversation renamed successfully!');
                    await loadSessions();
                } else {
                    showToast(data.error || 'Failed to rename', true);
                }
            } catch (err) {
                showToast('Error renaming conversation', true);
            }
            closeRenameModal();
        }

        // ============================================
        // SEND MESSAGE
        // ============================================

        async function sendMessage() {
            const input = document.getElementById('userInput');
            const message = input.value.trim();
            
            if (!message || isLoading) return;
            
            input.value = '';
            
            if (!currentSessionId) {
                const url = new URL(window.location);
                url.searchParams.delete('session');
                window.history.pushState({}, '', url);
            }
            
            appendMessageToChat(message, true, new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            
            const chatBox = document.getElementById('chatBox');
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message ai';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = `
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            `;
            chatBox.appendChild(typingDiv);
            chatBox.scrollTop = chatBox.scrollHeight;
            
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            isLoading = true;
            
            try {
                const formData = new URLSearchParams();
                formData.append('message', message);
                if (currentSessionId) {
                    formData.append('session_id', currentSessionId);
                }
                
                const response = await fetch('api/socratic_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                });
                
                const data = await response.json();
                
                const indicator = document.getElementById('typingIndicator');
                if (indicator) indicator.remove();
                
                if (data.reply) {
                    appendMessageToChat(data.reply, false, new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
                    
                    if (data.session_id && !currentSessionId) {
                        currentSessionId = data.session_id;
                        await loadSessions();
                        const url = new URL(window.location);
                        url.searchParams.set('session', currentSessionId);
                        window.history.pushState({}, '', url);
                        document.getElementById('chatTitle').innerHTML = `📝 Conversation ${currentSessionId}`;
                    }
                } else if (data.error) {
                    appendMessageToChat('Sorry, an error occurred. Please try again.', false);
                    showToast(data.error, true);
                }
            } catch (err) {
                const indicator = document.getElementById('typingIndicator');
                if (indicator) indicator.remove();
                appendMessageToChat('Network error. Please check your connection.', false);
                console.error('Error:', err);
            }
            
            isLoading = false;
            sendBtn.disabled = false;
            input.focus();
            
            await loadSessions();
        }

        // ============================================
        // EVENT LISTENERS
        // ============================================

        document.getElementById('sendBtn').addEventListener('click', sendMessage);
        document.getElementById('userInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeRenameModal();
                closeEditModal();
            }
        });

        // ============================================
        // INIT
        // ============================================

        loadSessions();
        if (currentSessionId) {
            loadChatHistory(currentSessionId);
        }
    </script>
</body>
</html>