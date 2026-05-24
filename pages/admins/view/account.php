<div class="form-container">
        <form action="userAcc.php" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input type="text" name="firstName" id="firstName" value="<?php echo $user['firstName']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="middleName">Middle Name</label>
                    <input type="text" name="middleName" id="middleName" value="<?php echo $user['middleName']; ?>">
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input type="text" name="lastName" id="lastName" value="<?php echo $user['lastName']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <input type="text" name="status" id="status" value="<?php echo $user['status']; ?>" readonly>
                </div>
            </div>

            <div class="form-row">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo $user['email']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" name="birthday" id="birthday" value="<?php echo $user['birthday']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="text" name="age" id="age" value="<?php echo $age; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select name="sex" id="sex">
                        <option value="Male" <?php echo $user['sex'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $user['sex'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea name="address" id="address"><?php echo $user['address']; ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="profileImage">Profile Image</label>
                    <!-- Display the current image if it exists -->
                    <div class="profile-image-container">
                        <img src="<?php echo $user['image']; ?>" alt="Profile Image" id="currentImage" class="current-image">
                    </div>
                    <input type="file" name="profileImage" id="profileImage" value="<?php echo $user['image']; ?>">
                </div>
                <div class="form-group">
                    <label for="contactNumber">Contact Number</label>
                    <input type="text" name="contactNumber" id="contactNumber" value="<?php echo $user['contactNumber']; ?>">
                </div>
                <div class="form-group">
                    <label for="nationality">Nationality</label>
                    <input type="text" name="nationality" id="nationality" value="<?php echo $user['nationality']; ?>">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn-download" type="submit" name="saveChanges">Save Changes</button>
                <!-- <button class="btn-archive" type="submit" name="archiveAccount" onclick="return confirm('Are you sure you want to archive your account?');">Archive Account</button> -->
            </div>

        </form>
    </div>