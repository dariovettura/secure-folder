jQuery(document).ready(function($) {
    'use strict';
    
    // File download handling
    $(document).on('click', '.sfm-download-btn', function(e) {
        e.preventDefault();
        
        var fileId = $(this).data('file-id');
        var button = $(this);
        var originalText = button.text();
        
        // Show loading state
        button.html('<span class="sfm-spinner"></span>Downloading...').prop('disabled', true);
        
        $.ajax({
            url: sfm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfm_download_file',
                file_id: fileId,
                nonce: sfm_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.download_url) {
                    // Create temporary link and trigger download
                    var link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = '';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showMessage('Download started!', 'success');
                } else {
                    showMessage(response.data || 'Download failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during download', 'error');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // File search functionality
    $('#sfm-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        var files = $('.sfm-file-item');
        
        if (searchTerm === '') {
            files.show();
            return;
        }
        
        files.each(function() {
            var fileText = $(this).text().toLowerCase();
            if (fileText.indexOf(searchTerm) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Show "no results" message if no files are visible
        var visibleFiles = $('.sfm-file-item:visible').length;
        if (visibleFiles === 0) {
            showNoResultsMessage();
        } else {
            hideNoResultsMessage();
        }
    });
    
    // File filtering by type
    $('.sfm-filter-btn').on('click', function(e) {
        e.preventDefault();
        
        var filterType = $(this).data('filter');
        var files = $('.sfm-file-item');
        
        // Update active filter button
        $('.sfm-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        if (filterType === 'all') {
            files.show();
        } else {
            files.each(function() {
                var fileType = $(this).data('file-type');
                if (fileType === filterType) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
        
        // Show "no results" message if no files are visible
        var visibleFiles = $('.sfm-file-item:visible').length;
        if (visibleFiles === 0) {
            showNoResultsMessage();
        } else {
            hideNoResultsMessage();
        }
    });
    
    // File sorting
    $('.sfm-sort-select').on('change', function() {
        var sortBy = $(this).val();
        var container = $('.sfm-files-grid');
        var files = container.find('.sfm-file-item').toArray();
        
        files.sort(function(a, b) {
            var aVal, bVal;
            
            switch (sortBy) {
                case 'name':
                    aVal = $(a).find('.sfm-file-name').text().toLowerCase();
                    bVal = $(b).find('.sfm-file-name').text().toLowerCase();
                    break;
                case 'date':
                    aVal = new Date($(a).find('.sfm-upload-date').text().replace('Uploaded: ', ''));
                    bVal = new Date($(b).find('.sfm-upload-date').text().replace('Uploaded: ', ''));
                    break;
                case 'size':
                    aVal = parseFileSize($(a).find('.sfm-file-size').text().replace('Size: ', ''));
                    bVal = parseFileSize($(b).find('.sfm-file-size').text().replace('Size: ', ''));
                    break;
                case 'downloads':
                    aVal = parseInt($(a).find('.sfm-download-count').text().replace('Downloads: ', ''));
                    bVal = parseInt($(b).find('.sfm-download-count').text().replace('Downloads: ', ''));
                    break;
                default:
                    return 0;
            }
            
            if (aVal < bVal) return -1;
            if (aVal > bVal) return 1;
            return 0;
        });
        
        container.empty().append(files);
    });
    
    // File preview (if supported file types)
    $(document).on('click', '.sfm-file-preview', function(e) {
        e.preventDefault();
        
        var fileId = $(this).data('file-id');
        var fileType = $(this).data('file-type');
        
        if (fileType.startsWith('image/')) {
            showImagePreview(fileId);
        } else if (fileType === 'application/pdf') {
            showPdfPreview(fileId);
        } else {
            showMessage('Preview not available for this file type', 'info');
        }
    });
    
    // Lazy loading for file icons
    $('.sfm-file-icon').each(function() {
        var icon = $(this);
        var fileType = icon.data('file-type');
        
        if (fileType) {
            icon.addClass('sfm-file-icon-' + fileType.split('/')[1]);
        }
    });
    
    // File drag and drop (if upload is enabled)
    $('.sfm-upload-area').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    $('.sfm-upload-area').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    $('.sfm-upload-area').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });
    
    // File upload progress
    function handleFileUpload(file) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'sfm_upload_file');
        formData.append('nonce', sfm_ajax.nonce);
        
        $.ajax({
            url: sfm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percentComplete = (e.loaded / e.total) * 100;
                        updateUploadProgress(percentComplete);
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    showMessage('File uploaded successfully!', 'success');
                    location.reload(); // Refresh to show new file
                } else {
                    showMessage(response.data || 'Upload failed', 'error');
                }
            },
            error: function() {
                showMessage('An error occurred during upload', 'error');
            },
            complete: function() {
                hideUploadProgress();
            }
        });
    }
    
    // Update upload progress
    function updateUploadProgress(percent) {
        var progressBar = $('.sfm-progress-bar');
        if (progressBar.length === 0) {
            $('.sfm-upload-area').after('<div class="sfm-progress"><div class="sfm-progress-bar"></div></div>');
            progressBar = $('.sfm-progress-bar');
        }
        progressBar.css('width', percent + '%');
    }
    
    // Hide upload progress
    function hideUploadProgress() {
        $('.sfm-progress').fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    // Show image preview
    function showImagePreview(fileId) {
        // Implementation for image preview modal
        showMessage('Image preview functionality would be implemented here', 'info');
    }
    
    // Show PDF preview
    function showPdfPreview(fileId) {
        // Implementation for PDF preview modal
        showMessage('PDF preview functionality would be implemented here', 'info');
    }
    
    // Parse file size string to bytes
    function parseFileSize(sizeStr) {
        var units = {
            'B': 1,
            'KB': 1024,
            'MB': 1024 * 1024,
            'GB': 1024 * 1024 * 1024
        };
        
        var match = sizeStr.match(/^([\d.]+)\s*([A-Z]+)$/);
        if (match) {
            var size = parseFloat(match[1]);
            var unit = match[2];
            return size * (units[unit] || 1);
        }
        return 0;
    }
    
    // Show no results message
    function showNoResultsMessage() {
        if ($('.sfm-no-results').length === 0) {
            $('.sfm-files-grid').after('<div class="sfm-no-results"><p>No files found matching your criteria.</p></div>');
        }
    }
    
    // Hide no results message
    function hideNoResultsMessage() {
        $('.sfm-no-results').remove();
    }
    
    // Show message function
    function showMessage(message, type) {
        var messageClass = 'sfm-message ' + type;
        var messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
        
        // Remove existing messages
        $('.sfm-message').remove();
        
        // Add new message
        $('.sfm-frontend-files').prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.sfm-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Initialize file type icons
    $('.sfm-file-item').each(function() {
        var item = $(this);
        var fileName = item.find('.sfm-file-name').text();
        var extension = fileName.split('.').pop().toLowerCase();
        
        // Add file type class for styling
        item.addClass('sfm-file-type-' + extension);
        
        // Set appropriate icon
        var icon = item.find('.sfm-file-icon');
        var iconClass = getFileIconClass(extension);
        icon.addClass(iconClass);
    });
    
    // Get file icon class based on extension
    function getFileIconClass(extension) {
        var iconMap = {
            'pdf': 'sfm-icon-pdf',
            'doc': 'sfm-icon-doc',
            'docx': 'sfm-icon-doc',
            'txt': 'sfm-icon-txt',
            'jpg': 'sfm-icon-image',
            'jpeg': 'sfm-icon-image',
            'png': 'sfm-icon-image',
            'gif': 'sfm-icon-image',
            'zip': 'sfm-icon-archive',
            'rar': 'sfm-icon-archive'
        };
        
        return iconMap[extension] || 'sfm-icon-default';
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + F for search
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
            e.preventDefault();
            $('#sfm-search').focus();
        }
        
        // Escape to clear search
        if (e.keyCode === 27) {
            $('#sfm-search').val('').trigger('keyup');
            $('#sfm-search').blur();
        }
    });
    
    // Infinite scroll (if implemented)
    var loading = false;
    $(window).on('scroll', function() {
        if (loading) return;
        
        var scrollTop = $(window).scrollTop();
        var windowHeight = $(window).height();
        var documentHeight = $(document).height();
        
        if (scrollTop + windowHeight >= documentHeight - 100) {
            // Load more files
            loadMoreFiles();
        }
    });
    
    // Load more files function
    function loadMoreFiles() {
        if (loading) return;
        
        loading = true;
        var currentPage = $('.sfm-file-item').length / 20; // Assuming 20 files per page
        var nextPage = Math.floor(currentPage) + 1;
        
        $.ajax({
            url: sfm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfm_load_more_files',
                page: nextPage,
                nonce: sfm_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.files) {
                    $('.sfm-files-grid').append(response.data.files);
                }
            },
            complete: function() {
                loading = false;
            }
        });
    }
});
