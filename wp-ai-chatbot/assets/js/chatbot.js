/**
 * WP AI Chatbot - Client Widget Script
 */

(function () {
    'use strict';

    if (window.WPAIChatbotInitialized) {
        return;
    }
    window.WPAIChatbotInitialized = true;

    var config = window.wpAiChatbotConfig || {};
    var apiUrl = config.apiUrl || '/wp-json/wp-ai-chatbot/v1/chat';
    var nonce = config.nonce || '';
    var botTitle = config.botTitle || 'Agent Sidekick';
    var welcomeMsg = config.welcomeMessage || 'Hello! How can I assist you today?';
    var placeholder = config.placeholder || 'Type your question...';
    var suggestions = config.suggestions || [];
    var position = config.position || 'right';
    var themeColor = config.themeColor || '#0073aa';

    // Apply custom theme color if set
    if (themeColor) {
        document.documentElement.style.setProperty('--wp-ai-primary', themeColor);
    }

    var STORAGE_KEY = 'wp_ai_chat_session_history';
    var history = [];

    // Load session history from sessionStorage
    try {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            history = JSON.parse(saved);
        }
    } catch (e) {
        history = [];
    }

    // Build Chat Widget DOM
    function initWidget() {
        var launcher = document.createElement('button');
        launcher.className = 'wp-ai-chat-launcher wp-ai-pos-' + position;
        launcher.setAttribute('aria-label', 'Open Agent Sidekick');
        launcher.innerHTML = `
            <span class="wp-ai-launcher-pulse"></span>
            <svg class="wp-ai-icon-chat" viewBox="0 0 24 24">
                <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A2.5 2.5 0 0 0 5 15.5 2.5 2.5 0 0 0 7.5 18 2.5 2.5 0 0 0 10 15.5 2.5 2.5 0 0 0 7.5 13m9 0a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0 2.5 2.5 2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5z"/>
            </svg>
            <svg class="wp-ai-icon-close" viewBox="0 0 24 24">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        `;

        var windowEl = document.createElement('div');
        windowEl.className = 'wp-ai-chat-window wp-ai-pos-' + position;
        windowEl.innerHTML = `
            <div class="wp-ai-chat-header">
                <div class="wp-ai-header-left">
                    <div class="wp-ai-header-avatar">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A2.5 2.5 0 0 0 5 15.5 2.5 2.5 0 0 0 7.5 18 2.5 2.5 0 0 0 10 15.5 2.5 2.5 0 0 0 7.5 13m9 0a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0 2.5 2.5 2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5z"/>
                        </svg>
                        <span class="wp-ai-status-pulse"></span>
                    </div>
                    <div class="wp-ai-header-info">
                        <h4 class="wp-ai-header-title">${escapeHtml(botTitle)}</h4>
                        <p class="wp-ai-header-subtitle">
                            <span class="wp-ai-online-indicator"></span>
                            Online • Instant AI Answers
                        </p>
                    </div>
                </div>
                <div class="wp-ai-header-actions">
                    <button type="button" class="wp-ai-header-btn wp-ai-btn-reset" title="Start new conversation" aria-label="Start new conversation">
                        <svg viewBox="0 0 24 24">
                            <path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                        </svg>
                    </button>
                    <button type="button" class="wp-ai-header-btn wp-ai-btn-close" title="Close" aria-label="Close Chat">
                        <svg viewBox="0 0 24 24">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="wp-ai-chat-messages" id="wp-ai-messages-container"></div>
            <div class="wp-ai-chat-input-wrapper">
                <div class="wp-ai-chat-input-area">
                    <input type="text" class="wp-ai-input" id="wp-ai-chat-input" placeholder="${escapeHtml(placeholder)}" autocomplete="off" />
                    <button type="button" class="wp-ai-btn-send" id="wp-ai-btn-send" aria-label="Send Message">
                        <svg viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
                <div class="wp-ai-chat-footer">Powered by <strong>WP Agent Sidekick</strong></div>
            </div>
        `;

        document.body.appendChild(launcher);
        document.body.appendChild(windowEl);

        var messagesContainer = windowEl.querySelector('#wp-ai-messages-container');
        var inputField = windowEl.querySelector('#wp-ai-chat-input');
        var sendBtn = windowEl.querySelector('#wp-ai-btn-send');
        var closeBtn = windowEl.querySelector('.wp-ai-btn-close');
        var resetBtn = windowEl.querySelector('.wp-ai-btn-reset');

        // Populate initial messages
        renderInitialMessages(messagesContainer);

        // Toggle chat open/close
        launcher.addEventListener('click', function () {
            var isOpen = windowEl.classList.contains('wp-ai-visible');
            if (isOpen) {
                closeChat();
            } else {
                openChat();
            }
        });

        closeBtn.addEventListener('click', closeChat);

        // Reset conversation
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                history = [];
                try {
                    sessionStorage.removeItem(STORAGE_KEY);
                } catch (e) {}
                renderInitialMessages(messagesContainer);
            });
        }

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && windowEl.classList.contains('wp-ai-visible')) {
                closeChat();
            }
        });

        function openChat() {
            windowEl.classList.add('wp-ai-visible');
            launcher.classList.add('wp-ai-open');
            setTimeout(function () {
                inputField.focus();
                scrollToBottom(messagesContainer);
            }, 100);
        }

        function closeChat() {
            windowEl.classList.remove('wp-ai-visible');
            launcher.classList.remove('wp-ai-open');
        }

        // Send triggers
        sendBtn.addEventListener('click', function () {
            sendMessage(inputField, messagesContainer, sendBtn);
        });

        inputField.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(inputField, messagesContainer, sendBtn);
            }
        });
    }

    function renderInitialMessages(container) {
        container.innerHTML = '';

        // If history exists, restore it
        if (history.length > 0) {
            history.forEach(function (item) {
                appendMessageBubble(container, item.content, item.role);
            });
        } else {
            // First time: display welcome message
            if (welcomeMsg) {
                appendMessageBubble(container, welcomeMsg, 'assistant');
            }

            // Quick suggestion pills
            if (suggestions && suggestions.length > 0) {
                var pillsWrap = document.createElement('div');
                pillsWrap.className = 'wp-ai-suggestions';
                suggestions.forEach(function (text) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'wp-ai-suggestion-btn';
                    btn.textContent = text;
                    btn.addEventListener('click', function () {
                        var input = document.getElementById('wp-ai-chat-input');
                        var send = document.getElementById('wp-ai-btn-send');
                        if (input) {
                            input.value = text;
                            sendMessage(input, container, send);
                        }
                    });
                    pillsWrap.appendChild(btn);
                });
                container.appendChild(pillsWrap);
            }
        }
        scrollToBottom(container);
    }

    function sendMessage(inputField, container, sendBtn) {
        var text = (inputField.value || '').trim();
        if (!text) return;

        // Display user message bubble
        appendMessageBubble(container, text, 'user');
        inputField.value = '';
        inputField.disabled = true;
        sendBtn.disabled = true;

        // Remove suggestions once conversation begins
        var existingSuggestions = container.querySelector('.wp-ai-suggestions');
        if (existingSuggestions) {
            existingSuggestions.remove();
        }

        // Record to conversation history
        history.push({ role: 'user', content: text });
        saveHistory();

        // Show typing indicator
        var typingIndicator = showTypingIndicator(container);
        scrollToBottom(container);

        // Send to WordPress REST API
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                message: text,
                history: history.slice(0, -1), // send preceding history
                page_title: document.title || '',
                page_url: window.location.href || ''
            })
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            });
        })
        .then(function (result) {
            typingIndicator.remove();
            inputField.disabled = false;
            sendBtn.disabled = false;
            inputField.focus();

            if (result.status >= 200 && result.status < 300 && result.data.reply) {
                var reply = result.data.reply;
                appendMessageBubble(container, reply, 'assistant');
                history.push({ role: 'assistant', content: reply });
                saveHistory();

                // If Human Handoff is triggered and provided in response
                if (result.data.handoff && result.data.handoff.enabled && result.data.handoff.url) {
                    appendHandoffCard(container, result.data.handoff);
                }
            } else {
                var errorText = (result.data && result.data.message) ? result.data.message : 'Sorry, something went wrong. Please try again.';
                showError(container, errorText);
            }
            scrollToBottom(container);
        })
        .catch(function (err) {
            typingIndicator.remove();
            inputField.disabled = false;
            sendBtn.disabled = false;
            showError(container, 'Network error. Please check your connection.');
            scrollToBottom(container);
        });
    }

    function appendMessageBubble(container, text, role) {
        var row = document.createElement('div');
        row.className = 'wp-ai-message-row wp-ai-row-' + role;

        if (role === 'assistant') {
            var botAvatar = document.createElement('div');
            botAvatar.className = 'wp-ai-msg-avatar';
            botAvatar.innerHTML = `<svg viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A2.5 2.5 0 0 0 5 15.5 2.5 2.5 0 0 0 7.5 18 2.5 2.5 0 0 0 10 15.5 2.5 2.5 0 0 0 7.5 13m9 0a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0 2.5 2.5 2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5z"/></svg>`;
            row.appendChild(botAvatar);
        }

        var bubble = document.createElement('div');
        bubble.className = 'wp-ai-message ' + (role === 'user' ? 'wp-ai-message-user' : 'wp-ai-message-bot');
        if (role === 'user') {
            bubble.textContent = text;
        } else {
            bubble.innerHTML = renderMarkdown(text);
        }
        row.appendChild(bubble);
        container.appendChild(row);
        return row;
    }

    function appendHandoffCard(container, handoff) {
        var row = document.createElement('div');
        row.className = 'wp-ai-message-row wp-ai-row-assistant';

        var botAvatar = document.createElement('div');
        botAvatar.className = 'wp-ai-msg-avatar';
        botAvatar.innerHTML = `<svg viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A2.5 2.5 0 0 0 5 15.5 2.5 2.5 0 0 0 7.5 18 2.5 2.5 0 0 0 10 15.5 2.5 2.5 0 0 0 7.5 13m9 0a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0 2.5 2.5 2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5z"/></svg>`;
        row.appendChild(botAvatar);

        var card = document.createElement('div');
        card.className = 'wp-ai-handoff-card';
        card.innerHTML = `
            <div class="wp-ai-handoff-caption">
                <span class="wp-ai-handoff-badge">Team Support</span>
                <span>Connect directly with our team:</span>
            </div>
            <a class="wp-ai-handoff-btn" href="${escapeHtml(handoff.url)}" target="${(handoff.url.indexOf('sms:') === 0 || handoff.url.indexOf('tel:') === 0) ? '_self' : '_blank'}" rel="noopener noreferrer">
                ${escapeHtml(handoff.label || 'Connect with Our Team')}
                <svg viewBox="0 0 24 24" class="wp-ai-handoff-arrow"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        `;
        row.appendChild(card);
        container.appendChild(row);
    }

    function showTypingIndicator(container) {
        var row = document.createElement('div');
        row.className = 'wp-ai-message-row wp-ai-row-assistant wp-ai-typing-row';

        var botAvatar = document.createElement('div');
        botAvatar.className = 'wp-ai-msg-avatar';
        botAvatar.innerHTML = `<svg viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7.5 13A2.5 2.5 0 0 0 5 15.5 2.5 2.5 0 0 0 7.5 18 2.5 2.5 0 0 0 10 15.5 2.5 2.5 0 0 0 7.5 13m9 0a2.5 2.5 0 0 0-2.5 2.5 2.5 2.5 0 0 0 2.5 2.5 2.5 2.5 0 0 0 2.5-2.5 2.5 2.5 0 0 0-2.5-2.5z"/></svg>`;
        row.appendChild(botAvatar);

        var indicator = document.createElement('div');
        indicator.className = 'wp-ai-typing-indicator';
        indicator.innerHTML = `
            <span class="wp-ai-typing-dot"></span>
            <span class="wp-ai-typing-dot"></span>
            <span class="wp-ai-typing-dot"></span>
        `;
        row.appendChild(indicator);
        container.appendChild(row);
        return row;
    }

    function showError(container, message) {
        var errorBanner = document.createElement('div');
        errorBanner.className = 'wp-ai-error-banner';
        errorBanner.textContent = message;
        container.appendChild(errorBanner);
    }

    function saveHistory() {
        try {
            // Keep at most 20 recent messages in sessionStorage
            var trimmed = history.slice(-20);
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
        } catch (e) {}
    }

    function scrollToBottom(container) {
        container.scrollTop = container.scrollHeight;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Lightweight and safe Markdown formatter
    function renderMarkdown(md) {
        if (!md) return '';
        var escaped = escapeHtml(md);

        // Code blocks: ```code```
        escaped = escaped.replace(/```([\s\S]*?)```/g, function (match, code) {
            return '<pre><code>' + code.trim() + '</code></pre>';
        });

        // Inline code: `code`
        escaped = escaped.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Bold: **text** or __text__
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        escaped = escaped.replace(/__(.*?)__/g, '<strong>$1</strong>');

        // Italic: *text* or _text_
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Markdown links: [title](url)
        escaped = escaped.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        // Bullet lists: - item or * item
        var lines = escaped.split('\n');
        var inList = false;
        var formattedLines = [];

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (/^[-*]\s+(.*)/.test(line)) {
                if (!inList) {
                    formattedLines.push('<ul>');
                    inList = true;
                }
                formattedLines.push('<li>' + line.replace(/^[-*]\s+/, '') + '</li>');
            } else {
                if (inList) {
                    formattedLines.push('</ul>');
                    inList = false;
                }
                if (line.length > 0) {
                    formattedLines.push('<p>' + line + '</p>');
                }
            }
        }

        if (inList) {
            formattedLines.push('</ul>');
        }

        return formattedLines.join('');
    }

    // Initialize once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
})();
