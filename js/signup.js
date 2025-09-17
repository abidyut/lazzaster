
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const referral = document.getElementById('referral').value;
            
            // Validation
            let isValid = true;
            
            // Username validation (no spaces)
            if (username.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
            
            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                document.getElementById('email-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
            
            // Password validation (at least 6 characters)
            if (password.length < 6) {
                document.getElementById('password-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
            
            // If form is valid, proceed with signup
            if (isValid) {
                // Here you would typically send the data to your backend
                // For demonstration, we'll just store in localStorage and redirect
                
                // Store user data (in a real app, this would be done on the server)
                const userData = {
                    username: username,
                    email: email,
                    password: password, // In real app, password should be hashed
                    referral: referral
                };
                
                localStorage.setItem('userData', JSON.stringify(userData));
                
                // Show success message
                alert('সাইন আপ সফল! লগইন পেজে redirect করা হচ্ছে...');
                
                // Redirect to login page after 2 seconds
                setTimeout(function() {
                    window.location.href = 'login.html';
                }, 2000);
            }
        });
        
        // Real-time validation
        document.getElementById('username').addEventListener('input', function() {
            if (this.value.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
        });
        
        document.getElementById('email').addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(this.value)) {
                document.getElementById('email-error').style.display = 'block';
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                document.getElementById('password-error').style.display = 'block';
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
        });
    </script>
    <script>
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const referral = document.getElementById('referral').value;
            
            // Validation
            let isValid = true;
            
            // Username validation (no spaces)
            if (username.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
            
            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                document.getElementById('email-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
            
            // Password validation (at least 6 characters)
            if (password.length < 6) {
                document.getElementById('password-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
            
            // If form is valid, proceed with signup
            if (isValid) {
                // Here you would typically send the data to your backend
                // For demonstration, we'll just store in localStorage and redirect
                
                // Store user data (in a real app, this would be done on the server)
                const userData = {
                    username: username,
                    email: email,
                    password: password, // In real app, password should be hashed
                    referral: referral
                };
                
                localStorage.setItem('userData', JSON.stringify(userData));
                
                // Show success message
                alert('সাইন আপ সফল! লগইন পেজে redirect করা হচ্ছে...');
                
                // Redirect to login page after 2 seconds
                setTimeout(function() {
                    window.location.href = 'login.html';
                }, 2000);
            }
        });
        
        // Real-time validation
        document.getElementById('username').addEventListener('input', function() {
            if (this.value.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
        });
        
        document.getElementById('email').addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(this.value)) {
                document.getElementById('email-error').style.display = 'block';
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                document.getElementById('password-error').style.display = 'block';
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
        });
 
    <script>
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const referral = document.getElementById('referral').value;
            
            // Validation
            let isValid = true;
            
            // Username validation (no spaces)
            if (username.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
            
            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                document.getElementById('email-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
            
            // Password validation (at least 6 characters)
            if (password.length < 6) {
                document.getElementById('password-error').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
            
            // If form is valid, proceed with signup
            if (isValid) {
                // Here you would typically send the data to your backend
                // For demonstration, we'll just store in localStorage and redirect
                
                // Store user data (in a real app, this would be done on the server)
                const userData = {
                    username: username,
                    email: email,
                    password: password, // In real app, password should be hashed
                    referral: referral
                };
                
                localStorage.setItem('userData', JSON.stringify(userData));
                
                // Show success message
                alert('সাইন আপ সফল! লগইন পেজে redirect করা হচ্ছে...');
                
                // Redirect to login page after 2 seconds
                setTimeout(function() {
                    window.location.href = 'login.html';
                }, 2000);
            }
        });
        
        // Real-time validation
        document.getElementById('username').addEventListener('input', function() {
            if (this.value.includes(' ')) {
                document.getElementById('username-error').style.display = 'block';
            } else {
                document.getElementById('username-error').style.display = 'none';
            }
        });
        
        document.getElementById('email').addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(this.value)) {
                document.getElementById('email-error').style.display = 'block';
            } else {
                document.getElementById('email-error').style.display = 'none';
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                document.getElementById('password-error').style.display = 'block';
            } else {
                document.getElementById('password-error').style.display = 'none';
            }
        });
