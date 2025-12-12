<?php
require_once 'config/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'department' => sanitize($_POST['department'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? '')
    ];
    
    // Validate
    if (strlen($data['password']) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match';
    } else {
        $result = register($data);
        if ($result['success']) {
            $success = 'Registration successful! You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Resource Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-cubes"></i>
                </div>
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtitle">Join our resource management system</p>
            </div>
            
            <div class="auth-body">
                <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div class="alert-content"><?php echo $error; ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <div class="alert-content"><?php echo $success; ?></div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="d-flex gap-3">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="Kristina Koirala" required 
                                   value="<?php echo $_POST['full_name'] ?? ''; ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Username <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="kristina" required
                                   value="<?php echo $_POST['username'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="kristina@gmail.com" required
                               value="<?php echo $_POST['email'] ?? ''; ?>">
                    </div>
                    
                    <div class="d-flex gap-3">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-control">
                                <option value="">Select Department</option>
                                <option value="IT Department">IT Department</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Finance">Finance</option>
                                <option value="HR">Human Resources</option>
                                <option value="Operations">Operations</option>
                                <option value="Sales">Sales</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="9818235147"
                                   value="<?php echo $_POST['phone'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        </div>
                    </div>
                    
                   
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
            </div>
            
            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
