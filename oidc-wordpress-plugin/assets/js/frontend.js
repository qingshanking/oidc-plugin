/**
 * OIDC WordPress Plugin 前端JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // OIDC登录按钮点击事件
        $('.oidc-login-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // 显示加载状态
            $button.text('登录中...').prop('disabled', true);
            
            // 发起OIDC登录
            $.ajax({
                url: oidc_wp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oidc_login',
                    nonce: oidc_wp_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.auth_url) {
                        // 重定向到OIDC提供商
                        window.location.href = response.data.auth_url;
                    } else {
                        showMessage(oidc_wp_ajax.strings.login_error, 'error');
                        resetButton($button, originalText);
                    }
                },
                error: function() {
                    showMessage(oidc_wp_ajax.strings.login_error, 'error');
                    resetButton($button, originalText);
                }
            });
        });
        
        // OIDC登出处理
        $('.oidc-logout-link').on('click', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var originalText = $link.text();
            
            // 显示加载状态
            $link.text('登出中...').prop('disabled', true);
            
            // 发起OIDC登出
            $.ajax({
                url: oidc_wp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oidc_logout',
                    nonce: oidc_wp_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.logout_url) {
                        // 重定向到登出页面
                        window.location.href = response.data.logout_url;
                    } else {
                        // 回退到WordPress登出
                        window.location.href = wp_logout_url;
                    }
                },
                error: function() {
                    // 回退到WordPress登出
                    window.location.href = wp_logout_url;
                }
            });
        });
        
        // 显示消息
        function showMessage(message, type) {
            var $messageDiv = $('<div class="oidc-message oidc-message-' + type + '">' + message + '</div>');
            
            // 添加到页面
            $('body').append($messageDiv);
            
            // 显示消息
            $messageDiv.fadeIn();
            
            // 3秒后自动隐藏
            setTimeout(function() {
                $messageDiv.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // 重置按钮状态
        function resetButton($button, text) {
            $button.text(text).prop('disabled', false);
        }
        
        // 检查URL参数，显示相应的消息
        var urlParams = new URLSearchParams(window.location.search);
        var oidcStatus = urlParams.get('oidc_status');
        var oidcMessage = urlParams.get('oidc_message');
        
        if (oidcStatus && oidcMessage) {
            var messageType = oidcStatus === 'success' ? 'success' : 'error';
            showMessage(decodeURIComponent(oidcMessage), messageType);
            
            // 清理URL参数
            var newUrl = window.location.pathname;
            if (window.location.search) {
                var searchParams = new URLSearchParams(window.location.search);
                searchParams.delete('oidc_status');
                searchParams.delete('oidc_message');
                if (searchParams.toString()) {
                    newUrl += '?' + searchParams.toString();
                }
            }
            if (window.location.hash) {
                newUrl += window.location.hash;
            }
            window.history.replaceState({}, document.title, newUrl);
        }
        
        // 添加CSS样式
        if (!$('#oidc-wp-styles').length) {
            $('head').append('<style id="oidc-wp-styles">' +
                '.oidc-message { ' +
                    'position: fixed; ' +
                    'top: 20px; ' +
                    'right: 20px; ' +
                    'padding: 15px 20px; ' +
                    'border-radius: 4px; ' +
                    'color: white; ' +
                    'font-weight: bold; ' +
                    'z-index: 9999; ' +
                    'display: none; ' +
                    'box-shadow: 0 2px 10px rgba(0,0,0,0.2); ' +
                '} ' +
                '.oidc-message-success { background-color: #4CAF50; } ' +
                '.oidc-message-error { background-color: #f44336; } ' +
                '.oidc-login-button:disabled { opacity: 0.6; cursor: not-allowed; } ' +
                '.oidc-logout-link:disabled { opacity: 0.6; cursor: not-allowed; } ' +
            '</style>');
        }
        
    });
    
})(jQuery);
