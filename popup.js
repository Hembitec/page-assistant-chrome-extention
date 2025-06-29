// DOM Elements
const openOptionsButton = document.getElementById('openOptions');
const activateAssistantButton = document.getElementById('activateAssistant');
const statusDiv = document.getElementById('status');

// Open options page
openOptionsButton.addEventListener('click', () => {
    chrome.runtime.openOptionsPage();
});

// Activate the assistant on the current page
activateAssistantButton.addEventListener('click', () => {
    // Get the current active tab
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
        if (tabs[0]) {
            // Send a message to the content script to activate the assistant
            chrome.tabs.sendMessage(tabs[0].id, { action: 'activate_assistant' });
            
            // Close the popup
            window.close();
        }
    });
});

// Check if API key is configured
chrome.storage.sync.get(['provider', 'openaiApiKey', 'geminiApiKey', 'model'], (result) => {
    let provider = result.provider || 'openai';
    let apiKeyConfigured = false;
    if (provider === 'openai') {
        apiKeyConfigured = !!result.openaiApiKey;
    } else if (provider === 'gemini') {
        apiKeyConfigured = !!result.geminiApiKey;
    }
    if (!apiKeyConfigured) {
        statusDiv.innerHTML = `
            <span class="status-icon"><i class="fas fa-exclamation-triangle"></i></span>
            ${provider === 'openai' ? 'OpenAI' : 'Gemini'} API key not configured
        `;
        statusDiv.classList.add('not-configured');
    } else {
        let modelText = result.model ? `Using: ${provider} / ${result.model}` : 'Model not selected';
        statusDiv.innerHTML = `
            <span class="status-icon configured"><i class="fas fa-check-circle"></i></span>
            <span class="status-text-main">Ready to analyze pages</span>
            <span class="status-text-secondary">${modelText}</span>
        `;
    }
});
