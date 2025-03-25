<?php
/**
 * Plugin Name: AI Sketch
 * Description: A simple AI Sketch Generator plugin for WordPress with enhanced UI and security.
 * Version: 1.0
 * Author: Aindrajaya
 * Author URI: https://aindrajaya.my.id/
 * Text Domain: ai-sketch
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AISG_VERSION', '2.0');
define('AISG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include main plugin class
//require_once AISG_PLUGIN_DIR . 'includes/class-ai-sketch-generator.php';

// Initialize plugin
//function aisg_init() {
//    $instance = AI_Sketch_Generator::get_instance();
//}
//add_action('plugins_loaded', 'aisg_init');

// Register activation hook
register_activation_hook(__FILE__, 'aisg_activate');

function aisg_activate() {
    // Create options for storing the API key securely
    add_option('aisg_api_key', '');
    add_option('aisg_image_count', 4); // Default to 4 images

    // Drop the existing database table if it exists
    aisg_drop_prompt_logs_table();

    // Create the database table for prompt logs history
    aisg_create_prompt_logs_table();
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'aisg_deactivate');

function aisg_deactivate() {
    // Cleanup if needed
}

function aisg_drop_prompt_logs_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'aisg_prompt_logs';
    
    // Drop the table if it exists
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Create the database table for prompt logs history
function aisg_create_prompt_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aisg_prompt_logs';
    
    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Create the table
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            prompt text NOT NULL,
            email varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Enqueue styles and scripts
function aisg_enqueue_assets() {
    // Enqueue the existing CSS
    wp_enqueue_style('aisg-styles', AISG_PLUGIN_URL . 'css/ai-form.css', array(), AISG_VERSION);
    
    // Enqueue new CSS for enhanced UI
    wp_enqueue_style('aisg-enhanced-styles', AISG_PLUGIN_URL . 'css/ai-enhanced.css', array(), AISG_VERSION);
     
    // Enqueue JavaScript
    wp_enqueue_script('aisg-script', AISG_PLUGIN_URL . 'js/ai-sketch-generator.js', array('jquery'), AISG_VERSION, true);
    
    // Pass data to JavaScript
    wp_localize_script('aisg-script', 'aisg_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aisg_nonce'),
        'image_count' => get_option('aisg_image_count', 4),
    ));

    // Enqueue admin script for settings page
    wp_enqueue_script('aisg-admin-script', AISG_PLUGIN_URL . 'js/ai-sketch-admin.js', array('jquery'), AISG_VERSION, true);

}
add_action('wp_enqueue_scripts', 'aisg_enqueue_assets');

// Add admin menu
function aisg_add_admin_menu() {
    add_menu_page(
        'AI Render Generator Settings',
        'AI Render Generator',
        'manage_options',
        'ai-sketch-generator',
        'aisg_settings_page',
        'dashicons-art',
        30
    );
}
add_action('admin_menu', 'aisg_add_admin_menu');

// Settings page
function aisg_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings if form is submitted
    if (isset($_POST['aisg_save_settings']) && check_admin_referer('aisg_settings_nonce')) {
        // Sanitize and save API key
        if (isset($_POST['aisg_api_key'])) {
            update_option('aisg_api_key', sanitize_text_field($_POST['aisg_api_key']));
        }
        
        // Sanitize and save image count
        if (isset($_POST['aisg_image_count'])) {
            update_option('aisg_image_count', absint($_POST['aisg_image_count']));
        }

        // Sanitize and save image size
        if (isset($_POST['aisg_image_size'])) {
            update_option('aisg_image_size', sanitize_text_field($_POST['aisg_image_size']));
        }

        // Sanitize and save email address
        if (isset($_POST['aisg_email_address'])) {
            $email_address = sanitize_email($_POST['aisg_email_address']);
            if (is_email($email_address)) {
                update_option('aisg_email_address', $email_address);
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Invalid email address format.</p></div>';
            }
        }

        
        // Sanitize and save upload limit
        if (isset($_POST['aisg_upload_limit'])) {
            update_option('aisg_upload_limit', absint($_POST['aisg_upload_limit']));
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $api_key = get_option('aisg_api_key', '');
    $image_count = get_option('aisg_image_count', 4);
    $image_size = get_option('aisg_image_size', '512x512');
    $email_address = get_option('aisg_email_address', 'mail@admin.com');
    $upload_limit = get_option('aisg_upload_limit', 40);
    
    // Display settings form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <!-- Display the prompt logs table -->
        <div class="aisg-prompt-logs">
            <h2>Prompt Logs</h2>
            <?php
            aisg_display_prompt_logs();
            ?>

         </div>
         <hr>   
        
        <form method="post" action="">
            <?php wp_nonce_field('aisg_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aisg_api_key">Stability AI API Key</label></th>
                    <td>
                        <input type="password" id="aisg_api_key" name="aisg_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description">Enter your Stability AI API key. <a href="https://platform.stability.ai/" target="_blank">Get one here</a>.</p>
                        <input type="checkbox" id="aisg_show_api_key"> Show API Key
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aisg_image_count">Number of Images</label></th>
                    <td>
                        <input type="number" id="aisg_image_count" name="aisg_image_count" value="<?php echo esc_attr($image_count); ?>" min="1" max="10" class="small-text">
                        <p class="description">Number of images to generate per request (1-10).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aisg_image_size">Image Size</label></th>
                    <td>
                        <select id="aisg_image_size" name="aisg_image_size">
                            <option value="512x512" <?php selected($image_size, '512x512'); ?>>512x512</option>
                            <option value="768x768" <?php selected($image_size, '768x768'); ?>>768x768</option>
                            <option value="1024x1024" <?php selected($image_size, '1024x1024'); ?>>1024x1024</option>
                        </select>
                        <p class="description">Size of generated images.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aisg_email_address">Notification Email Address</label></th>
                    <td>
                        <input type="email" id="aisg_email_address" name="aisg_email_address" value="<?php echo esc_attr($email_address); ?>" class="regular-text">
                        <p class="description">Enter the email address to receive notifications.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aisg_upload_limit">Daily Upload Limit</label></th>
                    <td>
                        <input type="number" id="aisg_upload_limit" name="aisg_upload_limit" value="<?php echo esc_attr($upload_limit); ?>" min="1" class="small-text">
                        <p class="description">Maximum number of uploads allowed per day (default is 40).</p>
                    </td>
            </table>
            <p class="submit">
                <input type="submit" name="aisg_save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>
        
        <div class="aisg-shortcode-info">
            <h2>Shortcode Usage</h2>
            <p>Use the following shortcode to display the AI Sketch Generator on any page or post:</p>
            <code>[aisg_ai_form]</code>
        </div>


    </div>
    <?php
}

function aisg_display_prompt_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aisg_prompt_logs';
    
    // Fetch prompt logs
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
    
    if ($logs) {
        echo '<table class="widefat cellspacing="0" cellpadding="0">';
        echo '<thead><tr><th>ID</th><th>Prompt</th><th>Email</th><th>Created At</th><th>Additional Field</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log['id']) . '</td>';
            echo '<td>' . esc_html($log['prompt']) . '</td>';
            echo '<td>' . esc_html($log['email']) . '</td>';
            echo '<td>' . esc_html($log['created_at']) . '</td>';
            echo '<td>' . esc_html($log['additional_field']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No prompt logs found.</p>';
    }
}

// Register AJAX handlers
function aisg_register_ajax_handlers() {
    add_action('wp_ajax_aisg_generate_images', 'aisg_generate_images');
    add_action('wp_ajax_nopriv_aisg_generate_images', 'aisg_generate_images');
    
    add_action('wp_ajax_aisg_submit_request', 'aisg_submit_request');
    add_action('wp_ajax_nopriv_aisg_submit_request', 'aisg_submit_request');
}
add_action('init', 'aisg_register_ajax_handlers');

// AJAX handler for generating images
function aisg_generate_images() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aisg_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        wp_die();
    }

    // Check upload limit
    $user_ip = aisg_get_user_ip();
    if (!aisg_check_upload_limit($user_ip)) {
        wp_send_json_error(array('message' => 'You have reached the maximum number of uploads for today.'));
        wp_die();
    }
    
    // Get parameters
    $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'text';
    
    // Validate input
    if (empty($prompt)) {
        wp_send_json_error(array('message' => 'Please provide a prompt.'));
        wp_die();
    }

    if(empty($email) || !is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        wp_die();
    }

    // Log the prompt input
    aisg_log_prompt_input($prompt, $email);

    // Send prompt logs to notification email
    aisg_send_prompt_logs_email($prompt);
    
    // Get API key from options
    $api_key = get_option('aisg_api_key', '');
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'API key is not configured. Please contact the administrator.'));
        wp_die();
    }
    
    // Get image count from options
    $image_count = get_option('aisg_image_count', 4);
    
    // Initialize variables
    $images = array();
    $error = '';
    
    // Process sketch upload if in sketch mode
    $sketch_file = null;
    if ($mode === 'sketch' && isset($_FILES['sketch_image']) && $_FILES['sketch_image']['error'] === UPLOAD_ERR_OK) {
        $sketch_file = $_FILES['sketch_image'];
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($sketch_file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type. Please upload a JPEG, PNG, or GIF image.'));
            wp_die();
        }
    }
    
    // Generate multiple images
    for ($i = 0; $i < $image_count; $i++) {
        $result = aisg_call_stability_api($api_key, $prompt, $mode, $sketch_file);
        
        if (isset($result['error'])) {
            $error = $result['error'];
            break;
        }
        
        if (isset($result['image'])) {
            $images[] = $result['image'];
        }
    }
    
    // Return results
    if (!empty($error)) {
        wp_send_json_error(array('message' => $error));
    } elseif (empty($images)) {
        wp_send_json_error(array('message' => 'Failed to generate images.'));
    } else {
        wp_send_json_success(array('images' => $images, 'prompt' => $prompt, 'email' => $email));
    }
    
    wp_die();
}

// Function to log prompt input
function aisg_log_prompt_input($prompt, $email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aisg_prompt_logs';
    
    // Insert prompt into the database
    $wpdb->insert(
        $table_name,
        array(
            'prompt' => $prompt,
            'email' => $email,
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s')
    );
}

// Function to send prompt logs email
function aisg_send_prompt_logs_email($prompt){
    global $wpdb;
    $table_name = $wpdb->prefix . 'aisg_prompt_logs';

    // Fetch the latest prompt logs
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5", ARRAY_A);
    if ($logs) {
        $log_list = '';
        foreach ($logs as $log) {
            $log_entries .= 'ID: ' . esc_html($log['id']) . '<br>';
            $log_entries .= 'Prompt: ' . esc_html($log['prompt']) . '<br>';
            $log_entries .= 'Created At: ' . esc_html($log['created_at']) . '<br><br>';
        }
        
        // Prepare email content
        $to = get_option('aisg_email_address', '');
        $subject = __('AI Render Generator - Prompt Logs', 'ai-sketch');
        $body = sprintf(
            __(
                '<div style="font-family: Arial, sans-serif; line-height: 1.5; color: #333;">
                    <h2 style="color: #333;">Prompt Logs</h2>
                    <p><strong style="color: #444;">Current Prompt:</strong> %s</p>
                    <p><strong style="color: #444;">Logs:</strong><br>%s</p>
                </div>',
                'ai-sketch'
            ),
            htmlentities($prompt),
            $log_entries
        );
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AI Render Generator <no-reply@yourdomain.com>'
        );

        // Send email
        $mail_sent = wp_mail($to, $subject, $body, $headers);
        if ($mail_sent) {
            error_log("aisg_send_prompt_logs_email Email sent successfully to: $to");
        } else {
            error_log("aisg_send_prompt_logs_email Failed to send email to: $to");
        }
    } else {
        error_log("aisg_send_prompt_logs_email No prompt logs found.");
    }
}

// Function to call Stability AI API
function aisg_call_stability_api($api_key, $prompt, $mode, $sketch_file = null) {
    $allowed_keywords = [
        // General Architecture Terms
        'architect', 'architecture', 'building', 'construction', 'design', 'urban planning', 'blueprint', 'drafting',

        // Styles & Aesthetics
        'modern', 'minimalist', 'Japandi', 'industrial', 'brutalist', 'contemporary', 'traditional', 'postmodern',
        'neoclassical', 'art deco', 'mid-century modern', 'parametric', 'biophilic', 'vernacular', 'organic',

        // Building Types
        'house', 'home', 'apartment', 'villa', 'bungalow', 'cottage', 'mansion', 'residence', 'townhouse',
        'skyscraper', 'tower', 'high-rise', 'low-rise', 'loft', 'duplex', 'penthouse', 'row house', 'dome',
        'tiny house', 'prefabricated home', 'container home',

        // Interior & Exterior Elements
        'interior', 'exterior', 'façade', 'courtyard', 'atrium', 'balcony', 'veranda', 'terrace', 'rooftop',
        'patio', 'porch', 'window', 'door', 'staircase', 'hallway', 'mezzanine', 'skylight',

        // Architectural Features
        'floor plan', 'elevation', 'cross-section', 'rendering', 'perspective view', 'axonometric',
        'isometric', 'site plan', 'landscape design', 'zoning', 'structural engineering', 'load-bearing walls',
        'sustainability', 'passive design', 'green building', 'smart home', 'modular design',

        // Notable Architects & Influences
        'Bjarke Ingels', 'Frank Lloyd Wright', 'Le Corbusier', 'Zaha Hadid', 'Mies van der Rohe', 'Rem Koolhaas',
        'Tadao Ando', 'Richard Neutra', 'Louis Kahn', 'Renzo Piano', 'Norman Foster', 'Oscar Niemeyer',

        // Materials & Finishes
        'concrete', 'steel', 'glass', 'wood', 'bamboo', 'brick', 'stone', 'marble', 'granite',
        'ceramic', 'terracotta', 'corten steel', 'rammed earth',

        // Spaces & Functions
        'kitchen', 'bedroom', 'bathroom', 'living room', 'dining room', 'office', 'workspace',
        'auditorium', 'library', 'gallery', 'museum', 'theater', 'stadium', 'hospital', 'school',
        'university', 'park', 'plaza', 'shopping mall', 'retail space',

        // Sustainable & Smart Architecture
        'solar panel', 'rainwater harvesting', 'green roof', 'ventilation', 'insulation', 'energy-efficient',
        'biophilic design', 'net-zero', 'carbon-neutral', 'parametric design',

        // Urban & Landscape Design
        'urban', 'landscape', 'cityscape', 'skyline', 'master plan', 'infrastructure', 'streetscape',
        'pedestrian-friendly', 'public space', 'walkability', 'bike lane', 'transportation hub',
        'eco-friendly', 'garden', 'botanical garden', 'arboretum', 'park', 'green space', 'natural landscape',

        // Cultural & Historical References
        'cultural', 'historical', 'heritage', 'contextual', 'adaptive reuse', 'preservation', 'restoration',
        'renovation', 'revitalization', 'urban renewal', 'gentrification',

        // Miscellaneous
        'concept', 'vision', 'rendering', 'illustration', 'artwork', 'sketch', 'draft', 'model',
        'prototype', 'simulation', '3D model', 'virtual reality', 'augmented reality', 'digital twin',
        'photorealistic', 'stylized', 'abstract', 'surreal', 'fantasy', 'futuristic',
        'whimsical', 'playful', 'elegant', 'sophisticated', 'luxurious', 'cozy', 'inviting', 'warm',
    ];

    // Functino to validate keyword based on allowed keywords
    function is_valid_keyword($prompt, $allowed_keywords) {
        foreach ($allowed_keywords as $keyword) {
            if (stripos($prompt, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    // Validate the user prompt
    if(!is_valid_keyword($prompt, $allowed_keywords)) {
        echo json_encode(["error" => "This AI is specifically for architecture design. Please provide a relevant prompt."]);
        exit;
    }

    // Set up the endpoint based on the chosen mode
    if ($mode === 'sketch' && $sketch_file) {
        $url = 'https://api.stability.ai/v2beta/stable-image/control/sketch';
        
        // Prepare the file for cURL
        $tmp_file_path = $sketch_file['tmp_name'];

        // Append the additional text to the prompt
        $prompt .= ". And I want this image influenced by Bjarke Ingels or Japandi architect.";
        
        // Build POST fields
        $post_fields = array(
            'image' => new CURLFile($tmp_file_path, $sketch_file['type'], $sketch_file['name']),
            'prompt' => $prompt,
            'control_strength' => '0.7',
            'output_format' => 'webp'
        );
    } else {
        $url = 'https://api.stability.ai/v2beta/stable-image/generate/ultra';
        // Append the additional text to the prompt
        $prompt .= ". And I want this image influenced by Bjarke Ingels or Japandi architect.";
        $post_fields = array(
            'prompt' => $prompt,
            'output_format' => 'webp'
        );
    }
    
    // Set up cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    
    // Headers including the API key
    $headers = array(
        "authorization: Bearer $api_key",
        "accept: image/*"
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    
    // Check for cURL error
    if (curl_errno($ch)) {
        curl_close($ch);
        return array('error' => 'cURL error: ' . curl_error($ch));
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verify that the API returned a 200 OK response
    if ($http_code == 200) {
        // Convert image to base64
        $base64_image = base64_encode($response);
        return array('image' => 'data:image/webp;base64,' . $base64_image);
    } else {
        return array('error' => 'API error: Received HTTP Code ' . $http_code);
    }
}

// AJAX handler for submitting development requests
function aisg_submit_request() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aisg_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        wp_die();
    }
    
    // Get form data
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $image_url = isset($_POST['image_url']) ? sanitize_text_field($_POST['image_url']) : '';
    $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
    $uploaded_sketch = isset($_POST['uploaded_sketch']) ? sanitize_text_field($_POST['uploaded_sketch']) : '';
    
    // Log form data
    error_log("aisg_submit_request: Form data - Name: $name, Email: $email, Message: $message, Image URL: $image_url, Prompt: $prompt, Uploaded Sketch: $uploaded_sketch");

    // Validate required fields
    if (empty($name) || empty($email) || empty($message)) {
        error_log('aisg_submit_request: Validation failed - Missing required fields.');
        wp_send_json_error(array('message' => 'Please fill in all required fields.'));
        wp_die();
    }
    
    // Validate email
    if (!is_email($email)) {
        error_log('aisg_submit_request: Validation failed - Invalid email address.');
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        wp_die();
    }
    
    // Prepare email content
    $to = get_option('aisg_email_address', '');
    if (empty($to)) {
        error_log('aisg_submit_request: Validation failed - No email address configured.');
        wp_send_json_error(array('message' => 'No email address configured for notifications.'));
        wp_die();
    }

    $subject = __('AI Render Generator - Development Request', 'ai-sketch');
    $body = sprintf(
        __(
            '<div style="font-family: Arial, sans-serif; line-height: 1.5; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #000; text-align: center; padding: 20px;">
                    <img src="https://www.bmoutsourcing.com/wp-content/uploads/2023/01/1496144229-1.png" alt="BM Outsourcing" style="max-width: 200px; height: auto;">
                </div>
                <div style="padding: 20px;">
                    <h2 style="color: #333; text-align: center; margin: 0 0 20px;">New Development Request Received</h2>
                    <div style="margin-bottom: 20px;">
                        <p><strong style="color: #444;">Name:</strong> %s</p>
                        <p><strong style="color: #444;">Email:</strong> <a href="mailto:%s" style="color: #007bff; text-decoration: none;">%s</a></p>
                        <p><strong style="color: #444;">Prompt:</strong> %s</p>
                    </div>
                    <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #fa4e42; margin-bottom: 20px;">
                        <p style="margin: 0;"><strong style="color: #444;">Message:</strong></p>
                        <p style="margin: 10px 0 0;">%s</p>
                    </div>
                </div>
                <div style="background-color: #f9f9f9; text-align: center; padding: 15px; font-size: 14px; color: #777;">
                    <p style="margin: 0;">This is an automated notification. For assistance, please <a href="mailto:info@bmoutsourcing.com" style="color: #007bff; text-decoration: none;">contact us</a>.</p>
                </div>
            </div>',
            'ai-sketch'
        ),
        htmlentities($name),
        htmlentities($email),
        htmlentities($email), // Repeated for the mailto link
        htmlentities($prompt),
        nl2br(htmlentities($message))
    );

    // Send email to user
    $user_subject = __('Thank You for Your AI Request', 'ai-sketch');
    $user_body = sprintf(
        __(
            '<div style="font-family: Arial, sans-serif; line-height: 1.5; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #000; text-align: center; padding: 20px;">
                    <img src="https://www.bmoutsourcing.com/wp-content/uploads/2023/01/1496144229-1.png" alt="BM Outsourcing" style="max-width: 200px; height: auto;">
                </div>
                <div style="padding: 20px;">
                    <h2 style="color: #333; text-align: center;">Thanks for using our AI generator!</h2>
                    <p style="text-align: center;">We love your concept and think it has real potential.</p>
                    <p>To help you take it to the next level, we’re offering a special package for <strong>$399</strong> that includes:</p>
                    <ul style="list-style-type: disc; padding-left: 20px;">
                        <li>A high-quality, professional render of your idea</li>
                        <li>A design presentation (PDF) that brings your vision to life</li>
                        <li>Expert feedback to guide your next steps (like drawings and development)</li>
                    </ul>
                    <p>It’s a great way to move forward without committing to full design costs just yet.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="https://buy.stripe.com/cN28xC9F80So8eccMN" style="background-color: #fa4e42; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">Proceed to Payment</a>
                    </div>
                    <p style="text-align: center; font-size: 14px; color: #777;">If you have any questions, feel free to <a href="mailto:support@bmoutsourcing.com" style="color:rgb(141, 133, 133); text-decoration: none;">contact us</a>.</p>
                </div>
            </div>',
            'ai-sketch'
        )
    );
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $name . ' <' . $email . '>',
        'Reply-To: ' . $email
    );

    $user_headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: AI Render Generator <no-reply@yourdomain.com>'
    );
    
    // Attach images if available
    $attachments = array();

    // Log email details
    error_log("aisg_submit_request: Email details - To: $to, Subject: $subject, Body: $body, Headers: " . implode(', ', $headers));
    
    // Process generated image
    if (!empty($image_url)) {
        error_log("Processing generated image...");
        // Convert base64 to image and save temporarily
        $upload_dir = wp_upload_dir();
        $image_folder = $upload_dir['basedir'] . '/aisg-images';

        // Create directory if it doesn't exist
        if (!file_exists($image_folder)) {
            wp_mkdir_p($image_folder);
            error_log("Created directory: $image_folder");
        }

        // Generate unique filename
        $filename = 'aisg-image-' . time() . '.png';
        $file_path = $image_folder . '/' . $filename;

        // Remove header from base64 string
        $image_data = str_replace('data:image/webp;base64,', '', $image_url);
        $image_data = str_replace(' ', '+', $image_data);

        // Save image
        $decoded_image = base64_decode($image_data);
        file_put_contents($file_path, $decoded_image);
        error_log("Saved generated image to: $file_path");

        // Add to attachments
        $attachments[] = $file_path;

        // Add image to email body
        $image_url = $upload_dir['baseurl'] . '/aisg-images/' . $filename;
        $body .= "<br><br><h3>Generated Image:</h3>";
        $body .= "<a href='$image_url' target='_blank'>$image_url</a>";
        $user_body .= "<br><br><h3>Generated Image:</h3>";
        $user_body .= "<a href='$image_url' target='_blank'>$image_url</a>";
        error_log("Added generated image link to email body: $image_url");
    }
    
    // Process uploaded sketch if available
    if (!empty($uploaded_sketch)) {
        error_log("Processing uploaded sketch...");
        // Convert base64 to image and save temporarily
        $upload_dir = wp_upload_dir();
        $image_folder = $upload_dir['basedir'] . '/aisg-images';

        // Create directory if it doesn't exist
        if (!file_exists($image_folder)) {
            wp_mkdir_p($image_folder);
            error_log("Created directory: $image_folder");
        }

        // Generate unique filename
        $sketch_filename = 'aisg-sketch-' . time() . '.png';
        $sketch_file_path = $image_folder . '/' . $sketch_filename;

        // Remove header from base64 string
        $sketch_data = str_replace('data:image/webp;base64,', '', $uploaded_sketch);
        $sketch_data = str_replace(' ', '+', $sketch_data);

        // Save image
        $decoded_sketch = base64_decode($sketch_data);
        file_put_contents($sketch_file_path, $decoded_sketch);
        error_log("Saved uploaded sketch to: $sketch_file_path");

        // Add to attachments
        $attachments[] = $sketch_file_path;

        // Add sketch to email body
        $sketch_url = $upload_dir['baseurl'] . '/aisg-images/' . $sketch_filename;
        $body .= "<br><br><h3>Uploaded Sketch:</h3>";
        $body .= "<a href='$sketch_url' target='_blank'>$sketch_url</a>";
        $user_body .= "<br><br><h3>Uploaded Sketch:</h3>";
        $user_body .= "<a href='$sketch_url' target='_blank'>$sketch_url</a>";
        error_log("Added uploaded sketch link to email body: $sketch_url");
    }
    
    // Send email
    $mail_sent = wp_mail($to, $subject, $body, $headers, $attachments);
    error_log("aisg_submit_request Email sent to: $to, Subject: $subject, Body: $body"); // Log the email content for debugging
    error_log("aisg_submit_request Mail sent status: " . ($mail_sent ? 'Success' : 'Failure')); // Log the mail sending status

    $user_mail_sent = wp_mail($email, $user_subject, $user_body, $user_headers, $attachments);
    error_log("aisg_submit_request User email sent to: $email, Subject: $user_subject, Body: $user_body"); // Log the email content for debugging
    error_log("aisg_submit_request User mail sent status: " . ($user_mail_sent ? 'Success' : 'Failure')); // Log the mail sending status
    
    // Clean up temporary files
    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                unlink($attachment);
            }
        }
    }
    
    if ($mail_sent && $user_mail_sent) {
        error_log("aisg_submit_request Email sent successfully to: $to"); // Log the email sending status
        wp_send_json_success(array('message' => 'Your request has been submitted successfully!'));
    } else {
        error_log("aisg_submit_request Failed to send email to: $to"); // Log the email sending status
        wp_send_json_error(array('message' => 'Failed to send your request. Please try again.'));
    }
    
    wp_die();
}

// Register the AJAX action
add_action('wp_ajax_aisg_submit_request', 'aisg_submit_request');
add_action('wp_ajax_nopriv_aisg_submit_request', 'aisg_submit_request');

// Register shortcode
function aisg_form_shortcode() {
    $remaining_uploads = aisg_get_remaining_uploads();

    ob_start();
    include AISG_PLUGIN_DIR . 'templates/ai-form-template.php';
    $output = ob_get_clean();
    
    return $output . '<hr>
<div style="margin-top: 2rem; background-color: #f0f0f0; border: 1px solid #ddd;; padding: 1rem; border-radius: 5px;">
    <h3 style="margin-top: 0; color: #555;">Important Note:</h3>
    <p style="margin-bottom: 0; color: #555;">Further design development will be handled by BM|O. This tool is designed to capture your vision and provide our design team with a clear understanding of your concept.</p>
    <p style="color: #555;" class="aisg-remaining-uploads">Remaining uploads today: '. $remaining_uploads . '</p>
</div>'; 
}
add_shortcode('aisg_ai_form', 'aisg_form_shortcode');

// Create templates directory and move the form template
function aisg_create_templates() {
    $templates_dir = AISG_PLUGIN_DIR . 'templates';
    
    if (!file_exists($templates_dir)) {
        wp_mkdir_p($templates_dir);
    }
    
    // Copy the form template if it doesn't exist
    $template_file = $templates_dir . '/ai-form-template.php';
    if (!file_exists($template_file)) {
        $original_file = AISG_PLUGIN_DIR . 'ai-form.php';
        if (file_exists($original_file)) {
            copy($original_file, $template_file);
        }
    }
}
add_action('plugins_loaded', 'aisg_create_templates');

// Function to check and update upload count for an IP address
function aisg_check_upload_limit($ip_address) {
    $upload_counts = get_option('aisg_upload_counts', array());
    $current_date = date('Y-m-d');
    $upload_limit = get_option('aisg_upload_limit', 40); // Default to 40 uploads

    // Clean up old entries (older than 24 hours)
    foreach ($upload_counts as $ip => $data) {
        if ($data['date'] !== $current_date) {
            unset($upload_counts[$ip]);
        }
    }

    if (!isset($upload_counts[$ip_address]) || $upload_counts[$ip_address]['date'] !== $current_date) {
        $upload_counts[$ip_address] = array(
            'count' => 1,
            'date' => $current_date
        );
    } else {
        $upload_counts[$ip_address]['count']++;
    }

    update_option('aisg_upload_counts', $upload_counts);

    return $upload_counts[$ip_address]['count'] <= $upload_limit;
}

// Function to get remaining uploads for the current user
function aisg_get_remaining_uploads() {
    $user_ip = aisg_get_user_ip();
    $upload_counts = get_option('aisg_upload_counts', array());
    $current_date = date('Y-m-d');
    $upload_limit = get_option('aisg_upload_limit', 40); // Default to 40 uploads

    if (isset($upload_counts[$user_ip]) && $upload_counts[$user_ip]['date'] === $current_date) {
        $used_uploads = $upload_counts[$user_ip]['count'];
    } else {
        $used_uploads = 0;
    }

    return max(0, $upload_limit - $used_uploads);
}

// Function to get the user's IP address
function aisg_get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP passed from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        // IP address from remote address
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}