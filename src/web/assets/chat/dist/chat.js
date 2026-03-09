// chat.js
document.addEventListener('alpine:init', () => {
    Alpine.data('craftChat', (config) => ({
        isOpen: false,
        isFullscreen: false,
        hasActiveChat: false,
        autoNavigate: false,
        isLoading: false,
        conversationId: null,
        newMessage: '',
        messages: [],

        botName: config.botName,
        welcomeMsg: config.welcomeMsg,
        csrfTokenValue: config.csrfTokenValue,
        csrfTokenName: config.csrfTokenName,

        init() {
            const storedChat = sessionStorage.getItem('craftChatConvId');
            if (storedChat) {
                this.conversationId = parseInt(storedChat);
                this.hasActiveChat = true;
                const storedMessages = sessionStorage.getItem('craftChatMessages');
                if (storedMessages) {
                    this.messages = JSON.parse(storedMessages);
                } else {
                    this.messages = [{ role: 'assistant', content: this.welcomeMsg }];
                }
            } else {
                this.messages = [{ role: 'assistant', content: this.welcomeMsg }];
                this.saveMessages();
            }

            if (sessionStorage.getItem('craftChatIsOpen') === 'true') {
                // To avoid transition flicker on load if it was open, we can just set it
                this.isOpen = true;
            }
            if (sessionStorage.getItem('craftChatAutoNav') === 'true') {
                this.autoNavigate = true;
            }

            // Watch for changes on autoNavigate to save it
            this.$watch('autoNavigate', value => {
                sessionStorage.setItem('craftChatAutoNav', value);
            });
        },

        saveMessages() {
            sessionStorage.setItem('craftChatMessages', JSON.stringify(this.messages));
        },

        openChat() {
            this.isOpen = true;
            sessionStorage.setItem('craftChatIsOpen', 'true');
            if (!this.conversationId) {
                this.startConversation();
            }
            this.scrollToBottom();
        },

        closeChat() {
            this.isOpen = false;
            sessionStorage.setItem('craftChatIsOpen', 'false');
        },

        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
        },

        async startConversation() {
            try {
                const body = new URLSearchParams();
                body.append(this.csrfTokenName, this.csrfTokenValue);

                const response = await fetch('/craft-chat/api/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                const data = await response.json();
                if (data.success) {
                    this.conversationId = data.conversationId;
                    this.hasActiveChat = true;
                    sessionStorage.setItem('craftChatConvId', this.conversationId);
                }
            } catch (err) {
                console.error("Failed to start chat session", err);
            }
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.conversationId) return;

            const userMsg = this.newMessage.trim();
            this.messages.push({ role: 'user', content: userMsg });
            this.saveMessages();
            this.newMessage = '';
            this.isLoading = true;
            this.scrollToBottom();

            try {
                const body = new URLSearchParams();
                body.append(this.csrfTokenName, this.csrfTokenValue);
                body.append('conversationId', this.conversationId);
                body.append('message', userMsg);

                const response = await fetch('/craft-chat/api/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                const data = await response.json();

                if (data.success) {
                    this.messages.push({ role: 'assistant', content: data.response });
                    this.saveMessages();
                    this.checkForLinks(data.response);
                } else {
                    this.messages.push({ role: 'assistant', content: "Sorry, an error occurred." });
                    this.saveMessages();
                }
            } catch (err) {
                console.error(err);
                this.messages.push({ role: 'assistant', content: "Network error occurred." });
            } finally {
                this.isLoading = false;
                this.scrollToBottom();
            }
        },

        checkForLinks(text) {
            // Simple markdown link detection [Text](url)
            const linkRegex = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g;
            let match;
            while ((match = linkRegex.exec(text)) !== null) {
                const url = match[2];
                // If autonav is on, redirect
                if (this.autoNavigate) {
                    window.location.href = url;
                }
            }
        },

        formatMessage(text) {
            // Convert markdown style links to stylized HTML
            // Replace [Text](URL) with the requested card format if possible, or simple links
            const linkRegex = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g;

            // Just basic link formatting for now. If it's the whole message, we can make it a card.
            return text.replace(linkRegex, (fullMatch, textContent, url) => {
                // If autoNavigate is on, we can click it to go
                return `<a href="${url}" target="_blank" class="craft-chat-card">
                    <span class="craft-chat-card-title">${textContent} <small>↗</small></span>
                    <span class="craft-chat-card-desc">${url}</span>
                </a>`;
            }).replace(/\n/g, '<br>'); // preserve newlines
        },

        scrollToBottom() {
            setTimeout(() => {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }, 50);
        }
    }));
});
