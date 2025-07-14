// Add Font Awesome to the page
function addFontAwesome() {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
    link.onload = () => {
        // Font Awesome has loaded, now we can initialize the extension
        init();
    };
    document.head.appendChild(link);
}

// Cache for storing conversation history
let conversationHistory = [];
let currentUrl = window.location.href;
let articleContent = null;
let assistantElements = null;

// Add Font Awesome
addFontAwesome();

// Watch for URL changes
let lastUrl = window.location.href;
new MutationObserver(() => {
    const url = window.location.href;
    if (url !== lastUrl) {
        lastUrl = url;
        currentUrl = url;
        conversationHistory = [];
        articleContent = null;
        init(); // Reinitialize on URL change
    }
}).observe(document, { subtree: true, childList: true });

// Listen for messages from the popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'activate_assistant') {
        activateAssistant();
    }
    return true;
});

// Activate the assistant
function activateAssistant() {
    // If we already have the assistant elements, just toggle the popup
    if (assistantElements && assistantElements.popup) {
        const isActive = assistantElements.popup.classList.contains('active');

        if (isActive) {
            assistantElements.popup.classList.remove('active');
        } else {
            assistantElements.popup.classList.add('active');

            // If this is the first time opening, add welcome message
            const chatContainer = assistantElements.popup.querySelector('.chat-container');
            if (chatContainer.children.length === 0) {
                addMessage('welcome', 'Welcome! I can help you understand this page. Try the buttons below or ask me a question.', chatContainer);

                // Pre-fetch article content
                if (!articleContent) {
                    articleContent = getArticleContent();
                }
            }
        }
    } else {
        // Initialize the assistant if it doesn't exist yet
        init();

        // Activate the popup after a short delay to ensure it's been created
        setTimeout(() => {
            if (assistantElements && assistantElements.popup) {
                assistantElements.popup.classList.add('active');
            }
        }, 100);
    }
}

// Only run on article pages
function isArticle() {
    // Check if we're on an article page by looking for article-specific elements
    return document.querySelector('article') !== null ||
        document.querySelector('[role="article"]') !== null ||
        document.querySelector('.article') !== null ||
        document.querySelector('.post') !== null ||
        document.querySelector('.blog-post') !== null;
}

// Create the UI elements
function createElements() {
    // Create button
    const button = document.createElement('button');
    button.className = 'page-assistant-btn';
    button.innerHTML = '<i class="fas fa-feather-alt"></i><div class="btn-text">Chat</div>';

    // Create popup
    const popup = document.createElement('div');
    popup.className = 'page-assistant-popup';

    // Create popup content
    popup.innerHTML = `
        <div class="popup-header"><i class="fas fa-leaf"></i> Essenca
            <button class="popup-close">×</button>
        </div>
        <div class="chat-container"></div>
        <div class="hot-buttons">
            <button class="hot-button" data-action="summary"><i class="fas fa-file-alt"></i> Summary</button>
            <button class="hot-button" data-action="key-takeaway"><i class="fas fa-lightbulb"></i> Key Takeaway</button>
        </div>
        <div class="input-container">
            <input type="text" class="chat-input" placeholder="Ask about this page...">
            <button class="send-button"><i class="fas fa-paper-plane"></i></button>
        </div>
    `;

    return { button, popup };
}

