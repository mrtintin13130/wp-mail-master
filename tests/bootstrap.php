<?php
require_once __DIR__ . '/../vendor/autoload.php';

WP_Mock::bootstrap();

WP_Mock::userFunction('wp_strip_all_tags', [
    'return' => function($text) {
        return strip_tags($text);
    }
]);
