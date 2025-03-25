<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>
<div>
    <h2>BM|O Vision Desk - AI Render Generator</h2>
    
    <div>
        <form id="aisg-form" method="post" enctype="multipart/form-data">
            <div>
                <label for="aisg-prompt-input">Describe what you want to generate:</label>
                <textarea 
                    id="aisg-prompt-input" 
                    style="color:black;" 
                    name="aisg_prompt" 
                    rows="4" 
                    placeholder="Describe your vision! For example: 'Create a modern living room with warm lighting, cozy furniture, and a large window overlooking the city skyline.' Or, 'Transform my rough sketch into a realistic 3D render of a beachfront villa with palm trees and a sunset view" 
                    onfocus="this.style.color='black';"
                    onblur="if (this.value === '') this.style.color='#95958d';"
                    required
                ></textarea>
            </div>

            <div>
                <label for="aisg-email-input">Enter your email:</label>
                <input type="email" id="aisg-email-input" name="email" required>
            </div>
            
            <div class="aisg-mode-selector">
                <label>Generation Mode:</label>
                <label>
                    <input type="radio" name="aisg_mode" value="text" checked> Text to Image
                </label>
                <label>
                    <input type="radio" name="aisg_mode" value="sketch"> Sketch to Image
                </label>
            </div>
            
            <div id="aisg-sketch-upload" class="aisg-file-upload" style="display: none;">
                <label for="aisg-sketch-image">Upload Sketch Image:</label>
                <input type="file" id="aisg-sketch-image" name="aisg_sketch_image" style="margin-bottom: 0.7rem; margin-top: 0.5;" accept="image/*">
            </div>
            
            <div class="aisg-form-row">
                <button type="submit" id="aisg-generate-btn" class="aisg-generate-btn">Generate Images</button>
            </div>
        </form>
    </div>
    
    <div id="aisg-loading" class="aisg-loading" style="position: relative;">
        <div class="aisg-spinner" style="position: relative;"></div>
        <div class="aisg-spinner-progress">0%</div>
        <p><span id="aisg-status">Analyzing image...</span></p>
    </div>
    
    <div id="aisg-error" class="aisg-error" style="display: none;"></div>
    
    <div id="aisg-results" class="aisg-results">
        <h3 class="aisg-results-title">Select your preferred image:</h3>
        <div id="aisg-image-gallery" class="aisg-image-gallery"></div>
    </div>
    
    <div id="aisg-uploaded-sketch" class="aisg-uploaded-sketch"></div>

    
</div>



