// API endpoints
const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
const GEMINI_API_URL_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
const PAGE_ASSISTANT_API_URL = 'https://insightlens.42web.io/wp-json/hmb-page-assistant/v1';

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
The generated comment should be concise and ready to be posted directly.
ADDITIONAL RULES FOR COMMENT QUALITY
Avoid generic LinkedIn phrases and clichés. Make each line sound natural, specific, and personal.
When appropriate, include a personal reaction or small observation.
Ask questions only when they open a new dimension or insight — avoid yes/no or surface-level questions.
Keep the tone curious, warm, smart, and concise.
Comments should be 1 sentence and 2 short sentences if it is a post that required adding unique insight or points to post and center around a single idea.
Vary sentence structure to make the comment feel human-written.

Focus on One Clear Idea
Each comment should express only one central thought — a reaction, question, or micro-insight.
Avoid trying to say too much or cover multiple angles.

Vary Comment Type (Don’t Force a Question)
Not all comments need a question or conversation starter.
Only ask a question if it opens a deeper layer of the post’s message.
Skip questions when a supportive statement, subtle insight, or warm reaction is enough.

Use Tone That Matches the Post
The tone should adapt based on the post type:
Professional posts → clear, sharp, respectful
Personal posts → warm, thoughtful, validating
Thought-leadership posts → insightful, curious, sometimes challenging
Avoid being overly chatty or robotic — keep the tone flexible, human, and context-aware.
`,

    'generate_linkedin_comment_generic': `You are a professional social media manager helping a user write a comment on a LinkedIn post.
Based on the post content provided, generate a professional, insightful, conversational starter and engaging comment based on
the type of post or what the post is about so it wont be generic.
Always use simple words so it will be easy for any one to understand.
The comment should add value to the discussion. It can be a thoughtful question, a supplementary insight, or an appreciative remark.
Always know that not all post comment need question and the ones that requires it the questionstion should be a unique and conversation stater ones.
Keep the tone positive. Do not include hashtags and emojis unless the emoji is highly relevant.
The generated comment should be concise and ready to be posted directly.

ADDITIONAL RULES FOR COMMENT QUALITY
Avoid generic LinkedIn phrases and clichés. Make each line sound natural, specific, and personal.
When appropriate, include a personal reaction or small observation.
Ask questions only when they open a new dimension or insight — avoid yes/no or surface-level questions.
Keep the tone curious, warm, smart, and concise.
Comments should be 1 sentenct or 2 short sentences if needed to prove a point and center around a single idea.
Vary sentence structure to make the comment feel human-written.

Focus on One Clear Idea
Each comment should express only one central thought — a reaction, question, or micro-insight.
Avoid trying to say too much or cover multiple angles.

Vary Comment Type (Don’t Force a Question)
Not all comments need a question or conversation starter.
Only ask a question if it opens a deeper layer of the post’s message.
Skip questions when a supportive statement, subtle insight, or warm reaction is enough.

Use Tone That Matches the Post
The tone should adapt based on the post type:

Professional posts → clear, sharp, respectful

Personal posts → warm, thoughtful, validating

Thought-leadership posts → insightful, curious, sometimes challenging
Avoid being overly chatty or robotic — keep the tone flexible, human, and context-aware.
`
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
        const result = await makeAIRequest({
            action: request.action,
            content: request.content,
            systemPrompt: systemPrompt,
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
            action: request.action,
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
    const syncPromise = new Promise((resolve) => {
        chrome.storage.sync.get(
            {
                provider: 'page_assistant_api',
                openaiApiKey: '',
                geminiApiKey: '',
                model: 'gpt-3.5-turbo',
                systemPrompt: '',
                userProfileInfo: ''
            },
            (items) => resolve(items)
        );
    });

    const localPromise = new Promise((resolve) => {
        chrome.storage.local.get({ jwtToken: null }, (items) => resolve(items));
    });

    const [syncSettings, localSettings] = await Promise.all([syncPromise, localPromise]);
    return { ...syncSettings, ...localSettings };
}

// Main AI request dispatcher
async function makeAIRequest({ content, systemPrompt, history = [], userMessage = null, action }) {
    const settings = await getSettings();

    // 1. Determine the final system prompt, especially for LinkedIn comments
    let finalSystemPrompt = systemPrompt;
    if (action === 'generate_linkedin_comment') {
        if (settings.userProfileInfo) {
            finalSystemPrompt = SYSTEM_PROMPTS['generate_linkedin_comment'];
        } else {
            finalSystemPrompt = SYSTEM_PROMPTS['generate_linkedin_comment_generic'];
        }
    }

    // 2. Dispatch based on the selected provider
    if (settings.provider === 'page_assistant_api') {
        if (!settings.jwtToken) {
            throw new Error('You are not logged in. Please log in from the extension settings.');
        }

        const url = `${PAGE_ASSISTANT_API_URL}/process`;
        const body = {
            action,
            content,
            user_profile: settings.userProfileInfo || null,
            history: history || [],
            message: userMessage || ''
        };

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${settings.jwtToken}`
            },
            body: JSON.stringify(body)
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || `An error occurred with the Page Assistant API at ${url}.`);
        }
        return data.result;

    } else if (settings.provider === 'openai') {
        if (!settings.openaiApiKey) {
            throw new Error('OpenAI API key not configured. Please set it in the extension options.');
        }

        let finalContent = content;
        // Special content formatting for persona-based LinkedIn comments
        if (action === 'generate_linkedin_comment' && settings.userProfileInfo) {
            finalContent = `USER PROFILE:\n${settings.userProfileInfo}\n\nPOST CONTENT:\n${content}`;
        }

        const messages = [
            { role: 'system', content: finalSystemPrompt },
            ...history,
            { role: 'user', content: userMessage ? userMessage : finalContent }
        ];
        return makeOpenAIRequestWithMessages(messages, settings);

    } else if (settings.provider === 'gemini') {
        if (!settings.geminiApiKey) {
            throw new Error('Gemini API key not configured. Please set it in the extension options.');
        }

        let finalContent = `Page content:\n${content}`;
        // Special content formatting for persona-based LinkedIn comments
        if (action === 'generate_linkedin_comment' && settings.userProfileInfo) {
            finalContent = `USER PROFILE:\n${settings.userProfileInfo}\n\nPOST CONTENT:\n${content}`;
        }

        let prompt = `${finalSystemPrompt}\n\n${finalContent}`;
        if (userMessage) {
            prompt += `\n\nUser question: ${userMessage}`;
        }

        const url = `${GEMINI_API_URL_BASE}${settings.model}:generateContent?key=${settings.geminiApiKey}`;
        const body = JSON.stringify({ contents: [{ parts: [{ text: prompt.trim() }] }] });
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error?.message || 'Failed to get response from Gemini');
        }
        if (data.candidates?.[0]?.content?.parts?.[0]?.text) {
            return data.candidates[0].content.parts[0].text;
        } else {
            throw new Error('Unexpected Gemini response format');
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
