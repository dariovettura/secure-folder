jQuery(document).ready(function($) {
    'use strict';
    
    // File upload form
    $('#sfm-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'sfm_upload_file');
        formData.append('nonce', sfm_admin_ajax.nonce);
        
        // Show loading state
        var submitBtn = $(this).find('input[type="submit"]');
        var originalText = submitBtn.val();
        submitBtn.val('Uploading...').prop('disabled', true);
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage('File uploaded successfully!', 'success');
                    // Redirect to files list
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=secure-files-manager';
                    }, 1500);
                } else {
                    showMessage(response.data || 'Upload failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during upload', 'error');
            },
            complete: function() {
                submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // File edit form
    $('#sfm-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=sfm_update_file_roles&nonce=' + sfm_admin_ajax.nonce;
        
        // Show loading state
        var submitBtn = $(this).find('input[type="submit"]');
        var originalText = submitBtn.val();
        submitBtn.val('Updating...').prop('disabled', true);
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('File updated successfully!', 'success');
                } else {
                    showMessage(response.data || 'Update failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during update', 'error');
            },
            complete: function() {
                submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Delete file
    $(document).on('click', '.sfm-delete-file', function(e) {
        e.preventDefault();
        
        if (!confirm(sfm_admin_ajax.strings.confirm_delete)) {
            return;
        }
        
        var fileId = $(this).data('file-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfm_delete_file',
                file_id: fileId,
                nonce: sfm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showMessage('File deleted successfully!', 'success');
                } else {
                    showMessage(response.data || 'Delete failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during deletion', 'error');
            }
        });
    });
    
    // Add role form
    $('#sfm-add-role-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=sfm_create_custom_role&nonce=' + sfm_admin_ajax.nonce;
        
        // Show loading state
        var submitBtn = $(this).find('input[type="submit"]');
        var originalText = submitBtn.val();
        submitBtn.val('Creating...').prop('disabled', true);
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('Role created successfully!', 'success');
                    // Redirect to roles list
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=secure-files-roles';
                    }, 1500);
                } else {
                    showMessage(response.data || 'Role creation failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during role creation', 'error');
            },
            complete: function() {
                submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Update role form
    $('#sfm-update-role-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=sfm_update_custom_role&nonce=' + sfm_admin_ajax.nonce;
        
        // Show loading state
        var submitBtn = $(this).find('input[type="submit"]');
        var originalText = submitBtn.val();
        submitBtn.val('Updating...').prop('disabled', true);
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage('Role updated successfully!', 'success');
                } else {
                    showMessage(response.data || 'Role update failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during role update', 'error');
            },
            complete: function() {
                submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Delete role
    $(document).on('click', '.sfm-delete-role', function(e) {
        e.preventDefault();
        
        if (!confirm(sfm_admin_ajax.strings.confirm_delete_role)) {
            return;
        }
        
        var roleId = $(this).data('role-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: sfm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfm_delete_custom_role',
                role_id: roleId,
                nonce: sfm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showMessage('Role deleted successfully!', 'success');
                } else {
                    showMessage(response.data || 'Role deletion failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during role deletion', 'error');
            }
        });
    });
    
    // File type validation
    $('#file').on('change', function() {
        var file = this.files[0];
        if (file) {
            var maxSize = 10485760; // 10MB default
            var allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
            var fileExt = file.name.split('.').pop().toLowerCase();
            
            if (file.size > maxSize) {
                showMessage('File size exceeds maximum allowed size', 'error');
                $(this).val('');
                return;
            }
            
            if (allowedTypes.indexOf(fileExt) === -1) {
                showMessage('File type not allowed', 'error');
                $(this).val('');
                return;
            }
        }
    });
    
    // Role name validation
    $('#role_name').on('input', function() {
        var value = $(this).val();
        var sanitized = value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
        $(this).val(sanitized);
    });
    
    // Auto-fill display name from role name
    $('#role_name').on('blur', function() {
        var roleName = $(this).val();
        var displayName = $('#role_display_name');
        
        if (roleName && !displayName.val()) {
            displayName.val(roleName.replace(/[-_]/g, ' ').replace(/\b\w/g, function(l) {
                return l.toUpperCase();
            }));
        }
    });
    
    // Show/hide capabilities based on role type
    $('input[name="role_type"]').on('change', function() {
        var roleType = $(this).val();
        var capabilitiesDiv = $('.sfm-capabilities');
        
        if (roleType === 'basic') {
            capabilitiesDiv.find('input[value="sfm_view_secure_files"]').prop('checked', true);
            capabilitiesDiv.find('input[value="sfm_download_files"]').prop('checked', true);
        } else if (roleType === 'advanced') {
            capabilitiesDiv.find('input[value="sfm_upload_files"]').prop('checked', true);
        } else if (roleType === 'admin') {
            capabilitiesDiv.find('input[type="checkbox"]').prop('checked', true);
        }
    });
    
    // Bulk actions
    $('#bulk-action-selector-top').on('change', function() {
        var action = $(this).val();
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete the selected files?')) {
                $(this).val('');
                return;
            }
        }
    });
    
    // Search functionality
    $('#sfm-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.sfm-file-item, .sfm-role-item').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(searchTerm) === -1) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    });
    
    // Toggle all checkboxes
    $('#toggle-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.sfm-checkbox').prop('checked', isChecked);
    });
    
    // Show message function
    function showMessage(message, type) {
        var messageClass = 'sfm-message ' + type;
        var messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
        
        // Remove existing messages
        $('.sfm-message').remove();
        
        // Add new message
        $('.wrap h1').after(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.sfm-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Initialize tooltips
    $('[data-tooltip]').each(function() {
        var tooltip = $(this).data('tooltip');
        $(this).attr('title', tooltip);
    });
    
    // Confirm before leaving unsaved changes
    var formChanged = false;
    $('form input, form textarea, form select').on('change', function() {
        formChanged = true;
    });
    
    $('form').on('submit', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Auto-save draft (if implemented)
    setInterval(function() {
        if (formChanged) {
            // Auto-save functionality could be implemented here
            console.log('Auto-saving...');
        }
    }, 30000); // Every 30 seconds
});
