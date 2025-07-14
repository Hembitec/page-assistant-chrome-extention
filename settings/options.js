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
const userProfileInfoTextarea = document.getElementById('userProfileInfo');
const userProfileFileInput = document.getElementById('userProfileFile');
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
const essencaApiGroup = document.getElementById('essenca-api-group');

// Auth Modal Elements
const authModal = document.getElementById('auth-modal');
const loginBtn = document.getElementById('login-btn');
const closeModalBtn = document.querySelector('.close-button');
const showRegisterLink = document.getElementById('show-register');
const showLoginLink = document.getElementById('show-login');
const loginView = document.getElementById('login-view');
const registerView = document.getElementById('register-view');
const submitLoginBtn = document.getElementById('submit-login');
const submitRegisterBtn = document.getElementById('submit-register');
const logoutBtn = document.getElementById('logout-btn');
const authStatusDiv = document.getElementById('auth-status');

// API URL
const API_BASE_URL = 'https://insightlens.42web.io/wp-json/essenca/v1';

// Load saved settings
function loadSettings() {
    chrome.storage.local.get(['jwtToken', 'userEmail'], (localData) => {
        if (localData.jwtToken) {
            updateAuthUI(true, localData.userEmail);
            fetchTokenBalance(localData.jwtToken);
        } else {
            updateAuthUI(false);
        }
    });

    chrome.storage.sync.get(
        {
            provider: 'essenca_api',
            openaiApiKey: '',
            geminiApiKey: '',
            model: 'gemini-2.5-flash',
            systemPrompt: '',
            userProfileInfo: ''
        },
        (items) => {
            providerSelect.value = items.provider;
            openaiApiKeyInput.value = items.openaiApiKey;
            geminiApiKeyInput.value = items.geminiApiKey;
            modelInput.value = items.model;
            systemPromptTextarea.value = items.systemPrompt;
            userProfileInfoTextarea.value = items.userProfileInfo;
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
    const userProfileInfo = userProfileInfoTextarea.value.trim();

    if (provider === 'openai' && !openaiApiKey) {
        showStatus('Error: OpenAI API key is required.', 'error');
        return;
    }
    if (provider === 'gemini' && !geminiApiKey) {
        showStatus('Error: Gemini API key is required.', 'error');
        return;
    }
    if (provider !== 'essenca_api' && !model) {
        showStatus('Error: Model name cannot be empty', 'error');
        return;
    }

    chrome.storage.sync.set(
        {
            provider: provider,
            openaiApiKey: openaiApiKey,
            geminiApiKey: geminiApiKey,
            model: model,
            systemPrompt: systemPrompt,
            userProfileInfo: userProfileInfo
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
    userProfileInfoTextarea.value = '';
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
document.addEventListener('DOMContentLoaded', () => {
    loadSettings();

    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');

    tabLinks.forEach(link => {
        link.addEventListener('click', () => {
            const tab = link.dataset.tab;

            tabLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            tabContents.forEach(content => {
                if (content.id === tab) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
        });
    });

    const toggleApiKeys = document.querySelectorAll('.toggle-api-key');
    toggleApiKeys.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const input = toggle.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        });
    });
});
saveButton.addEventListener('click', saveSettings);
resetButton.addEventListener('click', resetSettings);
userProfileFileInput.addEventListener('change', handleFileSelect);

// Handle model option selection
Array.from(document.querySelectorAll('.model-option')).forEach(option => {
    option.addEventListener('click', () => {
        const selectedModel = option.dataset.model;
        modelInput.value = selectedModel;
        updateModelOptionSelection(selectedModel);
    });
});

function updateProviderUI(provider) {
    openaiApiGroup.style.display = 'none';
    geminiApiGroup.style.display = 'none';
    essencaApiGroup.style.display = 'none';
    modelInput.parentElement.style.display = 'block';
    modelOptionsOpenAI.style.display = 'none';
    modelOptionsGemini.style.display = 'none';
    modelHintOpenAI.style.display = 'none';
    modelHintGemini.style.display = 'none';


    if (provider === 'openai') {
        openaiApiGroup.style.display = 'block';
        modelOptionsOpenAI.style.display = 'flex';
        modelHintOpenAI.style.display = 'block';
        modelInput.placeholder = 'gpt-3.5-turbo';
    } else if (provider === 'gemini') {
        geminiApiGroup.style.display = 'block';
        modelOptionsGemini.style.display = 'flex';
        modelHintGemini.style.display = 'block';
        modelInput.placeholder = 'gemini-pro';
    } else if (provider === 'essenca_api') {
        essencaApiGroup.style.display = 'block';
        modelInput.parentElement.style.display = 'none'; // Hide model selection
    }
}

providerSelect.addEventListener('change', function () {
    updateProviderUI(this.value);
});

// --- Auth Modal Logic ---
loginBtn.addEventListener('click', () => authModal.style.display = 'flex');
closeModalBtn.addEventListener('click', () => authModal.style.display = 'none');
showRegisterLink.addEventListener('click', (e) => {
    e.preventDefault();
    loginView.style.display = 'none';
    registerView.style.display = 'block';
});
showLoginLink.addEventListener('click', (e) => {
    e.preventDefault();
    registerView.style.display = 'none';
    loginView.style.display = 'block';
});
window.addEventListener('click', (e) => {
    if (e.target === authModal) {
        authModal.style.display = 'none';
    }
});

// --- Authentication Functions ---
submitRegisterBtn.addEventListener('click', async () => {
    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const pass = document.getElementById('register-password').value;

    try {
        const response = await fetch(`${API_BASE_URL}/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password: pass })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Registration failed.');

        showAuthStatus('Registration successful! Please log in.', 'success');
        showLoginLink.click();
    } catch (error) {
        showAuthStatus(error.message, 'error');
    }
});

submitLoginBtn.addEventListener('click', async () => {
    const username = document.getElementById('login-username').value;
    const pass = document.getElementById('login-password').value;

    try {
        const response = await fetch(`${API_BASE_URL}/token`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password: pass })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Login failed.');

        chrome.storage.local.set({ jwtToken: data.token, userEmail: data.user_email }, () => {
            updateAuthUI(true, data.user_email);
            fetchTokenBalance(data.token);
            authModal.style.display = 'none';
            showStatus('Logged in successfully!', 'success');
        });
    } catch (error) {
        showAuthStatus(error.message, 'error');
    }
});

logoutBtn.addEventListener('click', () => {
    chrome.storage.local.remove(['jwtToken', 'userEmail'], () => {
        updateAuthUI(false);
        showStatus('Logged out successfully.', 'success');
    });
});

function updateAuthUI(isLoggedIn, email = '') {
    const loggedInView = document.getElementById('auth-logged-in');
    const loggedOutView = document.getElementById('auth-logged-out');
    if (isLoggedIn) {
        loggedInView.style.display = 'block';
        loggedOutView.style.display = 'none';
        document.getElementById('user-email').textContent = email;
    } else {
        loggedInView.style.display = 'none';
        loggedOutView.style.display = 'block';
        document.getElementById('user-email').textContent = '';
        document.getElementById('token-balance').textContent = 'N/A';
    }
}

async function fetchTokenBalance(token) {
    try {
        const response = await fetch(`${API_BASE_URL}/balance`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not fetch balance.');

        document.getElementById('token-balance').textContent = data.balance;
    } catch (error) {
        console.error('Error fetching token balance:', error);
        document.getElementById('token-balance').textContent = 'Error';
    }
}

function showAuthStatus(message, type) {
    authStatusDiv.textContent = message;
    authStatusDiv.style.color = type === 'error' ? 'red' : 'green';
}


// Handle file upload for personalization
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }
    // Allow both .txt and .md files. Note: MIME type for .md can be inconsistent.
    // A simple check for file extension is more reliable here.
    if (!file.name.endsWith('.txt') && !file.name.endsWith('.md')) {
        showStatus('Error: Please upload a .txt or .md file.', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        userProfileInfoTextarea.value = e.target.result;
        showStatus(`Successfully loaded ${file.name}. Click Save to apply changes.`, 'success');
    };
    reader.onerror = function () {
        showStatus(`Error reading file: ${reader.error}`, 'error');
    };
    reader.readAsText(file);
}
