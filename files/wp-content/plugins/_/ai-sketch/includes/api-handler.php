<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle image generation AJAX request
function aisg_generate_image_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aisg_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        wp_die();
    }
    
    // Validate and sanitize prompt
    if (!isset($_POST['prompt']) || empty($_POST['prompt'])) {
        wp_send_json_error(array('message' => 'Prompt is required.'));
        wp_die();
    }
    
    $prompt = sanitize_text_field($_POST['prompt']);
    
    // Get API key from options
    $api_key = get_option('aisg_stability_api_key', '');
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'API key is not configured.'));
        wp_die();
    }
    
    // Get image count and size from options
    $image_count = get_option('aisg_image_count', 4);
    $image_size = get_option('aisg_image_size', '512x512');
    
    // Parse image size
    list($width, $height) = explode('x', $image_size);
    
    // Prepare API request
    $api_url = 'https://api.stability.ai/v1/generation/stable-diffusion-v1-5/text-to-image';

    // Append the additional text to the prompt
    $prompt .= ". And I want this image influenced by Bjarke Ingels or Japandi architect.";
    
    $body = array(
        'text_prompts' => array(
            array(
                'text' => $prompt,
                'weight' => 1
            )
        ),
        'cfg_scale' => 7,
        'clip_guidance_preset' => 'FAST_BLUE',
        'height' => intval($height),
        'width' => intval($width),
        'samples' => intval($image_count),
        'steps' => 30,
    );
    
    $args = array(
        'method' => 'POST',
        'timeout' => 60,
        'redirection' => 5,
        'httpversion' => '1.1',
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($body),
    );
    
    // Make API request
    $response = wp_remote_post($api_url, $args);
    
    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        wp_die();
    }
    
    // Get response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // Check for API errors
    if (isset($data['error'])) {
        wp_send_json_error(array('message' => $data['error']['message']));
        wp_die();
    }
    
    // Process and return images
    $images = array();
    
    if (isset($data['artifacts']) && is_array($data['artifacts'])) {
        foreach ($data['artifacts'] as $artifact) {
            $images[] = array(
                'base64' => $artifact['base64'],
                'seed' => $artifact['seed'],
                'finishReason' => $artifact['finishReason']
            );
        }
    }
    
    wp_send_json_success(array(
        'images' => $images,
        'prompt' => $prompt
    ));
    
    wp_die();
}

// Handle request submission AJAX request
function aisg_submit_request_handler() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aisg_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        wp_die();
    }
    
    // Validate and sanitize form data
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $image_data = isset($_POST['image_data']) ? sanitize_text_field($_POST['image_data']) : '';
    $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($message)) {
        wp_send_json_error(array('message' => 'All fields are required.'));
        wp_die();
    }
    
    // Validate email
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        wp_die();
    }
    
    // Prepare email content
    $to = get_option('admin_email');
    $subject = 'AI Sketch Generator: Further Development Request';
    
    $body = "Name: $name\n";
    $body .= "Email: $email\n";
    $body .= "Message: $message\n";
    $body .= "Prompt Used: $prompt\n";
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Attach image if available
    $attachments = array();
    
    if (!empty($image_data)) {
        // Convert base64 to image and save temporarily
        $upload_dir = wp_upload_dir();
        $image_folder = $upload_dir['basedir'] . '/aisg-images';
        
        // Create directory if it doesn't exist
        if (!file_exists($image_folder)) {
            wp_mkdir_p($image_folder);
        }
        
        // Generate unique filename
        $filename = 'aisg-image-' . time() . '.png';
        $file_path = $image_folder . '/' . $filename;
        
        // Remove header from base64 string
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        
        // Save image
        $decoded_image = base64_decode($image_data);
        file_put_contents($file_path, $decoded_image);
        
        // Add to attachments
        $attachments[] = $file_path;
        
        // Add image to email body
        $image_url = $upload_dir['baseurl'] . '/aisg-images/' . $filename;
        $body .= "<br><br><img src='$image_url' alt='Generated Image' style='max-width: 100%;'>";
    }
    
    // Send email
    $mail_sent = wp_mail($to, $subject, $body, $headers, $attachments);
    
    // Clean up temporary file
    if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                unlink($attachment);
            }
        }
    }
    
    if ($mail_sent) {
        wp_send_json_success(array('message' => 'Your request has been submitted successfully!'));
    } else {
        wp_send_json_error(array('message' => 'Failed to send your request. Please try again.'));
    }
    
    wp_die();
}

