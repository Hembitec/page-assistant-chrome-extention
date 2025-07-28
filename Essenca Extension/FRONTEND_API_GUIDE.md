# Frontend Development & API Guide for Page Assistant

## 1. Project Vision & Application Purpose

The goal is to build a modern, fast, and user-friendly web application using Next.js that serves as the public face of the Page Assistant.

**Core Objectives:**
*   **Attract New Users**: The landing page should clearly explain the value of the Page Assistant and encourage visitors to sign up and install the Chrome extension.
*   **User Onboarding**: Provide a seamless sign-up and login experience.
*   **User Dashboard**: Offer a secure area for registered users to view their account status, token balance, and recent activity.

---

## 2. Landing Page Design & Content

The landing page is the most important marketing tool. It should be clean, professional, and persuasive.

**Key Sections:**
*   **Hero Section**:
    *   **Headline**: A powerful, benefit-oriented headline (e.g., "Your AI-Powered Assistant for Smarter Browsing").
    *   **Sub-headline**: A brief explanation of what the product does.
    *   **Call-to-Action (CTA)**: A prominent button (e.g., "Get Started for Free" or "Install Chrome Extension").
    
*   **Features Section**:
    *   Showcase the main features (Summary, Chat, LinkedIn Comments) with icons, short descriptions, and screenshots.
    *   Focus on the *benefits* for the user (e.g., "Save Time with Instant Summaries").
*   **How It Works Section**:
    *   A simple 3-step visual guide: 1. Install Extension, 2. Sign Up, 3. Activate on any page.
*   **CTA Setion**

---

## 3. User Dashboard Design

The user dashboard should be simple, clean, and functional. It is accessible only after a user logs in.

**Key Components:**
*   **Welcome Message**: A personalized greeting (e.g., "Welcome, [User's Name]!").
*   **Token Balance Display**:
    *   A prominent card or section that clearly displays the user's remaining tokens.
    *   Example: "You have **42** tokens remaining."
    *   Include a CTA to "Buy More Tokens" (for future implementation).
*   **Recent Activity**:
    *   A table or list showing the user's last 5-10 actions, fetched from the `/user/activity` endpoint.
    *   Columns: `Date`, `Action`, `Tokens Used`.
*   **Account Management Links**:
    *   Simple links to a page where users can change their password or manage their profile.

---

## 4. Recommended Next.js Project Structure

A well-organized project structure is crucial for scalability and maintainability.

```
/essenca-frontend
|
├── /pages
|   ├── /api
|   |   └── [...nextauth].js  // For NextAuth.js if used
|   ├── /dashboard
|   |   └── index.js        // The main user dashboard page
|   ├── _app.js
|   ├── _document.js
|   ├── index.js            // The landing page
|   ├── login.js
|   └── signup.js
|
├── /components
|   ├── /dashboard          // Components specific to the dashboard
|   |   ├── ActivityLog.js
|   |   └── TokenBalance.js
|   ├── /layout             // Main layout components
|   |   ├── Footer.js
|   |   └── Navbar.js
|   └── /ui                 // Reusable UI elements (buttons, cards, etc.)
|
├── /lib
|   └── api.js              // Central file for all API communication
|
├── /styles
|   └── globals.css
|
└── /public
    └── /images
```

---

## 5. API Integration & Endpoints

This section details how to connect the Next.js app to the WordPress backend.

**Base API URL**: All endpoints are prefixed with your WordPress site's REST URL. This can be found in the plugin's "API Settings" page.
Example: `https://your-wordpress-site.com/wp-json/essenca/v1`

---

## Authentication

Authentication is handled using JSON Web Tokens (JWT). The flow is as follows:

1.  **User Login**: The user submits their `username` and `password` to the `/token` endpoint.
2.  **Receive JWT**: If the credentials are valid, the API returns a JWT. This token must be stored securely on the client-side (e.g., in an HttpOnly cookie or secure local storage).
3.  **Authenticated Requests**: For all subsequent requests to protected endpoints, the JWT must be included in the `Authorization` header.
    *   **Format**: `Authorization: Bearer <YOUR_JWT_HERE>`

---

## API Endpoint Reference

### 1. User Registration

*   **Endpoint**: `/register`
*   **Method**: `POST`
*   **Description**: Creates a new user account.
*   **Body (JSON)**:
    ```json
    {
      "username": "newuser",
      "email": "user@example.com",
      "password": "a-strong-password"
    }
    ```
