<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/cloud_storage.php';



$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user info
$stmt = $conn->prepare("SELECT full_name, email, phone, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found");
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    verify_csrf();
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if (!preg_match('/^[\p{L}\p{M} .\'-]{2,100}$/u', $full_name) || !preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
        $error = 'Enter a valid name and phone number.';
    }

    // Handle profile pic upload
    $profile_pic = $user['profile_pic']; // current pic
    $originalProfilePic = $user['profile_pic'];
    $newProfileReference = '';
    if (!$error && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $maxFileSize = 2 * 1024 * 1024;
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        if ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
            $error = "The profile picture could not be uploaded.";
        } elseif ($_FILES['profile_pic']['size'] > $maxFileSize) {
            $error = "Profile pictures must be 2 MB or smaller.";
        } else {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $_FILES['profile_pic']['tmp_name']);
            finfo_close($fileInfo);
            $imageInfo = @getimagesize($_FILES['profile_pic']['tmp_name']);
            if (!$imageInfo || !isset($allowedMimeTypes[$mimeType])) {
                $error = "Please upload a JPG, PNG, GIF, or WebP image.";
            } else {
                $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
                $objectKey = 'profiles/' . $fileName;
                $storedFile = liteup_store_uploaded_file($_FILES['profile_pic']['tmp_name'], $objectKey, $mimeType);
                if ($storedFile !== false) {
                    $profile_pic = $storedFile;
                    $newProfileReference = $storedFile;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            }
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, profile_pic = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $phone, $profile_pic, $user_id);
        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
            $user['full_name'] = $full_name;
            $user['phone'] = $phone;
            $user['profile_pic'] = $profile_pic;
            if ($newProfileReference !== '' && $originalProfilePic !== '' && $originalProfilePic !== $newProfileReference) {
                liteup_delete_stored_file($originalProfilePic);
            }
        } else {
            $error = "Failed to update profile.";
            if ($newProfileReference !== '') {
                liteup_delete_stored_file($newProfileReference);
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    verify_csrf();
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $passwordError = validate_password($new_password);

    if ($passwordError) {
        $error = $passwordError;
    } elseif (empty($current_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Fetch hashed password from DB
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (password_verify($current_password, $row['password'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_new_password, $user_id);
            if ($stmt->execute()) {
                $message = "Password changed successfully.";
                $deleteDevices = $conn->prepare('DELETE FROM trusted_devices WHERE user_id = ?');
                $deleteDevices->bind_param('i', $user_id);
                $deleteDevices->execute();
                session_regenerate_id(true);
            } else {
                $error = "Failed to change password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile - LiteUp</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        .profile-pic {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        .profile-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #172b4d, #22a6f2);
            font-size: 2.25rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<?php include 'navbar.php'; ?>

<main class="container flex-grow-1 my-5" style="max-width: 600px;">
    <h3>My Profile</h3>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <?= csrf_field() ?>
        <div class="text-center mb-4">
            <?php if ($user['profile_pic']): ?>
                <img src="media.php" alt="Profile Picture" class="profile-pic">
            <?php else: ?>
                <div class="profile-pic profile-placeholder" aria-label="No profile picture"><i class="fas fa-user"></i></div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label for="full_name" class="form-label">Full Name</label>
            <input type="text" name="full_name" id="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="profile_pic" class="form-label">Profile Picture (optional)</label>
            <input type="file" name="profile_pic" id="profile_pic" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="form-text">JPG, PNG, GIF, or WebP. Maximum size: 2 MB.</div>
        </div>
        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
    </form>

    <hr>

    

    <a href="dashboard.php" class="btn btn-secondary mt-3">â¬… Back to Dashboard</a>
</main>

<?php include '../includes/footer.php'; ?>

</body>
</html>
