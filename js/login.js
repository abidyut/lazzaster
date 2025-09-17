
    
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const login = document.getElementById('login').value;
            const password = document.getElementById('login-password').value;
            
            // Get stored user data (in a real app, this would be done on the server)
            const storedData = localStorage.getItem('userData');
            
            if (storedData) {
                const userData = JSON.parse(storedData);
                
                // Check credentials (in a real app, this would be done on the server)
                if ((login === userData.username || login === userData.email) && password === userData.password) {
                    // Show success message
                    alert('লগইন সফল! ড্যাশবোর্ডে redirect করা হচ্ছে...');
                    
                    // Store login status
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('currentUser', JSON.stringify(userData));
                    
                    // Redirect to dashboard after 2 seconds
                    setTimeout(function() {
                        window.location.href = 'index.html';
                    }, 2000);
                } else {
                    document.getElementById('login-error').style.display = 'block';
                    document.getElementById('password-error').style.display = 'block';
                }
            } else {
                document.getElementById('login-error').style.display = 'block';
                document.getElementById('password-error').style.display = 'block';
            }
        });
    