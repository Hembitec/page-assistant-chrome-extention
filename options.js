// Default system prompt
const DEFAULT_SYSTEM_PROMPT = `Read the following page content and create a concise bullet-point summary that captures:
• Main topic and key arguments
• Important facts and statistics
• Key takeaways and conclusions
Format the response as clear, easy-to-read bullet points.`;

// DOM Elements
const apiKeyInput = document.getElementById('apiKey');
const modelInput = document.getElementById('model');
const systemPromptTextarea = document.getElementById('systemPrompt');
const saveButton = document.getElementById('save');
const resetButton = document.getElementById('reset');
const statusDiv = document.getElementById('status');
const modelOptions = document.querySelectorAll('.model-option');
const providerSelect = document.getElementById('provider');
const openaiApiKeyInput = document.getElementById('openaiApiKey');
const geminiApiKeyInput = document.getElementById('geminiApiKey');
const modelOptionsOpenAI = document.getElementById('modelOptionsOpenAI');
const modelOptionsGemini = document.getElementById('modelOptionsGemini');
const modelHintOpenAI = document.getElementById('modelHintOpenAI');
const modelHintGemini = document.getElementById('modelHintGemini');
const openaiApiGroup = document.getElementById('openai-api-group');
const geminiApiGroup = document.getElementById('gemini-api-group');

// Load saved settings
function loadSettings() {
    chrome.storage.sync.get(
        {
            provider: 'openai',
            openaiApiKey: '',
            geminiApiKey: '',
            model: 'gpt-3.5-turbo',
            systemPrompt: ''
        },
        (items) => {
            providerSelect.value = items.provider;
            openaiApiKeyInput.value = items.openaiApiKey;
            geminiApiKeyInput.value = items.geminiApiKey;
            modelInput.value = items.model;
            systemPromptTextarea.value = items.systemPrompt;
            updateProviderUI(items.provider);
            updateModelOptionSelection(items.model);
        }
    );
}

// Update model option selection
function updateModelOptionSelection(selectedModel) {
    let options = providerSelect.value === 'openai' ? modelOptionsOpenAI : modelOptionsGemini;
    Array.from(options.querySelectorAll('.model-option')).forEach(option => {
        if (option.dataset.model === selectedModel) {
            option.classList.add('selected');
        } else {
            option.classList.remove('selected');
        }
    });
}

// Save settings
function saveSettings() {
    const provider = providerSelect.value;
    const openaiApiKey = openaiApiKeyInput.value.trim();
    const geminiApiKey = geminiApiKeyInput.value.trim();
    const model = modelInput.value.trim();
    const systemPrompt = systemPromptTextarea.value.trim();

    if (provider === 'openai' && openaiApiKey && !openaiApiKey.startsWith('sk-')) {
        showStatus('Error: Invalid OpenAI API key format. It should start with "sk-"', 'error');
        return;
    }
    if (provider === 'gemini' && geminiApiKey && geminiApiKey.length < 10) {
        showStatus('Error: Invalid Gemini API key format.', 'error');
        return;
    }
    if (!model) {
        showStatus('Error: Model name cannot be empty', 'error');
        return;
    }
    chrome.storage.sync.set(
        {
            provider: provider,
            openaiApiKey: openaiApiKey,
            geminiApiKey: geminiApiKey,
            model: model,
            systemPrompt: systemPrompt
        },
        () => {
            if (chrome.runtime.lastError) {
                showStatus('Error saving settings: ' + chrome.runtime.lastError.message, 'error');
            } else {
                showStatus('Settings saved successfully!', 'success');
            }
        }
    );
}

// Reset settings to defaults
function resetSettings() {
    providerSelect.value = 'openai';
    openaiApiKeyInput.value = '';
    geminiApiKeyInput.value = '';
    modelInput.value = 'gpt-3.5-turbo';
    systemPromptTextarea.value = '';
    updateProviderUI('openai');
    updateModelOptionSelection('gpt-3.5-turbo');
    showStatus('Settings reset to defaults. Click Save to apply changes.', 'success');
}

// Show status message
function showStatus(message, type) {
    statusDiv.textContent = message;
    statusDiv.className = type;
    statusDiv.style.display = 'block';

    // Hide status after 5 seconds
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', loadSettings);
saveButton.addEventListener('click', saveSettings);
resetButton.addEventListener('click', resetSettings);

// Handle model option selection
Array.from(document.querySelectorAll('.model-option')).forEach(option => {
    option.addEventListener('click', () => {
        const selectedModel = option.dataset.model;
        modelInput.value = selectedModel;
        updateModelOptionSelection(selectedModel);
    });
});

function updateProviderUI(provider) {
    if (provider === 'openai') {
        openaiApiGroup.style.display = '';
        geminiApiGroup.style.display = 'none';
        modelOptionsOpenAI.style.display = '';
        modelOptionsGemini.style.display = 'none';
        modelHintOpenAI.style.display = '';
        modelHintGemini.style.display = 'none';
        modelInput.placeholder = 'gpt-3.5-turbo';
    } else {
        openaiApiGroup.style.display = 'none';
        geminiApiGroup.style.display = '';
        modelOptionsOpenAI.style.display = 'none';
        modelOptionsGemini.style.display = '';
        modelHintOpenAI.style.display = 'none';
        modelHintGemini.style.display = '';
        modelInput.placeholder = 'gemini-2.0-flash';
    }
}

providerSelect.addEventListener('change', function() {
    updateProviderUI(this.value);
});
