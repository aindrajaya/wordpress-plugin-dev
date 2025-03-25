<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Register shortcode
function aisg_generator_shortcode($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'title' => 'AI Sketch Generator',
    ), $atts, 'ai_sketch_generator');
    
    // Start output buffering
    ob_start();
    
    // Include template
    include AISG_PLUGIN_DIR . 'templates/generator-template.php';
    
    // Return buffered content
    return ob_get_clean();
}
add_shortcode('ai_sketch_generator', 'aisg_generator_shortcode');

