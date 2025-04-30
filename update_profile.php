<?php
ob_start();
include 'db_controller.php';
session_start();

if (!isset($_SESSION['logged_account'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userEmail = filter_var($_SESSION['logged_account']['email'] ?? '', FILTER_SANITIZE_EMAIL);
$userData = [];
$error = '';
$success = '';

// Get current user data
try {
    $conn->select_db("atharv");
    $stmt = $conn->prepare("SELECT * FROM user_table WHERE email = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $error = "Error loading profile data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed";
    } else {
        try {
            // Get form data
            $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            $dob = $_POST['dob'] ?? null;
            $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
            $contactNumber = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
            $hometown = filter_input(INPUT_POST, 'hometown', FILTER_SANITIZE_STRING);
            $currentLocation = filter_input(INPUT_POST, 'current_location', FILTER_SANITIZE_STRING);
            $jobPosition = filter_input(INPUT_POST, 'job_position', FILTER_SANITIZE_STRING);
            $qualification = filter_input(INPUT_POST, 'qualification', FILTER_SANITIZE_STRING);
            $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
            $university = filter_input(INPUT_POST, 'university', FILTER_SANITIZE_STRING);
            $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_STRING);
            $removeResume = isset($_POST['remove_resume']);

            // Handle profile picture upload
            $profileImage = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                $targetDir = "uploads/profile_pics/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $fileName = basename($_FILES['profile_picture']['name']);
                $targetFilePath = $targetDir . $fileName;
                $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                $check = getimagesize($_FILES['profile_picture']['tmp_name']);
                if ($check === false) throw new Exception("File is not an image");
                if ($_FILES['profile_picture']['size'] > 2000000) throw new Exception("File is too large (max 2MB)");

                $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
                if (!in_array($fileType, $allowTypes)) throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");

                $newFileName = uniqid() . '.' . $fileType;
                $targetFilePath = $targetDir . $newFileName;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
                    $profileImage = $newFileName;
                    // Delete old profile picture if it exists
                    if (!empty($userData['profile_image']) && file_exists($targetDir . $userData['profile_image'])) {
                        unlink($targetDir . $userData['profile_image']);
                    }
                } else {
                    throw new Exception("Error uploading file");
                }
            }

            // Handle resume upload
            $resumeFile = null;
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
                $resumeDir = "uploads/resumes/";
                if (!file_exists($resumeDir)) {
                    mkdir($resumeDir, 0755, true);
                }

                $fileName = basename($_FILES['resume']['name']);
                $targetFilePath = $resumeDir . $fileName;
                $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                if ($_FILES['resume']['size'] > 5000000) throw new Exception("Resume file is too large (max 5MB)");

                $allowTypes = array('pdf', 'doc', 'docx');
                if (!in_array($fileType, $allowTypes)) throw new Exception("Only PDF, DOC, and DOCX files are allowed for resumes");

                $newFileName = uniqid() . '.' . $fileType;
                $targetFilePath = $resumeDir . $newFileName;

                if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetFilePath)) {
                    $resumeFile = $newFileName;
                    // Delete old resume if it exists
                    if (!empty($userData['resume']) && file_exists($resumeDir . $userData['resume'])) {
                        unlink($resumeDir . $userData['resume']);
                    }
                } else {
                    throw new Exception("Error uploading resume");
                }
            } elseif ($removeResume && !empty($userData['resume'])) {
                // Handle resume removal
                $resumeDir = "uploads/resumes/";
                if (file_exists($resumeDir . $userData['resume'])) {
                    unlink($resumeDir . $userData['resume']);
                }
                $resumeFile = ''; // Empty string to clear the database field
            }

            // Build the UPDATE query dynamically based on provided fields
            $updateFields = [];
            $params = [];
            $types = '';

            // Required fields
            $updateFields[] = "first_name = ?";
            $params[] = $firstName;
            $types .= 's';

            $updateFields[] = "last_name = ?";
            $params[] = $lastName;
            $types .= 's';

            $updateFields[] = "hometown = ?";
            $params[] = $hometown;
            $types .= 's';

            // Optional fields
            if (!empty($dob)) {
                $updateFields[] = "dob = ?";
                $params[] = $dob;
                $types .= 's';
            }

            if (!empty($gender)) {
                $updateFields[] = "gender = ?";
                $params[] = $gender;
                $types .= 's';
            }

            if (!empty($contactNumber)) {
                $updateFields[] = "contact_number = ?";
                $params[] = $contactNumber;
                $types .= 's';
            }

            if (!empty($currentLocation)) {
                $updateFields[] = "current_location = ?";
                $params[] = $currentLocation;
                $types .= 's';
            }

            if (!empty($profileImage)) {
                $updateFields[] = "profile_image = ?";
                $params[] = $profileImage;
                $types .= 's';
            }

            if (!empty($jobPosition)) {
                $updateFields[] = "job_position = ?";
                $params[] = $jobPosition;
                $types .= 's';
            }

            if (!empty($qualification)) {
                $updateFields[] = "qualification = ?";
                $params[] = $qualification;
                $types .= 's';
            }

            if (!empty($year)) {
                $updateFields[] = "year = ?";
                $params[] = $year;
                $types .= 'i';
            }

            if (!empty($university)) {
                $updateFields[] = "university = ?";
                $params[] = $university;
                $types .= 's';
            }

            if (!empty($company)) {
                $updateFields[] = "company = ?";
                $params[] = $company;
                $types .= 's';
            }

            // Handle resume separately
            if ($resumeFile !== null) {
                $updateFields[] = "resume = ?";
                $params[] = $resumeFile;
                $types .= 's';
            } elseif ($removeResume) {
                $updateFields[] = "resume = ''";
            }

            // Add email to params for WHERE clause
            $params[] = $userEmail;
            $types .= 's';

            // Build the final query
            $query = "UPDATE user_table SET " . implode(', ', $updateFields) . " WHERE email = ?";

            // Prepare and execute
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['flash'] = "Profile updated successfully!";
                $_SESSION['flash_mode'] = "alert-success";
                header('Location: view_profile.php');
                exit();
            } else {
                throw new Exception("Failed to update profile: " . $stmt->error);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .profile-pic, .resume-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .resume-preview {
            border-radius: 10px;
            height: auto;
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
        }
        .upload-btn {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .upload-btn input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            cursor: pointer;
        }
        .file-icon {
            font-size: 3rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-person-gear me-2"></i>Update Profile</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="text-center mb-4">
                                <img src="<?= !empty($userData['profile_image']) ? 'uploads/profile_pics/' . htmlspecialchars($userData['profile_image']) : 'https://via.placeholder.com/150?text=Profile' ?>" 
                                     class="profile-pic mb-3" 
                                     alt="Profile Picture" id="profilePicPreview">
                                <div>
                                    <label class="btn btn-primary upload-btn">
                                        <i class="bi bi-camera"></i> Change Photo
                                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*">
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($userData['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($userData['last_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dob" name="dob" 
                                           value="<?= htmlspecialchars($userData['dob'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="Male" <?= ($userData['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($userData['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($userData['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                                           value="<?= htmlspecialchars($userData['contact_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="hometown" class="form-label">Hometown</label>
                                    <input type="text" class="form-control" id="hometown" name="hometown" 
                                           value="<?= htmlspecialchars($userData['hometown'] ?? '') ?>" required>
                                </div>
                                
                                <div class="col-12">
                                    <label for="current_location" class="form-label">Current Location</label>
                                    <input type="text" class="form-control" id="current_location" name="current_location" 
                                           value="<?= htmlspecialchars($userData['current_location'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="job_position" class="form-label">Job Position</label>
                                    <input type="text" class="form-control" id="job_position" name="job_position" 
                                           value="<?= htmlspecialchars($userData['job_position'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="qualification" class="form-label">Qualification</label>
                                    <input type="text" class="form-control" id="qualification" name="qualification" 
                                           value="<?= htmlspecialchars($userData['qualification'] ?? '') ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="year" class="form-label">Graduation Year</label>
                                    <input type="number" class="form-control" id="year" name="year" min="1900" max="2099" 
                                           value="<?= htmlspecialchars($userData['year'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="university" class="form-label">University</label>
                                    <input type="text" class="form-control" id="university" name="university" 
                                           value="<?= htmlspecialchars($userData['university'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="company" class="form-label">Company</label>
                                    <input type="text" class="form-control" id="company" name="company" 
                                           value="<?= htmlspecialchars($userData['company'] ?? '') ?>">
                                </div>
                                
                                <!-- Resume Section -->
                                <div class="col-12 mt-4">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0"><i class="bi bi-file-earmark-pdf"></i> Resume/CV</h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($userData['resume'])): ?>
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="resume-preview me-3">
                                                        <i class="bi bi-file-earmark-pdf-fill file-icon"></i>
                                                        <div class="small"><?= htmlspecialchars($userData['resume']) ?></div>
                                                    </div>
                                                    <div>
                                                        <a href="uploads/resumes/<?= htmlspecialchars($userData['resume']) ?>" 
                                                           target="_blank" class="btn btn-outline-primary me-2">
                                                            <i class="bi bi-download"></i> Download
                                                        </a>
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" id="remove_resume" name="remove_resume">
                                                            <label class="form-check-label" for="remove_resume">
                                                                Remove current resume
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="resume" class="form-label"><?= empty($userData['resume']) ? 'Upload' : 'Update' ?> Resume (PDF, DOC, DOCX)</label>
                                                <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                                                <div class="form-text">Max file size: 5MB</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="bi bi-save"></i> Save Changes
                                    </button>
                                    <a href="view_profile.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview profile picture before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profilePicPreview').src = event.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Show selected resume filename
        document.getElementById('resume').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                alert('Selected file: ' + fileName + '\nClick Save Changes to upload');
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>