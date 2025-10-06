/**
 * WooCommerce Customer Portal - Custom My Account Scripts
 * Version: 2.0.0
 */

(function($) {
    'use strict';
    
    // Wait for DOM ready
    $(document).ready(function() {
        
        /**
         * Copy License Key functionality
         */
        $('.copy-btn-mini').on('click', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const targetId = btn.data('target');
            const input = $(targetId);
            
            if (!input.length) {
                return;
            }
            
            // Select and copy
            input[0].select();
            input[0].setSelectionRange(0, 99999); // For mobile
            
            try {
                const successful = document.execCommand('copy');
                
                if (successful) {
                    // Success feedback
                    const originalHTML = btn.html();
                    
                    btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>');
                    btn.css('background', '#10b981');
                    
                    setTimeout(function() {
                        btn.html(originalHTML);
                        btn.css('background', '');
                    }, 1500);
                } else {
                    alert(wcpPortal.i18n.copyFailed || 'Failed to copy');
                }
            } catch (err) {
                // Fallback for older browsers
                console.error('Copy failed:', err);
                alert(wcpPortal.i18n.copyFailed || 'Failed to copy');
            }
        });
        
        /**
         * Smooth scroll to sections
         */
        $('a[href^="#"]').on('click', function(e) {
            const target = $(this.getAttribute('href'));
            
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
        
        /**
         * Toggle mobile navigation (if needed)
         */
        $('.woocommerce-MyAccount-navigation-toggle').on('click', function() {
            $('.woocommerce-MyAccount-navigation').toggleClass('active');
        });
        
    });
    
})(jQuery);