*   **Response**: A success or error message.

### 2. User Login (Get Token)

*   **Endpoint**: `/token`
*   **Method**: `POST`
*   **Description**: Authenticates a user and returns a JWT.
*   **Body (JSON)**:
    ```json
    {
      "username": "testuser",
      "password": "password123"
    }
    ```
*   **Success Response (200 OK)**:
    ```json
    {
      "token": "ey...",
      "user_email": "user@example.com",
      "user_display_name": "Test User"
    }
    ```

### 3. Get User Dashboard Data

*   **Endpoint**: `/user/me`
*   **Method**: `GET`
*   **Authentication**: Required (Bearer Token)
*   **Description**: Retrieves all essential data for the currently logged-in user. This is the primary endpoint for a user dashboard.
*   **Success Response (200 OK)**:
    ```json
    {
      "id": 1,
      "username": "testuser",
      "email": "user@example.com",
      "token_balance": 42
    }
    ```

### 4. Get User Activity

*   **Endpoint**: `/user/activity`
*   **Method**: `GET`
*   **Authentication**: Required (Bearer Token)
*   **Description**: Retrieves the last 50 activity logs for the authenticated user.
*   **Success Response (200 OK)**:
    ```json
    [
      {
        "time": "2025-07-12 17:00:00",
        "request_action": "summary",
        "tokens_used": "2"
      },
      {
        "time": "2025-07-12 16:58:00",
        "request_action": "chat",
        "tokens_used": "1"
      }
    ]
    ```

### 5. Process AI Request

*   **Endpoint**: `/process`
*   **Method**: `POST`
*   **Authentication**: Required (Bearer Token)
*   **Description**: The main endpoint for all AI actions.
*   **Body (JSON)**:
    ```json
    {
      "action": "summary", // e.g., 'summary', 'chat', 'generate_linkedin_comment'
      "content": "The full text content to be processed.",
      "message": "Optional: A specific user question for the 'chat' action.",
      "user_profile": "Optional: The user's persona for LinkedIn comments." 
    }
    ```
*   **Success Response (200 OK)**:
    ```json
    {
        "success": true,
        "result": "The AI-generated response text.",
        "tokens_remaining": 40
    }
    ```

---

## Important Notes

*   **Error Handling**: The API will return appropriate HTTP status codes (e.g., 401 for unauthorized, 400 for bad requests) and a JSON body with an error `message`. The frontend should be prepared to handle these errors gracefully.
*   **Token Expiration**: JWTs have an expiration date. If the API returns a 403 `invalid_token` error, the user should be prompted to log in again.
*   **CORS**: Ensure that your WordPress site is configured to accept requests from your Next.js application's domain. You may need to add a CORS (Cross-Origin Resource Sharing) plugin or configure your server if you encounter issues.

---

## 6. Account Management Endpoints

### 1. Change Password

*   **Endpoint**: `/user/change-password`
*   **Method**: `POST`
*   **Authentication**: Required (Bearer Token)
*   **Description**: Allows a logged-in user to change their password.
*   **Body (JSON)**:
    ```json
    {
      "current_password": "their-current-password",
      "new_password": "their-new-strong-password"
    }
    ```
*   **Success Response (200 OK)**:
    ```json
    {
      "success": true,
      "message": "Password changed successfully."
    }
    ```
*   **Error Response (403 Forbidden)**:
    ```json
    {
      "code": "wrong_password",
      "message": "The current password you entered is incorrect.",
      "data": { "status": 403 }
    }
    ```

### 2. Change Username

*   **Endpoint**: `/user/change-username`
*   **Method**: `POST`
*   **Authentication**: Required (Bearer Token)
*   **Description**: Allows a logged-in user to change their username. Requires the user to confirm their password.
*   **Body (JSON)**:
    ```json
    {
      "password": "their-current-password",
      "new_username": "their-new-username"
    }
    ```
*   **Success Response (200 OK)**:
    ```json
    {
      "success": true,
      "message": "Username changed successfully."
    }
    ```
*   **Error Responses**:
    *   `403 Forbidden` (`wrong_password`): If the provided password is incorrect.
    *   `409 Conflict` (`username_exists`): If the new username is already taken.
