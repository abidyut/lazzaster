// Login form handling with API authentication
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get form values
            const login = document.getElementById('login').value;
            const password = document.getElementById('login-password').value;
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'লগইন করা হচ্ছে...';
            submitBtn.disabled = true;
            
            try {
                // Call API for authentication
                const response = await fetch(`${window.location.origin}/api/login.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: login, password: password })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Store auth data
                    localStorage.setItem('authToken', result.token);
                    localStorage.setItem('userData', JSON.stringify(result.user));
                    
                    // Show success message
                    alert('লগইন সফল! হোম পেজে redirect করা হচ্ছে...');
                    
                    // Redirect to home page
                    window.location.href = 'index.html';
                } else {
                    // Show error message
                    alert('Error: ' + result.message);
                    document.getElementById('login-error').style.display = 'block';
                    document.getElementById('password-error').style.display = 'block';
                }
            } catch (error) {
                console.error('Login error:', error);
                alert('নেটওয়ার্ক error. আবার চেষ্টা করুন');
            } finally {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });
    }
});

// Helper functions for authentication status
function isLoggedIn() {
    return localStorage.getItem('authToken') !== null;
}

function getUserData() {
    const userData = localStorage.getItem('userData');
    return userData ? JSON.parse(userData) : null;
}

function logout() {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userData');
    window.location.href = 'index.html';
}