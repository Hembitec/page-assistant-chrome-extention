# 🚀 Complete Chrome Extension API Guide for Essenca

This guide provides a full-fledged, tutorial-style blueprint for building a Chrome extension that interacts with the Essenca API. It covers everything from setup and authentication to implementing all major API features.

---

## 📂 **Project Structure**

For a clean and maintainable extension, we recommend structuring your scripts as follows:

```
/scripts
  ├── auth.js      # Handles login, logout, token refresh
  ├── api.js       # Manages all API calls to your backend
  └── background.js  # Background script for the extension
```

---

## ⚙️ **Part 1: Core Authentication (`/scripts/auth.js`)**

This module handles all aspects of user authentication.

### **1.1 Setup & Configuration**

First, define your Supabase credentials. **Never hardcode these in production.** Use your extension's build process to inject them as environment variables.

```javascript
// /scripts/auth.js

// IMPORTANT: Replace with your actual Supabase details
const SUPABASE_URL = 'https://dnngormeluqzeiwjzyhm.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRubmdvcm1lbHVxemVpd2p6eWhtIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMyNzY1NjksImV4cCI6MjA2ODg1MjU2OX0.Q2dUR5ojNxq6vfqKNlNKb3byX4ODypgQ44IMDQa-BGs';
const API_BASE_URL = 'http://localhost:3000'; // Or your production URL
```

### **1.2 Login, Logout, and Session Management**

```javascript
// /scripts/auth.js (continued)

// Logs the user in and stores the session
async function loginUser(email, password) {
  const response = await fetch(`${API_BASE_URL}/api/essenca/v1/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  if (!response.ok) throw new Error('Login failed');
  const data = await response.json();
  await chrome.storage.local.set({ 'essenca_session': data });
  return data;
}

// Logs the user out by clearing the session
async function logoutUser() {
  await chrome.storage.local.remove('essenca_session');
}

// Retrieves the current session from storage
async function getSession() {
  const { essenca_session } = await chrome.storage.local.get('essenca_session');
  return essenca_session;
}
```

### **1.3 Automatic Token Refresh**

This is the most critical part. We create a function to get a valid token, refreshing it automatically if it's expired.

```javascript
// /scripts/auth.js (continued)

// Refreshes the access token using the refresh token
async function refreshAccessToken() {
  const session = await getSession();
  if (!session?.refresh_token) throw new Error('No refresh token available.');

  const response = await fetch(`${SUPABASE_URL}/auth/v1/token?grant_type=refresh_token`, {
    method: 'POST',
    headers: {
      'apikey': SUPABASE_ANON_KEY,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ refresh_token: session.refresh_token })
  });

  if (!response.ok) {
    await logoutUser(); // If refresh fails, log the user out
    throw new Error('Session expired. Please log in again.');
  }

  const data = await response.json();
  // Update the session with the new token data
  await chrome.storage.local.set({ 'essenca_session': { ...session, ...data } });
  return data.access_token;
}

// Gets a valid access token, refreshing if necessary
async function getValidAccessToken() {
  const session = await getSession();
  if (!session) throw new Error('User not authenticated.');

  // Check if token expires in the next 60 seconds
  const isExpired = (session.expires_at * 1000) - Date.now() < 60000;
  if (isExpired) {
    return await refreshAccessToken();
  }
  return session.access_token;
}
```

---

## 📞 **Part 2: API Service Module (`/scripts/api.js`)**

This module will contain functions for every API endpoint. It uses a generic request handler to keep the code DRY (Don't Repeat Yourself).

### **2.1 Generic API Request Handler**

This function handles fetching a valid token, setting headers, and making the request.

```javascript
// /scripts/api.js

// Note: You would need to import getValidAccessToken from './auth.js'
// This is a conceptual guide; for actual extensions, use module bundling.

async function apiRequest(endpoint, method = 'GET', body = null) {
  const accessToken = await getValidAccessToken();
  const headers = {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${accessToken}`
  };

  const config = {
    method,
    headers
  };

  if (body) {
    config.body = JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, config);

  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.error || `API request to ${endpoint} failed`);
  }

  // For GET requests with no content, response.json() can fail
  if (response.status === 204 || response.headers.get('content-length') === '0') {
      return null;
  }
  
  return response.json();
}
```

### **2.2 Implementing All API Endpoints**

Now, create a clean function for each endpoint.

```javascript
// /scripts/api.js (continued)

// --- Public Endpoints ---
export const register = (email, password, username) => 
  apiRequest('/api/essenca/v1/register', 'POST', { email, password, username });

// --- Authenticated User Endpoints ---
export const getMyProfile = () => apiRequest('/api/essenca/v1/user/me');
export const getMyBalance = () => apiRequest('/api/essenca/v1/balance');
export const getMyActivity = () => apiRequest('/api/essenca/v1/user/activity');

export const changePassword = (current_password, new_password) => 
  apiRequest('/api/essenca/v1/user/change-password', 'POST', { current_password, new_password });

export const changeUsername = (password, new_username) => 
  apiRequest('/api/essenca/v1/user/change-username', 'POST', { password, new_username });

// --- Core AI Endpoint ---
export const processContent = (action, content, options = {}) => 
  apiRequest('/api/essenca/v1/process', 'POST', { action, content, ...options });

// --- Admin Endpoints ---
export const getAllUsers = () => apiRequest('/api/admin/users');
export const createAdminUser = (email, password, username) => 
  apiRequest('/api/admin/users/create', 'POST', { email, password, username });
```

---

## 🖥️ **Part 3: Example Usage in Extension UI**

Here’s how you would use these functions in your extension's UI logic (e.g., in a popup script).

```javascript
// /popup/script.js

document.getElementById('login-button').addEventListener('click', async () => {
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  try {
    await loginUser(email, password);
    console.log('Login successful!');
    // Update UI to show logged-in state
  } catch (error) {
    console.error(error.message);
    // Show error in UI
  }
});

document.getElementById('process-button').addEventListener('click', async () => {
  try {
    const response = await processContent('summarize', 'This is a long text to summarize...');
    console.log('AI Response:', response);
    // Display result in UI
  } catch (error) {
    console.error(error.message);
    // Handle error, maybe prompt for re-login
  }
});
```

This comprehensive guide provides a solid foundation for any developer to successfully build a robust and secure Chrome extension using the Essenca API.