// Add a message to the chat
function addMessage(type, content, container) {
    const messageElement = document.createElement('div');
    messageElement.className = `message ${type}`;

    if (type === 'loading') {
        messageElement.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Thinking...`;
    } else {
        messageElement.innerHTML = formatMarkdown(content);

        // Add to conversation history if it's a user or AI message
        if (type === 'user' || type === 'ai') {
            conversationHistory.push({ role: type === 'user' ? 'user' : 'assistant', content });
        }
    }

    container.appendChild(messageElement);

    // Scroll to the bottom
    container.scrollTop = container.scrollHeight;

    return messageElement;
}

// Handle button click
function handleButtonClick(button, popup) {
    button.addEventListener('click', () => {
        const isActive = popup.classList.contains('active');

        if (isActive) {
            popup.classList.remove('active');
        } else {
            popup.classList.add('active');

            const chatContainer = popup.querySelector('.chat-container');

            // If this is the first time opening, add welcome message
            if (chatContainer.children.length === 0) {
                addMessage('welcome', 'Welcome! I can help you understand this page. Try the buttons below or ask me a question.', chatContainer);

                // Pre-fetch article content
                if (!articleContent) {
                    articleContent = getArticleContent();
                }
            }
        }
    });

    // Handle close button
    const closeButton = popup.querySelector('.popup-close');
    closeButton.addEventListener('click', (e) => {
        e.stopPropagation();
        popup.classList.remove('active');
    });
}

// Handle hot buttons
function handleHotButtons(popup) {
    const chatContainer = popup.querySelector('.chat-container');
    const hotButtons = popup.querySelectorAll('.hot-button');

    hotButtons.forEach(button => {
        button.addEventListener('click', () => {
            const action = button.dataset.action;

            // Get article content if not already fetched
            if (!articleContent) {
                articleContent = getArticleContent();
            }

            // Add user message
            const userMessage = action === 'summary'
                ? 'Summarize this page for me'
                : 'What is the key takeaway from this page?';

            addMessage('user', userMessage, chatContainer);

            // Add loading message
            const loadingMessage = addMessage('loading', '', chatContainer);

            // Send to background script
            chrome.runtime.sendMessage(
                {
                    action: action,
                    content: articleContent,
                    history: conversationHistory.slice(-10) // Send last 10 messages for context
                },
                response => {
                    // Remove loading message
                    chatContainer.removeChild(loadingMessage);

                    if (response.success) {
                        addMessage('ai', response.result, chatContainer);
                    } else {
                        addMessage('ai', `Sorry, I encountered an error: ${response.error}`, chatContainer);
                    }
                }
            );
        });
    });
}

// Handle chat input
function handleChatInput(popup) {
    const chatContainer = popup.querySelector('.chat-container');
    const chatInput = popup.querySelector('.chat-input');
    const sendButton = popup.querySelector('.send-button');

    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;

        // Add user message to chat
        addMessage('user', message, chatContainer);

        // Clear input
        chatInput.value = '';

        // Get article content if not already fetched
        if (!articleContent) {
            articleContent = getArticleContent();
        }

        // Add loading message
        const loadingMessage = addMessage('loading', '', chatContainer);

        // Send to background script
        chrome.runtime.sendMessage(
            {
                action: 'chat',
                content: articleContent,
                message: message,
                history: conversationHistory.slice(-10) // Send last 10 messages for context
            },
            response => {
                // Remove loading message
                chatContainer.removeChild(loadingMessage);

                if (response.success) {
                    addMessage('ai', response.result, chatContainer);
                } else {
                    addMessage('ai', `Sorry, I encountered an error: ${response.error}`, chatContainer);
                }
            }
        );
    }

    // Send on button click
    sendButton.addEventListener('click', sendMessage);

    // Send on Enter key
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
}

// Close popup when clicking outside
function handleClickOutside(button, popup) {
    document.addEventListener('click', (event) => {
        if (!button.contains(event.target) && !popup.contains(event.target)) {
            popup.classList.remove('active');
        }
    });
}

// Function to check if we're on an email page
function isEmailPage() {
    const url = window.location.href.toLowerCase();
    return url.includes('mail.google.com') ||
        url.includes('outlook.live.com') ||
        url.includes('outlook.office.com') ||
        url.includes('mail.yahoo.com') ||
        url.includes('protonmail.com');
}

// Get page content
function getArticleContent() {
    try {
        // Check if we're on an email page
        if (isEmailPage()) {
            return getEmailContent();
        }

        // --- Readability.js extraction ---
        if (typeof Readability === 'function') {
            try {
                const docClone = document.cloneNode(true);
                const reader = new Readability(docClone);
                const article = reader.parse();
                if (article && article.textContent && article.textContent.length > 200) {
                    return article.title + '\n\n' + article.textContent;
                }
            } catch (e) {
                console.warn('Readability.js failed:', e);
            }
        }

        // Fallback: extract all visible text from the page
        const allText = getAllVisibleText();
        if (allText && allText.length > 100) {
            return allText;
        }

        // Try to get content using different methods (legacy logic)
        let content = '';
        let title = '';

        // Method 1: Standard article elements
        const articleContent = getStandardArticleContent();
        if (articleContent && articleContent.length > 100) {
            return articleContent;
        }

        // Method 2: Main content area
        const mainContent = getMainContent();
        if (mainContent && mainContent.length > 100) {
            return mainContent;
        }

        // Method 3: General page content
        return getGeneralPageContent();
    } catch (error) {
        console.error('Error extracting page content:', error);
        return 'I can help you with this page, but I couldn\'t extract specific content. Feel free to ask me questions!';
    }
}

// Extract all visible text from the page (including all elements, not just paragraphs)
function getAllVisibleText() {
    function isVisible(node) {
        return !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length);
    }
    function getTextFromNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            // Only include visible text nodes
            if (node.parentElement && isVisible(node.parentElement)) {
                const text = node.textContent.trim();
                if (text.length > 0) return text;
            }
            return '';
        } else if (node.nodeType === Node.ELEMENT_NODE && isVisible(node)) {
            // Skip script/style/noscript/meta
            const tag = node.tagName.toLowerCase();
            if (["script", "style", "noscript", "meta", "head", "title", "svg"].includes(tag)) return '';
            let text = '';
            for (let child of node.childNodes) {
                text += getTextFromNode(child) + ' ';
            }
            return text;
        }
        return '';
    }
    const body = document.body;
    const text = getTextFromNode(body).replace(/\s+/g, ' ').trim();
    // Optionally prepend the page title
    const title = document.title ? document.title + '\n\n' : '';
    return title + text;
}

// Get content from standard article elements
function getStandardArticleContent() {
    // Get article title
    const titleElement = document.querySelector('h1');
    const title = titleElement ? titleElement.textContent.trim() : 'Untitled Page';

    // Get article content
    const article = document.querySelector('article') ||
        document.querySelector('[role="article"]') ||
        document.querySelector('.article') ||
        document.querySelector('.post') ||
        document.querySelector('.blog-post');

    if (!article) {
        return null;
    }

    // Get all paragraphs and headers
    const contentElements = article.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
    let content = '';

    contentElements.forEach(element => {
        // Skip elements that are part of embeds, comments, or other non-article content
        if (element.closest('.supplementalPostContent') ||
            element.closest('.responsesWrapper') ||
            element.closest('.butterBar') ||
            element.closest('.comments')) {
            return;
        }

        // Add appropriate spacing based on element type
        if (element.tagName.toLowerCase().startsWith('h')) {
            content += element.textContent.trim() + '\n\n';
        } else {
            content += element.textContent.trim() + '\n\n';
        }
    });

    return `${title}\n\n${content}`;
}

// Get content from main content area
function getMainContent() {
    // Try to find main content area
    const main = document.querySelector('main') ||
        document.querySelector('#main') ||
        document.querySelector('.main') ||
        document.querySelector('#content') ||
        document.querySelector('.content');

    if (!main) {
        return null;
    }

    // Get title
    const titleElement = document.querySelector('h1') || document.querySelector('title');
    const title = titleElement ? titleElement.textContent.trim() : 'Untitled Page';

    // Get all paragraphs and headers
    const contentElements = main.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
    let content = '';

    contentElements.forEach(element => {
        // Add appropriate spacing based on element type
        if (element.tagName.toLowerCase().startsWith('h')) {
            content += element.textContent.trim() + '\n\n';
        } else {
            content += element.textContent.trim() + '\n\n';
        }
    });

    return `${title}\n\n${content}`;
}

// Get general page content
function getGeneralPageContent() {
    // Get title
    const titleElement = document.querySelector('title') || document.querySelector('h1');
    const title = titleElement ? titleElement.textContent.trim() : window.location.href;

    // Get all paragraphs and headers from the body
    const contentElements = document.body.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
    let content = '';

    // Limit to first 50 elements to avoid overwhelming with content
    const elementsArray = Array.from(contentElements).slice(0, 50);

    elementsArray.forEach(element => {
        // Skip hidden elements or those with very little content
        if (element.offsetParent === null || element.textContent.trim().length < 5) {
            return;
        }

        // Skip elements that are likely navigation, footer, etc.
        if (element.closest('nav') ||
            element.closest('footer') ||
            element.closest('header') ||
            element.closest('.navigation') ||
            element.closest('.menu') ||
            element.closest('.sidebar')) {
            return;
        }

        // Add appropriate spacing based on element type
        if (element.tagName.toLowerCase().startsWith('h')) {
            content += element.textContent.trim() + '\n\n';
        } else {
            content += element.textContent.trim() + '\n\n';
        }
    });

    // If we couldn't extract much content, add URL
    if (content.length < 100) {
        content += `\n\nPage URL: ${window.location.href}`;
    }

    return `${title}\n\n${content}`;
}

// Get content from email pages
function getEmailContent() {
    const url = window.location.href.toLowerCase();
    let emailContent = '';

    // Get title/subject
    let title = 'Email Content';

    // Gmail
    if (url.includes('mail.google.com')) {
        const subjectElement = document.querySelector('h2[data-thread-perm-id]');
        if (subjectElement) {
            title = 'Email: ' + subjectElement.textContent.trim();
        }

        // Try to get email body
        const emailBody = document.querySelector('.a3s.aiL') || document.querySelector('[role="main"]');
        if (emailBody) {
            emailContent = emailBody.innerText;
        }
    }
    // Outlook
    else if (url.includes('outlook.')) {
        const subjectElement = document.querySelector('[role="heading"][aria-level="2"]');
        if (subjectElement) {
            title = 'Email: ' + subjectElement.textContent.trim();
        }

        // Try to get email body
        const emailBody = document.querySelector('[role="region"][aria-label*="Message body"]');
        if (emailBody) {
            emailContent = emailBody.innerText;
        }
    }
    // Other email providers - generic approach
    else {
        // Try common email subject selectors
        const subjectElement = document.querySelector('.subject') ||
            document.querySelector('[data-testid="message-subject"]');
        if (subjectElement) {
            title = 'Email: ' + subjectElement.textContent.trim();
        }

        // Try to get email body using common selectors
        const emailBody = document.querySelector('.email-body') ||
            document.querySelector('.message-body') ||
            document.querySelector('[role="main"]');
        if (emailBody) {
            emailContent = emailBody.innerText;
        }
    }

    // If we couldn't extract content, provide a generic message
    if (!emailContent || emailContent.length < 20) {
        return 'This appears to be an email. I can help you with questions about it, but I couldn\'t extract the specific content.';
    }

    return `${title}\n\n${emailContent}`;
}

// Format markdown-style text to HTML
function formatMarkdown(text) {
    return text
        // Normalize line endings
        .replace(/\r\n/g, '\n')
        // Remove extra blank lines
        .replace(/\n\s*\n/g, '\n\n')
        // Convert ** ** or __ __ to bold
        .replace(/(\*\*|__)(.*?)\1/g, '<strong>$2</strong>')
        // Convert * * or _ _ to italic
        .replace(/(\*|_)(.*?)\1/g, '<em>$2</em>')
        // Convert bullet points and ensure consistent spacing
        .replace(/^[\s]*[-*•][\s]+(.+)$/gm, '<li>$1</li>')
        // Wrap bullet points in ul, handling consecutive items
        .replace(/((?:<li>.*?<\/li>\n?)+)/g, '<ul>$1</ul>')
        // Convert remaining line breaks
        .replace(/\n/g, '<br>')
        // Clean up breaks around lists
        .replace(/(<br>)+\s*<ul>/g, '<ul>')
        .replace(/<\/ul>\s*(<br>)+/g, '</ul>')
        // Ensure single break after lists
        .replace(/<\/ul>/g, '</ul><br>')
        // Clean up multiple breaks
        .replace(/(<br>){3,}/g, '<br><br>');
}

// Initialize the extension
function init() {
    // Remove any existing elements
    const existingButton = document.querySelector('.page-assistant-btn');
    const existingPopup = document.querySelector('.page-assistant-popup');

    if (existingButton) existingButton.remove();
    if (existingPopup) existingPopup.remove();

    const { button, popup } = createElements();

    // Store elements for later access
    assistantElements = { button, popup };

    // Add elements to page
    document.body.appendChild(button);
    document.body.appendChild(popup);

    // Setup event handlers
    handleButtonClick(button, popup);
    handleHotButtons(popup);
    handleChatInput(popup);
    handleClickOutside(button, popup);
}

// Run initialization
addFontAwesome();
