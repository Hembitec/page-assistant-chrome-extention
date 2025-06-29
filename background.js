// OpenAI API endpoint
const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';

// Gemini API endpoint base
const GEMINI_API_URL_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

// System prompts for different actions
const SYSTEM_PROMPTS = {
    summary: `Read the following page content and create a concise bullet-point summary that captures:
• Main topic and key arguments
• Important facts and statistics
• Key takeaways and conclusions
Format the response as clear, easy-to-read bullet points.`,

    'key-takeaway': `Read the following page content and identify the single most important takeaway or insight.
Focus on the core message or conclusion that the reader should remember.
Provide a concise, impactful response (1-2 sentences) that captures the essence of the content.`,

    chat: `You are a helpful AI assistant that can answer questions about the page the user is viewing.
Use the page content to provide accurate, relevant responses.
If the answer isn't in the content, acknowledge this and provide general knowledge if appropriate.
Keep responses concise, informative, and conversational.`,

    'generate_linkedin_comment': `You are a LinkedIn user. Your persona is defined by the "USER PROFILE" provided below.
Adopt this persona and write a comment AS THIS PERSON.
Your response must ONLY be the comment text itself. Do not add any introductory phrases like "Here's a comment for Sarah:".
Based on your persona and the "POST CONTENT", generate a professional, insightful, conversational starter and engaging comment based on
the type of post or what the post is about so it wont be generic.
Always use simple words so it will be easy for any one to understand.
The comment should add value to the discussion. It can be a thoughtful question, a supplementary insight, or an appreciative remark.
Always know that not all post comment need question and the ones that requires it the questionstion should be a unique and conversation stater ones.
Keep the tone positive. Do not include hashtags and emojis unless the emoji is highly relevant.
The generated comment should be concise and ready to be posted directly.`,

    'generate_linkedin_comment_generic': `You are a professional social media manager helping a user write a comment on a LinkedIn post.
Based on the post content provided, generate a professional, insightful, conversational starter and engaging comment based on
the type of post or what the post is about so it wont be generic.
Always use simple words so it will be easy for any one to understand.
The comment should add value to the discussion. It can be a thoughtful question, a supplementary insight, or an appreciative remark.
Always know that not all post comment need question and the ones that requires it the questionstion should be a unique and conversation stater ones.
Keep the tone positive. Do not include hashtags and emojis unless the emoji is highly relevant.
The generated comment should be concise and ready to be posted directly.`
};

// Handle messages from content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    // Handle different action types
    switch (request.action) {
        case 'summary':
            processRequest(request, SYSTEM_PROMPTS.summary, sendResponse);
            break;

        case 'key-takeaway':
            processRequest(request, SYSTEM_PROMPTS['key-takeaway'], sendResponse);
            break;

        case 'chat':
            processChatRequest(request, sendResponse);
            break;

        case 'generate_linkedin_comment':
            processRequest(request, SYSTEM_PROMPTS['generate_linkedin_comment'], sendResponse);
            break;

        default:
            sendResponse({
                success: false,
                error: `Unknown action: ${request.action}`
            });
    }

    return true; // Will respond asynchronously
});

// Process standard requests (summary, key takeaway)
async function processRequest(request, systemPrompt, sendResponse) {
    try {
        let finalSystemPrompt = systemPrompt;

        // If the action is for LinkedIn, decide which prompt to use
        if (request.action === 'generate_linkedin_comment') {
            const settings = await getSettings();
            if (settings.userProfileInfo) {
                finalSystemPrompt = SYSTEM_PROMPTS['generate_linkedin_comment'];
            } else {
                finalSystemPrompt = SYSTEM_PROMPTS['generate_linkedin_comment_generic'];
            }
        }

        const result = await makeAIRequest({
            content: request.content,
            systemPrompt: finalSystemPrompt,
            history: request.history || []
        });
        sendResponse({ success: true, result });
    } catch (error) {
        console.error('Request processing error:', error);
        sendResponse({ success: false, error: error.message });
    }
}

// Process chat requests
async function processChatRequest(request, sendResponse) {
    try {
        // For chat, we include the user's message in the messages array
        const history = request.history || [];
        const result = await makeAIRequest({
            content: request.content,
            systemPrompt: SYSTEM_PROMPTS.chat,
            history,
            userMessage: request.message
        });
        sendResponse({ success: true, result });
    } catch (error) {
        console.error('Chat processing error:', error);
        sendResponse({ success: false, error: error.message });
    }
}

