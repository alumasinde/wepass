<?php $user = $user ?? []; ?>

<div class="container">

    <h2><?= htmlspecialchars($title ?? 'My Profile') ?></h2>

    <p><a href="/settings/delegation"><i class="fa-solid fa-user-clock"></i> Manage My Delegate</a></p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/settings/users/profile">
        <?= csrf_field() ?>

        <div class="card">
            <div class="card-body">

                <h4>Profile Information</h4>

                <div class="form-group">
                    <label>First Name</label>
                    <input 
                        type="text" 
                        name="first_name" 
                        class="form-control"
                        value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input 
                        type="text" 
                        name="last_name" 
                        class="form-control"
                        value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control"
                        value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                        required
                    >
                </div>

            </div>
        </div>

        <br>

        <div class="card">
            <div class="card-body">

                <h4>Change Password</h4>
                <p class="text-muted">Leave blank if you don't want to change your password.</p>

                <div class="form-group">
                    <label>Current Password</label>
                    <input 
                        type="password" 
                        name="current_password" 
                        class="form-control"
                    >
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input 
                        type="password" 
                        name="new_password" 
                        class="form-control"
                    >
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        class="form-control"
                    >
                </div>

            </div>
        </div>

        <br>

        <button type="submit" class="btn btn-primary">
            Update Profile
        </button>

    <a href="/dashboard" class="btn btn-secondary">Cancel</a>



    </form>

</div>