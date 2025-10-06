/**
 * WooCommerce License Tab - Customer Portal JavaScript
 * 
 * @package License Manager
 * @version 1.0.0
 * @author anjarsaputra
 * @since 2025-10-04
 */

(function($) {
    'use strict';

    /**
     * License Portal Manager
     */
    const LicensePortal = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.createModalHTML();
            console.log('ALM License Portal initialized');
        },

        /**
         * Create modal HTML structure
         */
        createModalHTML: function() {
            // Only create once
            if ($('#alm-deactivate-modal').length) {
                return;
            }

            const modalHTML = `
                <div id="alm-deactivate-modal" class="alm-modal">
                    <div class="alm-modal-overlay"></div>
                    <div class="alm-modal-container">
                        <div class="alm-modal-content">
                            <button type="button" class="alm-modal-close" aria-label="Close">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                            
                            <div class="alm-modal-icon alm-modal-icon-warning">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            
                            <h3 class="alm-modal-title">Deactivate Site?</h3>
                            
                            <div class="alm-modal-body">
                                <p>Are you sure you want to deactivate this site?</p>
                                <div class="alm-site-info-box">
                                    <strong>Site URL:</strong>
                                    <span class="alm-modal-site-url"></span>
                                </div>
                                <p class="alm-modal-note">This will free up one activation slot for this license.</p>
                            </div>
                            
                            <div class="alm-modal-footer">
                                <button type="button" class="alm-btn alm-btn-cancel">Cancel</button>
                                <button type="button" class="alm-btn alm-btn-danger alm-btn-confirm">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    Yes, Deactivate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Copy license key
            $(document).on('click', '.alm-copy-btn', this.copyLicenseKey.bind(this));
            
            // Deactivate site
            $(document).on('click', '.alm-deactivate-btn', this.deactivateSite.bind(this));
            
            // Download theme
            $(document).on('click', '.alm-btn-download', this.downloadTheme.bind(this));
            
            // Close notification
            $(document).on('click', '.alm-notification-close', this.closeNotification.bind(this));
            
            // Modal events
            $(document).on('click', '.alm-modal-close, .alm-btn-cancel, .alm-modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.alm-btn-confirm', this.confirmDeactivation.bind(this));
            
            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.alm-modal.active').length) {
                    this.closeModal();
                }
            }.bind(this));
        },

        /**
         * Copy license key to clipboard
         */
        copyLicenseKey: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const licenseKey = $button.data('license-key');
            const $input = $button.closest('.alm-license-key-group').find('.alm-license-key-input');
            
            // Method 1: Modern Clipboard API (preferred)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(licenseKey).then(() => {
                    this.showCopySuccess($button);
                }).catch(() => {
                    // Fallback to method 2
                    this.copyFallback($input, $button);
                });
            } 
            // Method 2: Fallback for older browsers
            else {
                this.copyFallback($input, $button);
            }
        },

        /**
         * Fallback copy method
         */
        copyFallback: function($input, $button) {
            try {
                $input.select();
                $input[0].setSelectionRange(0, 99999); // For mobile devices
                
                const successful = document.execCommand('copy');
                
                if (successful) {
                    this.showCopySuccess($button);
                } else {
                    this.showNotification(almLicenseData.strings.copy_failed, 'error');
                }
                
                // Deselect
                window.getSelection().removeAllRanges();
            } catch (err) {
                console.error('Copy failed:', err);
                this.showNotification(almLicenseData.strings.copy_failed, 'error');
            }
        },

        /**
         * Show copy success feedback
         */
        showCopySuccess: function($button) {
            const originalText = $button.html();
            
            // Change button state
            $button.addClass('copied');
            $button.html(`
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>${almLicenseData.strings.copied}</span>
            `);
            
            // Show notification
            this.showNotification(almLicenseData.strings.copied, 'success');
            
            // Reset after 3 seconds
            setTimeout(() => {
                $button.removeClass('copied');
                $button.html(originalText);
            }, 3000);
        },

        /**
         * Show deactivation modal
         */
        deactivateSite: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const licenseKey = $button.data('license-key');
            const siteId = $button.data('site-id');
            const siteUrl = $button.data('site-url');
            
            // Store data in modal for later use
            const $modal = $('#alm-deactivate-modal');
            $modal.data('license-key', licenseKey);
            $modal.data('site-id', siteId);
            $modal.data('site-url', siteUrl);
            $modal.data('button', $button);
            
            // Update modal content
            $modal.find('.alm-modal-site-url').text(siteUrl);
            
            // Show modal with animation
            this.openModal();
        },

        /**
         * Open modal
         */
        openModal: function() {
            const $modal = $('#alm-deactivate-modal');
            $modal.addClass('active');
            $('body').addClass('alm-modal-open');
            
            // Focus on cancel button for accessibility
            setTimeout(() => {
                $modal.find('.alm-btn-cancel').focus();
            }, 100);
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            // Don't close if clicking inside modal content
            if (e && $(e.target).closest('.alm-modal-content').length && !$(e.target).hasClass('alm-modal-close') && !$(e.target).hasClass('alm-btn-cancel')) {
                return;
            }

            const $modal = $('#alm-deactivate-modal');
            $modal.removeClass('active');
            $('body').removeClass('alm-modal-open');
            
            // Clear stored data
            $modal.removeData('license-key site-id site-url button');
        },

        /**
         * Confirm deactivation (called when user clicks "Yes, Deactivate")
         */
        /**
 * Confirm deactivation (called when user clicks "Yes, Deactivate")
 */
/**
 * Confirm deactivation (called when user clicks "Yes, Deactivate")
 */
confirmDeactivation: function(e) {
    e.preventDefault();
    
    const $modal = $('#alm-deactivate-modal');
    const licenseKey = $modal.data('license-key');
    const siteId = $modal.data('site-id');
    const siteUrl = $modal.data('site-url');
    const $button = $modal.data('button');
    
    // Close modal
    this.closeModal();
    
    // Disable button
    $button.prop('disabled', true);
    const originalText = $button.text();
    $button.html('<span class="alm-loading"></span> ' + almLicenseData.strings.deactivating);
    
    // FIXED: Use AJAX endpoint instead of REST API
    $.ajax({
        url: almLicenseData.ajax_url,  // ✅ Changed from REST to AJAX
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'alm_deactivate_site',           // ✅ AJAX action
            nonce: almLicenseData.nonce,             // ✅ AJAX nonce
            license_key: licenseKey,                 // ✅ Correct parameter
            site_id: siteId,                         // ✅ Correct parameter
            site_url: siteUrl
        },
        success: (response) => {
            console.log('Deactivation Response:', response);
            
            if (response.success) {
                // Success - get message
                const message = response.data?.message || almLicenseData.strings.deactivated || 'Site deactivated successfully!';
                this.showNotification(message, 'success');
                
                // Remove site from list with animation
                $button.closest('.alm-site-item').fadeOut(400, function() {
                    $(this).remove();
                    
                    // If no more sites, hide active sites section
                    const $sitesList = $('.alm-sites-list');
                    if ($sitesList.find('.alm-site-item').length === 0) {
                        $sitesList.closest('.alm-active-sites').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                });
                
                // Update activation count
                const newCount = response.data?.new_count || 0;
                this.updateActivationCount(licenseKey, -1);
                
            } else {
                // Error - get error message
                let errorMsg = almLicenseData.strings.error || 'Failed to deactivate site';
                
                if (response.data) {
                    if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else if (response.data.message) {
                        errorMsg = response.data.message;
                    }
                }
                
                this.showNotification(errorMsg, 'error');
                
                // Re-enable button
                $button.prop('disabled', false);
                $button.text(originalText);
            }
        },
        error: (xhr, status, error) => {
            console.error('AJAX Error:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
            
            let errorMessage = almLicenseData.strings.error || 'Connection error. Please try again.';
            
            // Try to parse error response
            try {
                const jsonResponse = JSON.parse(xhr.responseText);
                if (jsonResponse.data && jsonResponse.data.message) {
                    errorMessage = jsonResponse.data.message;
                } else if (jsonResponse.message) {
                    errorMessage = jsonResponse.message;
                }
            } catch(e) {
                // Not JSON, use default message
            }
            
            this.showNotification(errorMessage, 'error');
            
            // Re-enable button
            $button.prop('disabled', false);
            $button.text(originalText);
        }
    });
},

        /**
         * Update activation count in UI
         */
        updateActivationCount: function(licenseKey, change) {
            const $card = $(`.alm-license-card[data-license-key="${licenseKey}"]`);
            const $activationsValue = $card.find('.alm-detail-item:first .alm-detail-value');
            
            if ($activationsValue.length) {
                const text = $activationsValue.text();
                const match = text.match(/(\d+)\s*\/\s*(\d+)/);
                
                if (match) {
                    const current = parseInt(match[1]);
                    const limit = parseInt(match[2]);
                    const newCount = Math.max(0, current + change);
                    
                    // Update text
                    let newText = `<strong>${newCount}</strong> / ${limit}`;
                    
                    // Remove or add limit reached warning
                    if (newCount >= limit) {
                        newText += ' <span class="alm-limit-reached">(Limit reached)</span>';
                    }
                    
                    $activationsValue.html(newText);
                }
            }
        },

        /**
         * Download theme
         */
        downloadTheme: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const productName = $button.data('product');
            const $card = $button.closest('.alm-license-card');
            const licenseKey = $card.data('license-key');
            
            // Show loading
            const originalText = $button.html();
            $button.html('<span class="alm-loading"></span> ' + almLicenseData.strings.loading);
            $button.css('pointer-events', 'none');
            
            // AJAX request to get download URL
            $.ajax({
                url: almLicenseData.ajax_url,
                type: 'POST',
                data: {
                    action: 'alm_get_download_url',
                    nonce: almLicenseData.nonce,
                    license_key: licenseKey,
                    product_name: productName
                },
                success: (response) => {
                    if (response.success && response.data.download_url) {
                        // Trigger download
                        window.location.href = response.data.download_url;
                        
                        this.showNotification('Download started!', 'success');
                    } else {
                        this.showNotification(response.data.message || 'Download not available', 'error');
                    }
                    
                    // Reset button
                    $button.html(originalText);
                    $button.css('pointer-events', 'auto');
                },
                error: (xhr, status, error) => {
                    console.error('Download error:', error);
                    this.showNotification('Download failed. Please contact support.', 'error');
                    
                    // Reset button
                    $button.html(originalText);
                    $button.css('pointer-events', 'auto');
                }
            });
        },

        /**
         * Show notification toast
         */
        showNotification: function(message, type = 'info') {
            // Remove existing notifications
            $('.alm-notification').remove();
            
            // Icon based on type
            let icon = '';
            if (type === 'success') {
                icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
            } else if (type === 'error') {
                icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
            } else {
                icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#17a2b8" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
            }
            
            // Create notification
            const $notification = $(`
                <div class="alm-notification ${type}">
                    ${icon}
                    <div class="alm-notification-content">${message}</div>
                    <button class="alm-notification-close">&times;</button>
                </div>
            `);
            
            // Append to body
            $('body').append($notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                this.closeNotification($notification);
            }, 5000);
        },

        /**
         * Close notification
         */
        closeNotification: function(target) {
            let $notification;
            
            if (target instanceof jQuery) {
                $notification = target;
            } else {
                $notification = $(target.currentTarget).closest('.alm-notification');
            }
            
            $notification.css('animation', 'slideOutRight 0.3s ease');
            
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Check if we're on the licenses page
        if ($('.alm-licenses-wrapper').length) {
            LicensePortal.init();
        }
    });

    /**
     * Expose to global scope for external access
     */
    window.ALMLicensePortal = LicensePortal;

})(jQuery);