// Get settings from storage
async function getSettings() {
    return new Promise((resolve) => {
        chrome.storage.sync.get(
            {
                provider: 'openai',
                openaiApiKey: '',
                geminiApiKey: '',
                model: 'gpt-3.5-turbo',
                systemPrompt: '',
                userProfileInfo: ''
            },
            (items) => resolve(items)
        );
    });
}

// Main AI request dispatcher
async function makeAIRequest({ content, systemPrompt, history = [], userMessage = null }) {
    const settings = await getSettings();
    if (settings.provider === 'openai') {
        if (!settings.openaiApiKey) {
            throw new Error('OpenAI API key not configured. Please set it in the extension options.');
        }

        let finalContent = content;
        // Check if this is a LinkedIn comment generation and if user profile info exists
        if (systemPrompt === SYSTEM_PROMPTS['generate_linkedin_comment'] && settings.userProfileInfo) {
            finalContent = `USER PROFILE:\n${settings.userProfileInfo}\n\nPOST CONTENT:\n${content}`;
        }

        // Construct messages array
        const messages = [
            { role: 'system', content: systemPrompt || settings.systemPrompt },
            ...history,
            { role: 'user', content: userMessage ? userMessage : finalContent }
        ];
        return makeOpenAIRequestWithMessages(messages, settings);
    } else if (settings.provider === 'gemini') {
        if (!settings.geminiApiKey) {
            throw new Error('Gemini API key not configured. Please set it in the extension options.');
        }
        // Gemini expects a different format
        // Compose the prompt
        let prompt = '';
        if (systemPrompt || settings.systemPrompt) {
            prompt += (systemPrompt || settings.systemPrompt) + '\n';
        }
        if (content) {
            prompt += 'Page content:\n' + content + '\n';
        }
        if (userMessage) {
            prompt += userMessage;
        }
        // Compose Gemini request
        const url = `${GEMINI_API_URL_BASE}${settings.model}:generateContent?key=${settings.geminiApiKey}`;
        const body = JSON.stringify({
            contents: [
                {
                    parts: [
                        { text: prompt.trim() }
                    ]
                }
            ]
        });
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body
            });
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error?.message || 'Failed to get response from Gemini');
            }
            const data = await response.json();
            // Gemini's response: data.candidates[0].content.parts[0].text
            if (data.candidates && data.candidates[0] && data.candidates[0].content && data.candidates[0].content.parts && data.candidates[0].content.parts[0].text) {
                return data.candidates[0].content.parts[0].text;
            } else {
                throw new Error('Unexpected Gemini response format');
            }
        } catch (error) {
            console.error('Gemini API Error:', error);
            throw new Error(`Failed to process request: ${error.message}`);
        }
    } else {
        throw new Error('Unknown provider selected.');
    }
}

// Make API call to OpenAI with standard format
async function makeOpenAIRequest(content, systemPrompt, history = []) {
    const settings = await getSettings();

    if (!settings.openaiApiKey) {
        throw new Error('OpenAI API key not configured. Please set it in the extension options.');
    }

    // Construct messages array
    const messages = [
        { role: 'system', content: systemPrompt || settings.systemPrompt },
        // Add conversation history if available
        ...history,
        // Add the page content as user message
        { role: 'user', content: "Page content:\n\n" + content }
    ];

    return makeOpenAIRequestWithMessages(messages, settings);
}

// Make API call to OpenAI with custom messages
async function makeOpenAIRequestWithMessages(messages, settings = null) {
    if (!settings) {
        settings = await getSettings();
    }

    if (!settings.openaiApiKey) {
        throw new Error('OpenAI API key not configured. Please set it in the extension options.');
    }

    try {
        const response = await fetch(OPENAI_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${settings.openaiApiKey}`
            },
            body: JSON.stringify({
                model: settings.model,
                messages: messages,
                temperature: 0.7,
                max_tokens: 800
            })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error?.message || 'Failed to get response from OpenAI');
        }

        const data = await response.json();
        return data.choices[0].message.content;
    } catch (error) {
        console.error('OpenAI API Error:', error);
        throw new Error(`Failed to process request: ${error.message}`);
    }
}
