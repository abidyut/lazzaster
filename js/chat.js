// Chat Support JavaScript Module
class ChatManager {
    constructor() {
        this.API_BASE_URL = window.location.origin;
        this.conversationId = null;
        this.sessionId = null;
        this.lastMessageId = 0;
        this.isConnected = false;
        this.pollInterval = null;
        this.currentCategory = 'general';
        
        // Initialize chat if elements exist
        if (document.getElementById('chatModal')) {
            this.init();
        }
    }

    init() {
        // Get user data if logged in
        const userData = localStorage.getItem('userData');
        this.user = userData ? JSON.parse(userData) : null;
        
        // Generate session ID for anonymous users
        if (!this.sessionId) {
            this.sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Update status
        this.updateChatStatus();
    }

    setupEventListeners() {
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendMessage');
        
        if (chatInput) {
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
        
        if (sendButton) {
            sendButton.addEventListener('click', () => this.sendMessage());
        }
    }

    async startConversation(category = 'general', priority = 'normal') {
        try {
            const response = await fetch(`${this.API_BASE_URL}/api/chat.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'start_conversation',
                    user_id: this.user?.id || null,
                    session_id: this.sessionId,
                    category: category,
                    priority: priority
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.conversationId = result.conversation_id;
                this.isConnected = true;
                this.updateChatStatus('অনলাইন - সংযুক্ত');
                
                // Start polling for new messages
                this.startMessagePolling();
                
                // Load initial messages
                await this.loadMessages();
                
                return true;
            } else {
                console.error('Failed to start conversation:', result.message);
                this.showError('চ্যাট শুরু করতে সমস্যা হয়েছে');
                return false;
            }
        } catch (error) {
            console.error('Network error starting conversation:', error);
            this.showError('নেটওয়ার্ক সমস্যা হয়েছে');
            return false;
        }
    }

    async sendMessage() {
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendMessage');
        
        if (!chatInput || !sendButton) return;
        
        const message = chatInput.value.trim();
        if (!message) return;
        
        // Start conversation if not already started
        if (!this.conversationId) {
            const started = await this.startConversation(this.currentCategory);
            if (!started) return;
        }

        // Disable input while sending
        chatInput.disabled = true;
        sendButton.disabled = true;
        
        try {
            // Show message immediately in UI
            this.addMessageToUI({
                id: 'temp_' + Date.now(),
                sender_type: 'user',
                message: message,
                created_at: new Date().toISOString(),
                sender_name: this.user?.username || 'আপনি'
            });
            
            // Clear input
            chatInput.value = '';

            const response = await fetch(`${this.API_BASE_URL}/api/chat.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_message',
                    conversation_id: this.conversationId,
                    sender_id: this.user?.id || null,
                    sender_type: 'user',
                    message: message,
                    message_type: 'text'
                })
            });

            const result = await response.json();
            
            if (!result.success) {
                this.showError('মেসেজ পাঠাতে সমস্যা হয়েছে');
                // Remove the temporary message
                const tempMsg = document.querySelector('[data-message-id="temp_' + (Date.now() - 1000) + '"]');
                if (tempMsg) tempMsg.remove();
            }
            
        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('মেসেজ পাঠাতে সমস্যা হয়েছে');
        } finally {
            // Re-enable input
            chatInput.disabled = false;
            sendButton.disabled = false;
            chatInput.focus();
        }
    }

    async loadMessages() {
        if (!this.conversationId) return;
        
        try {
            const response = await fetch(`${this.API_BASE_URL}/api/chat.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_messages',
                    conversation_id: this.conversationId,
                    last_message_id: this.lastMessageId
                })
            });

            const result = await response.json();
            
            if (result.success && result.messages) {
                result.messages.forEach(message => {
                    this.addMessageToUI(message);
                    this.lastMessageId = Math.max(this.lastMessageId, message.id);
                });
            }
            
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    addMessageToUI(message) {
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        // Remove welcome message if it exists
        const welcomeMsg = messagesContainer.querySelector('.chat-welcome');
        if (welcomeMsg) {
            welcomeMsg.style.display = 'none';
        }
        
        // Check if message already exists (avoid duplicates)
        if (document.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${message.sender_type}`;
        messageDiv.setAttribute('data-message-id', message.id);
        
        const senderName = message.sender_type === 'user' ? 
            (this.user?.username || 'আপনি') : 
            (message.sender_name || 'সাপোর্ট টিম');
        
        const messageTime = new Date(message.created_at).toLocaleTimeString('bn-BD', {
            hour: '2-digit',
            minute: '2-digit'
        });

        messageDiv.innerHTML = `
            <div class="message-content">
                ${this.escapeHtml(message.message)}
                <div class="message-time">${messageTime}</div>
            </div>
        `;

        messagesContainer.appendChild(messageDiv);
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    startMessagePolling() {
        // Stop existing polling
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        // Start new polling every 3 seconds
        this.pollInterval = setInterval(() => {
            this.loadMessages();
        }, 3000);
    }

    stopMessagePolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    updateChatStatus(status = null) {
        const statusElement = document.getElementById('chatStatus');
        if (statusElement) {
            if (status) {
                statusElement.textContent = status;
            } else {
                statusElement.textContent = this.isConnected ? 'অনলাইন' : 'অফলাইন';
            }
        }
    }

    showError(message) {
        // Create a temporary error message
        const messagesContainer = document.getElementById('chatMessages');
        if (!messagesContainer) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'chat-message system';
        errorDiv.innerHTML = `
            <div class="message-content" style="background: #ff6b6b; color: white;">
                ⚠️ ${message}
            </div>
        `;
        
        messagesContainer.appendChild(errorDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Remove error message after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 5000);
    }

    selectCategory(category, buttonElement) {
        this.currentCategory = category;
        
        // Update button states
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        if (buttonElement) {
            buttonElement.classList.add('active');
        }
        
        // Start conversation with selected category
        if (!this.conversationId) {
            this.startConversation(category);
        }
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    closeChat() {
        this.stopMessagePolling();
        this.isConnected = false;
        this.conversationId = null;
        this.lastMessageId = 0;
        this.updateChatStatus();
        
        // Clear messages
        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            messagesContainer.innerHTML = `
                <div class="chat-welcome">
                    <h4>স্বাগতম! 👋</h4>
                    <p>আমরা আপনাকে সাহায্য করতে এখানে আছি।</p>
                    <div class="chat-category">
                        <button class="category-btn" onclick="window.chatManager?.selectCategory('general', this)">সাধারণ প্রশ্ন</button>
                        <button class="category-btn" onclick="window.chatManager?.selectCategory('deposit', this)">ডিপোজিট</button>
                        <button class="category-btn" onclick="window.chatManager?.selectCategory('withdrawal', this)">উইথড্র</button>
                        <button class="category-btn" onclick="window.chatManager?.selectCategory('technical', this)">টেকনিক্যাল</button>
                        <button class="category-btn" onclick="window.chatManager?.selectCategory('complaint', this)">অভিযোগ</button>
                    </div>
                    <p style="font-size: 0.8rem; margin-top: 10px; color: #999;">একটি বিষয় নির্বাচন করুন বা সরাসরি মেসেজ লিখুন</p>
                </div>
            `;
        }
    }
}

// Global functions for backward compatibility
function toggleChat() {
    const chatModal = document.getElementById('chatModal');
    if (!chatModal) return;
    
    if (chatModal.style.display === 'flex') {
        chatModal.style.display = 'none';
        if (window.chatManager) {
            window.chatManager.closeChat();
        }
    } else {
        chatModal.style.display = 'flex';
        if (!window.chatManager) {
            window.chatManager = new ChatManager();
        }
    }
}

function startLiveChat() {
    const chatModal = document.getElementById('chatModal');
    if (chatModal) {
        chatModal.style.display = 'flex';
        if (!window.chatManager) {
            window.chatManager = new ChatManager();
        }
    }
}

function selectCategory(category, buttonElement) {
    if (window.chatManager) {
        window.chatManager.selectCategory(category, buttonElement);
    }
}

function emergencySupport() {
    if (window.chatManager) {
        window.chatManager.selectCategory('complaint', null);
        startLiveChat();
    } else {
        startLiveChat();
        setTimeout(() => {
            if (window.chatManager) {
                window.chatManager.selectCategory('complaint', null);
            }
        }, 100);
    }
}

// FAQ toggle function
function toggleFaq(element) {
    const answer = element.nextElementSibling;
    const span = element.querySelector('span');
    
    if (answer.classList.contains('active')) {
        answer.classList.remove('active');
        span.textContent = '+';
    } else {
        answer.classList.add('active');
        span.textContent = '-';
    }
}

// Initialize chat manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Set footer year
    const footerYear = document.getElementById('footer-year');
    if (footerYear) {
        footerYear.textContent = new Date().getFullYear();
    }
    
    // Update USDT rate
    const usdtRate = document.getElementById('usdt-rate');
    if (usdtRate) {
        usdtRate.textContent = '0.90';
    }
});