<?php

/**
 * Landlord Form Configuration
 * 
 * This file contains performance optimizations for the landlord form
 * to prevent timeout issues during form processing.
 */

// Increase execution time limit for form processing
if (php_sapi_name() !== 'cli') {
    set_time_limit(300); // 5 minutes
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '256M');
}

// Optimize form validation performance
ini_set('pcre.backtrack_limit', 1000000);
ini_set('pcre.recursion_limit', 1000000);

return [
    'form' => [
        'timeout' => 300,
        'memory_limit' => '256M',
        'validation' => [
            'simplified' => true,
            'real_time' => false,
            'batch_processing' => true
        ]
    ]
];
