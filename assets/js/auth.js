/**
 * Authentication Forms Functionality
 * 
 * Handles form validation and interaction for login and registration
 */
document.addEventListener('DOMContentLoaded', function() {
  // Password visibility toggle
  const togglePasswordButtons = document.querySelectorAll('.toggle-password');
  
  togglePasswordButtons.forEach(button => {
    button.addEventListener('click', function() {
      const passwordInput = this.previousElementSibling;
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      const icon = this.querySelector('i');
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  });

  // Form element references
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirm-password');
  const registerForm = document.getElementById('register-form');
  const emailInput = document.getElementById('email');
  const usernameInput = document.getElementById('username');
  
  // Form validation state
  const validationState = {
    password: false,
    passwordMatch: false,
    email: false,
    username: false
  };
  
  // Password validation
  const validatePassword = () => {
    if (!passwordInput || !document.getElementById('length-check')) {
      return false;
    }
    
    const password = passwordInput.value;
    let isValid = true;
    
    const lengthCheck = document.getElementById('length-check');
    const uppercaseCheck = document.getElementById('uppercase-check');
    const lowercaseCheck = document.getElementById('lowercase-check');
    const numberCheck = document.getElementById('number-check');
    const specialCheck = document.getElementById('special-check');
    
    // Check length (at least 8 characters)
    if (password.length >= 8) {
      lengthCheck.classList.add('valid');
      lengthCheck.querySelector('i').classList.replace('fa-times-circle', 'fa-check-circle');
    } else {
      lengthCheck.classList.remove('valid');
      lengthCheck.querySelector('i').classList.replace('fa-check-circle', 'fa-times-circle');
      isValid = false;
    }
    
    // Check for uppercase letter
    if (/[A-Z]/.test(password)) {
      uppercaseCheck.classList.add('valid');
      uppercaseCheck.querySelector('i').classList.replace('fa-times-circle', 'fa-check-circle');
    } else {
      uppercaseCheck.classList.remove('valid');
      uppercaseCheck.querySelector('i').classList.replace('fa-check-circle', 'fa-times-circle');
      isValid = false;
    }
    
    // Check for lowercase letter
    if (/[a-z]/.test(password)) {
      lowercaseCheck.classList.add('valid');
      lowercaseCheck.querySelector('i').classList.replace('fa-times-circle', 'fa-check-circle');
    } else {
      lowercaseCheck.classList.remove('valid');
      lowercaseCheck.querySelector('i').classList.replace('fa-check-circle', 'fa-times-circle');
      isValid = false;
    }
    
    // Check for number
    if (/[0-9]/.test(password)) {
      numberCheck.classList.add('valid');
      numberCheck.querySelector('i').classList.replace('fa-times-circle', 'fa-check-circle');
    } else {
      numberCheck.classList.remove('valid');
      numberCheck.querySelector('i').classList.replace('fa-check-circle', 'fa-times-circle');
      isValid = false;
    }
    
    // Check for special character
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
      specialCheck.classList.add('valid');
      specialCheck.querySelector('i').classList.replace('fa-times-circle', 'fa-check-circle');
    } else {
      specialCheck.classList.remove('valid');
      specialCheck.querySelector('i').classList.replace('fa-check-circle', 'fa-times-circle');
      isValid = false;
    }

    validationState.password = isValid;
    return isValid;
  };
  
  // Email validation
  const validateEmail = () => {
    if (!emailInput) return true;
    
    const isValid = emailInput.value && /.*@mcmaster\.ca$/.test(emailInput.value);
    
    const existingEmailFeedback = emailInput.parentElement.parentElement.querySelector('.email-feedback');
    if (existingEmailFeedback) {
      existingEmailFeedback.remove();
    }
    
    if (emailInput.value) {
      if (!isValid) {
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'email-feedback input-help';
        feedbackDiv.textContent = 'Must be a valid McMaster email address (@mcmaster.ca)';
        feedbackDiv.style.color = 'var(--color-error)';
        emailInput.style.borderColor = 'var(--color-error)';
        
        const defaultHelp = emailInput.parentElement.parentElement.querySelector('.input-help');
        if (defaultHelp) {
          defaultHelp.after(feedbackDiv);
        } else {
          emailInput.parentElement.parentElement.appendChild(feedbackDiv);
        }
      } else {
        emailInput.style.borderColor = 'var(--color-success)';
      }
    } else {
      emailInput.style.borderColor = '';
    }
    
    validationState.email = isValid;
    return isValid;
  };
  
  // Username validation
  const validateUsername = () => {
    if (!usernameInput) return true;
    
    const isValid = usernameInput.value && /^[a-zA-Z0-9_]{3,20}$/.test(usernameInput.value);
    
    const existingUsernameFeedback = usernameInput.parentElement.parentElement.querySelector('.username-feedback');
    if (existingUsernameFeedback) {
      existingUsernameFeedback.remove();
    }
    
    if (usernameInput.value) {
      if (!isValid) {
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'username-feedback input-help';
        
        if (usernameInput.value.length < 3 || usernameInput.value.length > 20) {
          feedbackDiv.textContent = 'Username must be between 3-20 characters';
        } else {
          feedbackDiv.textContent = 'Username can only contain letters, numbers, and underscores';
        }
        
        feedbackDiv.style.color = 'var(--color-error)';
        usernameInput.style.borderColor = 'var(--color-error)';
        
        const defaultHelp = usernameInput.parentElement.parentElement.querySelector('.input-help');
        if (defaultHelp) {
          defaultHelp.after(feedbackDiv);
        } else {
          usernameInput.parentElement.parentElement.appendChild(feedbackDiv);
        }
      } else {
        usernameInput.style.borderColor = 'var(--color-success)';
      }
    } else {
      usernameInput.style.borderColor = '';
    }
    
    validationState.username = isValid;
    return isValid;
  };
  
  // Confirm password validation
  const validatePasswordMatch = () => {
    if (!passwordInput || !confirmPasswordInput) return true;
    
    const existingMismatchError = document.querySelector('.password-mismatch-feedback');
    if (existingMismatchError) {
      existingMismatchError.remove();
    }
    
    if (passwordInput.value && confirmPasswordInput.value) {
      const isMatch = passwordInput.value === confirmPasswordInput.value;
      const feedbackDiv = document.createElement('div');
      feedbackDiv.className = 'password-mismatch-feedback input-help';
      
      if (!isMatch) {
        feedbackDiv.textContent = 'Passwords do not match';
        feedbackDiv.style.color = 'var(--color-error)';
        confirmPasswordInput.style.borderColor = 'var(--color-error)';
      } else {
        feedbackDiv.textContent = 'Passwords match';
        feedbackDiv.style.color = 'var(--color-success)';
        confirmPasswordInput.style.borderColor = 'var(--color-success)';
      }
      
      confirmPasswordInput.parentElement.parentElement.appendChild(feedbackDiv);
      
      validationState.passwordMatch = isMatch;
      return isMatch;
    }
    
    validationState.passwordMatch = false;
    return false;
  };
  
  // Set up event listeners for validation
  if (passwordInput) {
    passwordInput.addEventListener('input', () => {
      validatePassword();
      if (confirmPasswordInput && confirmPasswordInput.value) {
        validatePasswordMatch();
      }
    });
  }
  
  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', validatePasswordMatch);
  }
  
  if (emailInput) {
    emailInput.addEventListener('input', validateEmail);
  }
  
  if (usernameInput) {
    usernameInput.addEventListener('input', validateUsername);
  }
  
  // Form submission validation
  if (registerForm) {
    registerForm.addEventListener('submit', function(event) {
      const existingError = document.querySelector('.error-message');
      if (existingError) {
        existingError.remove();
      }
      
      const passwordValid = validatePassword();
      const passwordsMatch = validatePasswordMatch();
      const emailValid = validateEmail();
      const usernameValid = validateUsername();
      
      const isFormValid = passwordValid && passwordsMatch && emailValid && usernameValid;
      
      if (!isFormValid) {
        event.preventDefault();
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        
        if (!passwordValid) {
          errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password does not meet all requirements.';
        } else if (!passwordsMatch) {
          errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match.';
        } else if (!emailValid && emailInput.value) {
          errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid McMaster email address.';
        } else if (!usernameValid && usernameInput.value) {
          errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid username.';
        } else {
          errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please fill out all required fields correctly.';
        }
        
        registerForm.parentNode.insertBefore(errorDiv, registerForm);
        registerForm.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }
  
  // Auto-hide error and success messages after 5 seconds
  const messages = document.querySelectorAll('.error-message, .success-message');
  messages.forEach(message => {
    setTimeout(() => {
      message.style.opacity = '0';
      message.style.transition = 'opacity 0.5s ease';
      setTimeout(() => {
        if (message.parentNode) {
          message.parentNode.removeChild(message);
        }
      }, 500);
    }, 5000);
  });
});