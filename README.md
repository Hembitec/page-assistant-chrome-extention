# Page Assistant Chrome Extension

A professional AI-powered chatbot that helps you understand any webpage with summaries, key insights, and a specialized LinkedIn comment generation feature.

## Table of Contents
- [Features](#features)
- [How It Works](#how-it-works)
- [Setup and Configuration](#setup-and-configuration)
- [Permissions Used](#permissions-used)
- [Architecture Overview](#architecture-overview)

## Features

The Page Assistant extension provides a suite of AI-powered tools to enhance your browsing experience:

1.  **Webpage Summarization:**
    *   Get concise, bullet-point summaries of any webpage content.
    *   Highlights main topics, key arguments, important facts, statistics, and conclusions.

2.  **Key Takeaway Extraction:**
    *   Quickly identify the single most important insight or core message from a webpage (1-2 sentences).

3.  **Contextual Chatbot:**
    *   Engage in a conversational chat with an AI assistant about the content of the page you are viewing.
    *   The AI uses the page content to provide accurate and relevant responses. If the answer isn't in the content, it acknowledges this and can provide general knowledge.

4.  **LinkedIn Comment Generation (Specialized Feature):**
    *   **AI-Powered Comments:** Automatically generates professional, insightful, and engaging comments for LinkedIn posts.
    *   **Contextual & Non-Generic:** Comments are tailored to the post content, aiming to be conversational starters and add value to the discussion.
    *   **Persona-Based Generation:** You can define a user profile in the extension's settings. The AI will adopt this persona when generating comments, ensuring they align with your personal or professional brand.
    *   **Seamless Integration:** A custom "Generate AI Comment" button is injected directly into the LinkedIn comment box for easy access.

5.  **Configurable AI Backend:**
    *   Supports both **OpenAI** and **Google Gemini** models.
    *   Allows you to select your preferred AI model (e.g., `gpt-3.5-turbo`).
    *   Requires you to provide your own API key for the selected provider.

## How It Works

The extension operates through a combination of a background service worker, content scripts, and a user-friendly interface.

1.  **Content Scripts:**
    *   A general content script (`content.js`) is injected into all webpages to enable the summarization, key takeaway, and chat features.
    *   A specialized content script (`linkedin feature/content.js`) is injected *only* on LinkedIn pages to provide the AI comment generation functionality.

2.  **Background Service Worker (`background.js`):**
    *   This is the core of the extension. It listens for requests from the content scripts.
    *   It securely retrieves your API key and other settings from `chrome.storage`.
    *   It constructs the appropriate API request based on the action (e.g., summary, chat, LinkedIn comment) and sends it to the selected AI provider (OpenAI or Gemini).
    *   It then sends the AI's response back to the content script to be displayed to the user.

3.  **User Interface:**
    *   **Popup (`popup.html`):** The main interface for interacting with the summarization, key takeaway, and chat features, accessible by clicking the extension icon in the browser toolbar.
    *   **Options Page (`settings/options.html`):** A dedicated page for configuring the extension, including selecting the AI provider, entering API keys, choosing a model, and setting up your LinkedIn persona.

## Setup and Configuration

To use the Page Assistant extension, you need to configure your AI provider and API key:

1.  Right-click the extension icon in your browser toolbar and select "Options".
2.  On the options page, choose your preferred AI provider (OpenAI or Gemini).
3.  Enter your API key for the selected provider.
4.  (Optional) Select a specific model from the dropdown.
5.  (Optional) For the LinkedIn feature, fill in the "User Profile Information" text area to enable persona-based comment generation.
6.  Click "Save Settings".

## Permissions Used

The extension requires the following permissions to function:

*   `activeTab`: To access the content of the currently active webpage when you invoke the extension.
*   `storage`: To securely store your settings (API keys, provider, model, user profile).
*   `tabs`: To get information about tabs, which helps in managing content script injection.
*   `host_permissions`: To make API calls to `https://api.openai.com/*` and potentially other services.

## Architecture Overview

The extension follows a standard Manifest V3 architecture:

*   **`manifest.json`**: Defines the extension's metadata, permissions, and entry points.
*   **`background.js`**: The service worker that handles all background processing and API communication.
*   **`content.js`**: The main content script for interacting with general webpages.
*   **`linkedin feature/content.js`**: A specialized content script for the LinkedIn comment feature.
*   **`popup.html` / `popup.js`**: The UI and logic for the browser action popup.
*   **`settings/`**: Contains the HTML, CSS, and JavaScript for the options page.
*   **`icons/`**: Contains the extension's icons for the toolbar, settings, etc.
