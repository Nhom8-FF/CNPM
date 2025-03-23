document.addEventListener("DOMContentLoaded", function () {
  // Get modal elements
  var modal = document.getElementById("auth-modal");
  var closeBtn = document.querySelector(".close");
  var loginForm = document.getElementById("login-form");
  var signupForm = document.getElementById("signup-form");
  var forgotForm = document.getElementById("forgot-form");
  var loginLink = document.querySelector(".btn-login");
  var signupLink = document.getElementById("signup-link");
  var loginBackLink = document.getElementById("login-back-link");
  var forgotLink = document.getElementById("forgot-link");
  var forgotBackLink = document.getElementById("forgot-back-link");
  
  // Debug function to log information
  function logDebug(message, data = null) {
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      console.log(`[Auth Debug] ${message}`, data || '');
    }
  }
  
  logDebug("Auth Modal initialized", {
    modal: !!modal,
    loginForm: !!loginForm,
    signupForm: !!signupForm,
    forgotForm: !!forgotForm
  });
  
  // Show/hide forms
  function showForm(form) {
    // First hide all forms
    if (loginForm) loginForm.style.display = "none";
    if (signupForm) signupForm.style.display = "none";
    if (forgotForm) forgotForm.style.display = "none";
    
    // Then show the requested form
    if (form) form.style.display = "block";
    
    logDebug("Showing form", form?.id);
  }
  
  // Open modal with login form
  function openLoginModal() {
    if (modal) {
      modal.style.display = "block";
      showForm(loginForm);
      
      // Add animation class
      modal.classList.add("fade-in");
      setTimeout(() => {
        if (modal.querySelector(".modal-content")) {
          modal.querySelector(".modal-content").classList.add("slide-in");
        }
      }, 100);
      
      logDebug("Modal opened");
    } else {
      console.error("Auth modal element not found!");
    }
  }
  
  // Close modal function
  function closeModal() {
    if (modal) {
      // Add animation classes for closing
      modal.classList.add("fade-out");
      if (modal.querySelector(".modal-content")) {
        modal.querySelector(".modal-content").classList.add("slide-out");
      }
      
      // Wait for animation to complete before hiding
      setTimeout(() => {
        modal.style.display = "none";
        modal.classList.remove("fade-in", "fade-out");
        if (modal.querySelector(".modal-content")) {
          modal.querySelector(".modal-content").classList.remove("slide-in", "slide-out");
        }
      }, 300);
      
      logDebug("Modal closed");
    }
  }
  
  // Register event listeners for modal controls
  if (loginLink) {
    loginLink.addEventListener("click", function (e) {
      e.preventDefault();
      openLoginModal();
    });
    logDebug("Login link event listener added");
  } else {
    console.error("Login link element not found!");
  }
  
  // Close button click
  if (closeBtn) {
    closeBtn.addEventListener("click", closeModal);
    logDebug("Close button event listener added");
  } else {
    console.error("Close button element not found!");
  }
  
  // Click outside to close
  window.addEventListener("click", function (e) {
    if (e.target === modal) {
      closeModal();
    }
  });
  
  // Switch between forms
  if (signupLink) {
    signupLink.addEventListener("click", function (e) {
      e.preventDefault();
      showForm(signupForm);
    });
    logDebug("Signup link event listener added");
  }
  
  if (loginBackLink) {
    loginBackLink.addEventListener("click", function (e) {
      e.preventDefault();
      showForm(loginForm);
    });
    logDebug("Login back link event listener added");
  }
  
  if (forgotLink) {
    forgotLink.addEventListener("click", function (e) {
      e.preventDefault();
      showForm(forgotForm);
    });
    logDebug("Forgot link event listener added");
  }
  
  if (forgotBackLink) {
    forgotBackLink.addEventListener("click", function (e) {
      e.preventDefault();
      showForm(loginForm);
    });
    logDebug("Forgot back link event listener added");
  }
  
  // Add CSS for animations if it doesn't exist
  if (!document.getElementById("modal-animations")) {
    const style = document.createElement("style");
    style.id = "modal-animations";
    style.textContent = `
      .fade-in { animation: fadeIn 0.3s ease-in-out; }
      .fade-out { animation: fadeOut 0.3s ease-in-out; }
      .slide-in { animation: slideIn 0.3s ease-out; }
      .slide-out { animation: slideOut 0.3s ease-in; }
      
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
      }
      
      @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      
      @keyframes slideOut {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(50px); opacity: 0; }
      }
      
      #auth-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 2000;
        overflow-y: auto;
        padding: 2rem 0;
      }
      
      .modal-content {
        background: var(--surface-color);
        margin: 2rem auto;
        width: 90%;
        max-width: 450px;
        border-radius: var(--border-radius-lg);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        position: relative;
        overflow: hidden;
      }
      
      .close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: var(--text-secondary);
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10;
        transition: color var(--transition-fast);
      }
      
      .close:hover {
        color: var(--primary-color);
      }
      
      .auth-form {
        padding: 2.5rem;
      }
      
      .auth-form h2 {
        font-size: 1.8rem;
        margin-bottom: 1.5rem;
        color: var(--text-color);
        text-align: center;
        font-weight: 600;
      }
      
      .auth-input-group {
        position: relative;
        margin-bottom: 1.75rem;
      }
      
      .auth-input-group i {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
      }
      
      .auth-input {
        width: 100%;
        padding: 0.85rem 1rem 0.85rem 3rem;
        border-radius: var(--border-radius-md);
        background: var(--background-color);
        border: 1px solid rgba(99, 102, 241, 0.1);
        color: var(--text-color);
        font-size: 1rem;
        transition: all var(--transition-fast);
      }
      
      .auth-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
      }
      
      .auth-submit {
        width: 100%;
        padding: 0.85rem;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--border-radius-md);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition-normal);
        margin-top: 1rem;
      }
      
      .auth-submit:hover {
        background: var(--primary-color-hover);
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(99, 102, 241, 0.3);
      }
      
      .auth-footer {
        margin-top: 1.5rem;
        text-align: center;
        font-size: 0.95rem;
        color: var(--text-secondary);
      }
      
      .auth-footer .link {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
        transition: color var(--transition-fast);
      }
      
      .auth-footer .link:hover {
        color: var(--primary-color-hover);
        text-decoration: underline;
      }
      
      .error-message, .success-message {
        padding: 1rem;
        border-radius: var(--border-radius-sm);
        margin-bottom: 1.5rem;
        text-align: center;
        font-weight: 500;
        animation: fadeIn 0.3s ease;
      }
      
      .error-message {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error-color);
        border: 1px solid rgba(239, 68, 68, 0.2);
      }
      
      .success-message {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
        border: 1px solid rgba(16, 185, 129, 0.2);
      }
    `;
    document.head.appendChild(style);
    logDebug("Added modal animations styles");
  }
  
  // Helper to show loading state in form
  function setFormLoading(form, isLoading) {
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submitBtn) {
      if (isLoading) {
        submitBtn.setAttribute('disabled', 'disabled');
        submitBtn.dataset.originalText = submitBtn.innerText;
        submitBtn.innerText = 'Processing...';
      } else {
        submitBtn.removeAttribute('disabled');
        if (submitBtn.dataset.originalText) {
          submitBtn.innerText = submitBtn.dataset.originalText;
        }
      }
    }
  }
  
  // Helper to show message in form
  function showFormMessage(form, type, message) {
    // Remove any existing messages
    const existingMsg = form.querySelector('.form-message');
    if (existingMsg) {
      existingMsg.remove();
    }
    
    // Create message element
    const msgElement = document.createElement('div');
    msgElement.className = `form-message ${type}`;
    msgElement.innerText = message;
    
    // Insert at the top of the form
    form.insertBefore(msgElement, form.firstChild);
    
    // Scroll to view the message
    msgElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  
  // Helper function to safely parse JSON
  function safeJsonParse(text) {
    try {
      return JSON.parse(text);
    } catch (e) {
      logDebug('JSON parse error:', e);
      return null;
    }
  }
  
  // Update form submission function
  function setupFormSubmission() {
    // Get all form elements that should be handled via AJAX
    const forms = document.querySelectorAll('#login-form, #signup-form, #forgot-form');
    
    forms.forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get the form action
        const action = this.getAttribute('action');
        if (!action) {
          showFormMessage(this, 'error', 'Form action is not defined');
          return;
        }
        
        logDebug(`Submitting form to ${action}`, this.id);
        
        // Set loading state
        setFormLoading(this, true);
        
        // Create FormData object
        const formData = new FormData(this);
        
        // Add XHR header to indicate AJAX request
        const headers = {
          'X-Requested-With': 'XMLHttpRequest'
        };
        
        // Make AJAX request
        fetch(action, {
          method: 'POST',
          body: formData,
          headers: headers
        })
        .then(response => {
          logDebug('Response status:', response.status);
          
          // Check content type to determine how to parse the response
          const contentType = response.headers.get('content-type');
          if (contentType && contentType.includes('application/json')) {
            return response.json();
          } else {
            return response.text().then(text => {
              // Try to parse as JSON anyway in case the header is wrong
              const jsonData = safeJsonParse(text);
              if (jsonData) return jsonData;
              
              // If not JSON, create a response object
              return { 
                status: response.ok ? 'success' : 'error',
                message: text || (response.ok ? 'Success' : 'Error occurred'),
              };
            });
          }
        })
        .then(data => {
          logDebug('Response data:', data);
          
          // Handle the response
          if (data.status === 'success') {
            showFormMessage(this, 'success', data.message);
            
            // If redirect is specified, navigate there after a short delay
            if (data.redirect) {
              setTimeout(() => {
                window.location.href = data.redirect;
              }, 1000);
            }
          } else {
            showFormMessage(this, 'error', data.message || 'An error occurred');
          }
        })
        .catch(error => {
          logDebug('Fetch error:', error);
          showFormMessage(this, 'error', 'Could not connect to the server. Please try again later.');
        })
        .finally(() => {
          setFormLoading(this, false);
        });
      });
    });
  }
  
  // Make sure to call setupFormSubmission when the DOM is ready
  setupFormSubmission();
  });