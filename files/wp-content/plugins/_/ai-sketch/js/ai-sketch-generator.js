jQuery(document).ready(($) => {
  // Cache DOM elements
  const $generateBtn = $("#aisg-generate-btn");
  const $promptInput = $("#aisg-prompt-input");
  const $emailInput = $("#aisg-email-input");
  const $modeRadios = $('input[name="aisg_mode"]');
  const $sketchUpload = $("#aisg-sketch-upload");
  const $sketchInput = $("#aisg-sketch-image");
  const $loadingSpinner = $("#aisg-loading");
  const $resultsContainer = $("#aisg-results");
  const $imageGallery = $("#aisg-image-gallery");
  const $errorContainer = $("#aisg-error");
  const $uploadedSketchContainer = $("#aisg-uploaded-sketch");

  // Store selected image data
  let selectedImage = null
  let generatedPrompt = ""
  let uploadedSketchUrl = null
  let progressInterval = null;

  // Get the number of images to generate from the localized script data
  const numImages = aisg_data.image_count;
  console.log("Number of images to generate:", numImages); // Log the number of images to generate

  // Toggle sketch upload based on mode selection
  $modeRadios.on("change", () => {
    const mode = $('input[name="aisg_mode"]:checked').val()
    if (mode === "sketch") {
      $sketchUpload.show()
    } else {
      $sketchUpload.hide()
      $uploadedSketchContainer.hide()
    }
  })

  // Preview uploaded sketch
  $sketchInput.on("change", function () {
    if (this.files && this.files[0]) {
      const reader = new FileReader()

      reader.onload = (e) => {
        uploadedSketchUrl = e.target.result
        // console.log("uploadedSketchUrl:", uploadedSketchUrl); // Log the uploaded sketch URL
      }

      reader.readAsDataURL(this.files[0])
    }
  })

  // Handle form submission
  $("#aisg-form").on("submit", (e) => {
    e.preventDefault()

    // Get form data
    const prompt = $promptInput.val().trim()
    const email = $emailInput.val().trim()
    const mode = $('input[name="aisg_mode"]:checked').val()

    // Validate input
    if (!prompt) {
      showError("Please enter a prompt for the image generation.")
      return
    }

    if(!email) {
      showError("Please enter your email address.")
      return
    }

    if (!isValidEmail(email)) {
      showError("Please enter a valid email address.")
      return
    }

    if (mode === "sketch" && $sketchInput[0].files.length === 0) {
      showError("Please upload a sketch image.")
      return
    }

    // Show loading spinner and hide previous results
    $loadingSpinner.show()
    $resultsContainer.hide()
    $uploadedSketchContainer.hide()
    $errorContainer.hide()
    $generateBtn.prop("disabled", true)

    // Reset progress and status
    updateProgress(0, "Analyzing image...");

    // Get number of images to generate (default to 5)
    const baseTimePerImage = 10; // 10 seconds per image to reach 90%
    const totalBaseTime = baseTimePerImage * numImages; // Total time to reach 90%

    // Simulate progress updates
    let progress = 0;
    let startTime = Date.now();
    progressInterval = setInterval(() => {
      const elapsedTime = (Date.now() - startTime) / 1000; // Elapsed time in seconds
      if (progress < 90) {
        // Calculate progress based on elapsed time and total estimated time
        progress = (elapsedTime / totalBaseTime) * 90;
      } else if (progress < 96) {
        progress += 2; // Increment to 96
      } else if (progress < 99) {
        progress += 1; // Increment to 99
      } else {
        // Stop the interval once progress reaches 99
        clearInterval(progressInterval);
      }
      updateProgress(Math.min(progress, 99), getStatusMessage(progress));
    }, 500); // Update progress every 500ms

    // Create FormData object for file upload
    const formData = new FormData()
    formData.append("action", "aisg_generate_images")
    formData.append("nonce", aisg_data.nonce)
    formData.append("prompt", prompt)
    formData.append("email", email)
    formData.append("mode", mode)

    if (mode === "sketch" && $sketchInput[0].files.length > 0) {
      formData.append("sketch_image", $sketchInput[0].files[0])
    }

    // Make AJAX request
    $.ajax({
      url: aisg_data.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        // Clear progress interval
        clearInterval(progressInterval);

        console.log("DATA FORM: ", response.data); // Log the response data

        // Update progress to 100% on success
        updateProgress(100, "Image generation complete!");

        setTimeout(() => {
          // Hide loading spinner
          $loadingSpinner.hide()
          $generateBtn.prop("disabled", false)

          if (response.success) {
            // Store prompt for later use
            generatedPrompt = response.data.prompt
            const userEmail = response.data.email

            // Display images
            displayImages(response.data.images, userEmail)

            // Show results
            $resultsContainer.show()

            // Show uploaded sketch if in sketch mode
            if (mode === "sketch" && uploadedSketchUrl) {
              displayUploadedSketch(uploadedSketchUrl)
            }

            // Scroll to results
            $("html, body").animate(
              {
                scrollTop: $resultsContainer.offset().top - 50,
              },
              500,
            )
          } else {
            showError(response.data.message || "An error occurred while generating images.")

            // If the error is due to reaching the upload limit, update the remaining uploads display
            if (response.data.message.includes("maximum number of uploads")) {
              $(".aisg-remaining-uploads").text("Remaining uploads today: 0")
            }
          }
        }, 1000)
      },
      error: () => {
        // Clear progress interval
        clearInterval(progressInterval);

        $loadingSpinner.hide()
        $generateBtn.prop("disabled", false)
        showError("An error occurred while connecting to the server.")
      },
    })
  })

  // Display uploaded sketch
  function displayUploadedSketch(sketchUrl) {
    $uploadedSketchContainer
      .html(`
          <h3 class="aisg-results-title">Your Uploaded Sketch:</h3>
          <div class="aisg-uploaded-sketch-image">
              <img src="${sketchUrl}" alt="Uploaded Sketch">
          </div>
      `)
      .show()
  }

  // Display images in gallery
  function displayImages(images, email) {
    // Clear previous images
    $imageGallery.empty()

    // Add each image to the gallery
    images.forEach((imageUrl, index) => {
      const $imageItem = $(`
                <div class="aisg-image-item" data-index="${index}">
                    <img src="${imageUrl}" alt="Generated Image ${index + 1}">
                    <div class="aisg-image-overlay">
                        <button class="aisg-select-btn">Select</button>
                    </div>
                </div>
            `)

      $imageGallery.append($imageItem)
    })

    // Add click handler for image selection
    $(".aisg-image-item").on("click", function () {
      const index = $(this).data("index")
      const imageUrl = images[index]

      // Update selected image
      selectedImage = imageUrl

      // Update UI to show selected image
      $(".aisg-image-item").removeClass("selected")
      $(this).addClass("selected")

      // Show development request modal
      showDevelopmentModal(imageUrl, email)
    })
  }

  // Show development request modal
  function showDevelopmentModal(imageUrl, email) {
    console.log("imageUrl:", imageUrl); // Log the image URL
  
    // Create modal if it doesn't exist
    if ($("#aisg-modal").length === 0) {
      const $modal = $(`
        <div id="aisg-modal" class="aisg-modal">
          <div class="aisg-modal-content">
            <span class="aisg-close-modal">&times;</span>
            <h2 class="aisg-modal-title">Request Further Development</h2>
            
            <div class="aisg-selected-image-container">
              <img id="aisg-selected-image" src="${imageUrl}" alt="Selected Image">
            </div>
            
            ${
              uploadedSketchUrl
                ? `
            <div class="aisg-uploaded-sketch-modal">
              <h4>Your Uploaded Sketch:</h4>
              <img src="${uploadedSketchUrl}" alt="Uploaded Sketch">
            </div>
            `
                : ""
            }
            
            <div id="aisg-contact-form">
              <div class="aisg-form-group">
                <label for="aisg-name">Your Name</label>
                <input type="text" id="aisg-name" required>
              </div>
              
              <div class="aisg-form-group">
                <label for="aisg-email">Your Email</label>
                <input type="email" id="aisg-email" value="${email}" readonly required>
              </div>
              
              <div class="aisg-form-group">
                <label for="aisg-message">Message</label>
                <textarea id="aisg-message" rows="4" required></textarea>
              </div>
              
              <button type="button" id="aisg-submit-request" class="aisg-submit-button">Submit Request</button>
              
              <div id="aisg-form-message" class="aisg-success-message" style="display: none;"></div>
            </div>
          </div>
        </div>
      `);
  
      $("body").append($modal);
  
      // Handle modal close
      $(".aisg-close-modal").on("click", () => {
        $("#aisg-modal").hide();
      });
  
      // Close modal when clicking outside
      $(window).on("click", (event) => {
        if ($(event.target).is("#aisg-modal")) {
          $("#aisg-modal").hide();
        }
      });
  
      // Handle form submission
      $("#aisg-submit-request").on("click", function () {
        const name = $("#aisg-name").val().trim();
        const email = $("#aisg-email").val().trim();
        const message = $("#aisg-message").val().trim();
  
        // Validate form
        if (!name || !email || !message) {
          alert("Please fill in all required fields.");
          return;
        }
  
        // Validate email format
        if (!isValidEmail(email)) {
          alert("Please enter a valid email address.");
          return;
        }
  
        // Show loading state
        $(this).prop("disabled", true).text("Submitting...");
  
        // Prepare data for submission
        const requestData = {
          action: "aisg_submit_request",
          nonce: aisg_data.nonce,
          name: name,
          email: email,
          message: message,
          image_url: imageUrl,
          prompt: generatedPrompt,
          uploaded_sketch: uploadedSketchUrl
        };
  
        console.log("requestData:", requestData); // Log the request data
  
        // Submit form via AJAX
        $.ajax({
          url: aisg_data.ajax_url,
          type: "POST",
          data: requestData,
          success: (response) => {
            $("#aisg-submit-request").prop("disabled", false).text("Submit Request");
  
            if (response.success) {
              // Clear form
              $("#aisg-name, #aisg-email, #aisg-message").val("");
  
              // Show success message
              $("#aisg-form-message")
                .text(response.data.message || "Your request has been submitted successfully!")
                .show();
  
              // Hide success message after 5 seconds
              setTimeout(() => {
                $("#aisg-modal").hide();
                $("#aisg-form-message").hide(); 
                showThankYouModal(); // Show the Thank You modal
              }, 1000);
            } else {
              alert(response.data.message || "An error occurred while submitting your request.");
            }
          },
          error: () => {
            $("#aisg-submit-request").prop("disabled", false).text("Submit Request");
            alert("An error occurred while connecting to the server.");
          },
        });
      });
    } else {
      // Update selected image
      $("#aisg-selected-image").attr("src", imageUrl);
  
      // Update uploaded sketch if available
      if (uploadedSketchUrl) {
        if ($(".aisg-uploaded-sketch-modal").length === 0) {
          const $sketchContainer = $(`
            <div class="aisg-uploaded-sketch-modal">
              <h4>Your Uploaded Sketch:</h4>
              <img src="${uploadedSketchUrl}" alt="Uploaded Sketch">
            </div>
          `);
          $sketchContainer.insertAfter(".aisg-selected-image-container");
        } else {
          $(".aisg-uploaded-sketch-modal img").attr("src", uploadedSketchUrl);
        }
      }
    }
  
    // Show modal
    $("#aisg-modal").show();
  }

  // Show Thank You modal
  function showThankYouModal() {
    const $thankYouModal = $(`
      <div id="aisg-modal-thank-you" class="aisg-modal-thank-you">
        <div class="aisg-modal-thank-you-content">
          <span class="aisg-modal-thank-you-close">&times;</span>
          <h2 class="aisg-modal-thank-you-title">Thank You!</h2>
          <p class="aisg-modal-thank-you-message">Thank you for using our AI service.</p>
          <p class="aisg-modal-thank-you-message">Please check your email for further development information and payment details.</p>
        </div>
      </div>
    `);

    $("body").append($thankYouModal);

    // Handle modal close
    $(".aisg-modal-thank-you-close").on("click", () => {
      $("#aisg-modal-thank-you").fadeOut();
    });

    // Close modal when clicking outside
    $(window).on("click", (event) => {
      if ($(event.target).is("#aisg-modal-thank-you")) {
        $("#aisg-modal-thank-you").fadeOut();
      }
    });

    // Show Thank You modal with fade in
    $("#aisg-modal-thank-you").fadeIn();
  }

  // Helper function to show error messages
  function showError(message) {
    $errorContainer.html(message).show()
  }

  // Helper function to validate email
  function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return regex.test(email)
  }

  // Update progress and status
  function updateProgress(progress, status) {
    $(".aisg-spinner-progress").text(`${Math.round(progress)}%`);
    $("#aisg-status").text(status);
  }

  // Get status message based on progress
  function getStatusMessage(progress) {
    if (progress < 30) {
      return "Analyzing image...";
    } else if (progress < 70) {
      return "Applying filters...";
    } else if (progress < 90) {
      return "Finalizing render...";
    } else {
      return "Almost there...";
    }
  }
})

