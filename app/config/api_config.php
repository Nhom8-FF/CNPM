<?php
/**
 * API Configuration File
 * Contains API keys and configuration for external services
 */

// Return as an array that can be included in other files
return [
    // Google Gemini API - Get your key from https://makersuite.google.com/
    'google_api_key' => 'AIzaSyCGq0dnZduvms3Uhg3jZ6nicSWM0s_4rNA',
    
    // Clarifai API - Get your key from https://www.clarifai.com/
    'clarifai_api_key' => '115ff8c3a2094c7a928b3e3e8dbc7a78',
    
    // Model settings
    'models' => [
        'gemini' => [
            'default' => 'gemini-2.0-flash',
            'versions' => [
                'flash' => 'gemini-2.0-flash',
                'standard' => 'gemini-2.0-pro'
            ]
        ]
    ],
    
    // API endpoints
    'endpoints' => [
        'gemini' => 'https://generativelanguage.googleapis.com/v1/models/'
    ],
    
    // Default settings
    'settings' => [
        'max_tokens' => 1024,
        'temperature' => 0.7,
        'timeout' => 30,
        'max_image_size' => 10 * 1024 * 1024, // 10MB
        'max_total_size' => 20 * 1024 * 1024,  // 20MB
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/jpg'],
        'allowed_document_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // docx
        ]
    ]
];