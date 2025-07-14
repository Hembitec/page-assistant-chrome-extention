<?php

class Essenca_Gemini_Api {

    public static function make_request($action, $content, $message, $history, $user_profile = null) {
        $api_key = get_option('essenca_gemini_api_key');
        if (empty($api_key)) {
            throw new Exception('Gemini API key not configured in the plugin settings.');
        }

        $full_prompts = self::get_prompts();
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;
        $body_data = [];

        if ($action === 'generate_linkedin_comment') {
            // Use the structured format for LinkedIn comments for better reliability
            $system_prompt_text = !empty($user_profile) ? $full_prompts['generate_linkedin_comment'] : $full_prompts['generate_linkedin_comment_generic'];
            $user_content_text = !empty($user_profile) ? "USER PROFILE:\n" . $user_profile . "\n\nPOST CONTENT:\n" . $content : "POST CONTENT:\n" . $content;

            $body_data = [
                'contents' => [['role' => 'user', 'parts' => [['text' => $user_content_text]]]],
                'systemInstruction' => ['parts' => [['text' => $system_prompt_text]]],
                'generationConfig' => ['maxOutputTokens' => 800, 'temperature' => 0.7]
            ];
        } else {
            // Use the simple, combined prompt format for all other actions
            $system_prompt = $full_prompts[$action] ?? '';
            if (empty($system_prompt)) {
                throw new Exception('Invalid action specified.');
            }
            
            $prompt_body = "Page content:\n" . $content;
            if (!empty($message)) {
                $prompt_body .= "\n\nUser question: " . $message;
            }
            
            $full_prompt = $system_prompt . "\n\n" . $prompt_body;
            
            $body_data = [
                'contents' => [['parts' => [['text' => trim($full_prompt)]]]],
                'generationConfig' => ['maxOutputTokens' => 800]
            ];
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body_data),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);

        if ($response_code === 200 && isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($body['candidates'][0]['content']['parts'][0]['text']);
        }

        error_log('Essenca - Gemini API Error. Code: ' . $response_code . '. Body: ' . $response_body);

        if (isset($body['error']['message'])) {
            throw new Exception("Gemini API Error: " . $body['error']['message']);
        }
        if (isset($body['promptFeedback']['blockReason'])) {
            $finish_reason = $body['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            throw new Exception("Content blocked by Gemini's safety filters. Reason: " . $body['promptFeedback']['blockReason'] . ' (Finish Reason: ' . $finish_reason . ')');
        }
        if (empty($body['candidates'])) {
            throw new Exception('Unexpected Gemini response: The API returned no candidates. This may be due to safety settings or an empty prompt.');
        }

        throw new Exception('Unexpected Gemini response format. Check server error logs for the full API response.');
    }

    public static function test_connection() {
        $api_key = get_option('essenca_gemini_api_key');
        if (empty($api_key)) {
            throw new Exception('Gemini API key not configured in the plugin settings.');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;
        $body_data = ['contents' => [['parts' => [['text' => 'This is a test.']]]]];
        
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body_data),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('WP_Error: ' . $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return 'API key is valid and connection is successful!';
        } else {
            $error_message = 'An unknown error occurred.';
            if (isset($body['error']['message'])) {
                $error_message = $body['error']['message'];
            } elseif (isset($body['promptFeedback']['blockReason'])) {
                $error_message = "Request blocked by safety filter. Reason: " . $body['promptFeedback']['blockReason'];
            }
            throw new Exception($error_message);
        }
    }

    private static function get_prompts() {
        return [
            'summary' => 'Read the following page content and create a concise bullet-point summary that captures: • Main topic and key arguments • Important facts and statistics • Key takeaways and conclusions Format the response as clear, easy-to-read bullet points.',
            'key-takeaway' => 'Read the following page content and identify the single most important takeaway or insight. Focus on the core message or conclusion that the reader should remember. Provide a concise, impactful response (1-2 sentences) that captures the essence of the content.',
            'chat' => 'You are a helpful AI assistant that can answer questions about the page the user is viewing. Use the page content to provide accurate, relevant responses. If the answer isn\'t in the content, acknowledge this and provide general knowledge if appropriate. Keep responses concise, informative, and conversational.',
            'generate_linkedin_comment' => 'You are a LinkedIn user. Your persona is defined by the "USER PROFILE" provided below.
Adopt this persona and write a comment AS THIS PERSON.
Your response must ONLY be the comment text itself. Do not add any introductory phrases like "Here\'s a comment for Sarah:".
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
',
            'generate_linkedin_comment_generic' => 'You are a professional social media manager helping a user write a comment on a LinkedIn post.
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
'
        ];
    }
}
