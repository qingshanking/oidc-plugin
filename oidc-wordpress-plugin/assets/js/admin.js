/**
 * OIDC WordPress Plugin 管理后台JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // 测试发现URL功能
        $('#oidc_test_discovery').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#oidc_discovery_result');
            var $input = $('#oidc_wp_discovery_url');
            var discoveryUrl = $input.val();
            
            if (!discoveryUrl) {
                $result.html('<div class="notice notice-error"><p>请输入发现URL</p></div>');
                return;
            }
            
            // 显示加载状态
            $button.text('测试中...').prop('disabled', true);
            $result.html('<div class="notice notice-info"><p>正在测试连接...</p></div>');
            
            // 发起测试请求
            $.ajax({
                url: oidc_wp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'oidc_test_discovery',
                    nonce: oidc_wp_admin.nonce,
                    discovery_url: discoveryUrl
                },
                success: function(response) {
                    $button.text('测试连接').prop('disabled', false);
                    
                    if (response.success) {
                        var data = response.data.data;
                        var html = '<div class="notice notice-success"><p><strong>连接成功！</strong></p>';
                        html += '<ul style="margin-left: 20px;">';
                        html += '<li><strong>发行者:</strong> ' + (data.issuer || 'N/A') + '</li>';
                        html += '<li><strong>授权端点:</strong> ' + (data.authorization_endpoint || 'N/A') + '</li>';
                        html += '<li><strong>令牌端点:</strong> ' + (data.token_endpoint || 'N/A') + '</li>';
                        html += '<li><strong>用户信息端点:</strong> ' + (data.userinfo_endpoint || 'N/A') + '</li>';
                        if (data.scopes_supported && data.scopes_supported.length > 0) {
                            html += '<li><strong>支持的作用域:</strong> ' + data.scopes_supported.join(', ') + '</li>';
                        }
                        html += '</ul></div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="notice notice-error"><p><strong>连接失败:</strong> ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $button.text('测试连接').prop('disabled', false);
                    $result.html('<div class="notice notice-error"><p>请求失败，请检查网络连接</p></div>');
                }
            });
        });
        
        // 测试连接功能
        $('#test-oidc-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // 显示加载状态
            $button.text('测试中...').prop('disabled', true);
            
            // 获取表单数据
            var formData = {
                client_id: $('#oidc_wp_client_id').val(),
                client_secret: $('#oidc_wp_client_secret').val(),
                discovery_url: $('#oidc_wp_discovery_url').val(),
                redirect_uri: $('#oidc_wp_redirect_uri').val(),
                scope: $('#oidc_wp_scope').val()
            };
            
            // 验证必填字段
            if (!formData.client_id || !formData.discovery_url) {
                showAdminMessage('请填写客户端ID和发现URL', 'error');
                resetButton($button, originalText);
                return;
            }
            
            // 发起测试请求
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_oidc_connection',
                    nonce: oidc_wp_admin.nonce,
                    form_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        showAdminMessage('OIDC连接测试成功！', 'success');
                    } else {
                        showAdminMessage('OIDC连接测试失败：' + (response.data.message || '未知错误'), 'error');
                    }
                },
                error: function() {
                    showAdminMessage('OIDC连接测试失败：网络错误', 'error');
                },
                complete: function() {
                    resetButton($button, originalText);
                }
            });
        });
        
        // 自动填充发现URL示例
        $('#discovery-url-examples').on('click', function(e) {
            e.preventDefault();
            
            var examples = [
                'https://accounts.google.com/.well-known/openid_configuration',
                'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration',
                'https://auth0.com/.well-known/openid_configuration',
                'https://your-domain.com/.well-known/openid_configuration'
            ];
            
            var $examplesDiv = $('<div class="discovery-url-examples">' +
                '<h4>常见OIDC发现URL示例：</h4>' +
                '<ul>' +
                examples.map(function(url) {
                    return '<li><code>' + url + '</code></li>';
                }).join('') +
                '</ul>' +
                '<p><small>点击任意示例可自动填充到发现URL字段</small></p>' +
                '</div>');
            
            // 如果已存在，则切换显示
            if ($('.discovery-url-examples').length) {
                $('.discovery-url-examples').remove();
            } else {
                $('.discovery-url-field').after($examplesDiv);
                
                // 添加点击事件
                $examplesDiv.find('code').on('click', function() {
                    $('#oidc_wp_discovery_url').val($(this).text());
                });
            }
        });
        
        // 生成重定向URI
        $('#generate-redirect-uri').on('click', function(e) {
            e.preventDefault();
            
            var currentSite = window.location.origin;
            var redirectUri = currentSite + '/wp-login.php?oidc=callback';
            
            $('#oidc_wp_redirect_uri').val(redirectUri);
            
            showAdminMessage('已生成重定向URI：' + redirectUri, 'success');
        });
        
        // 验证设置
        $('form').on('submit', function(e) {
            var clientId = $('#oidc_wp_client_id').val();
            var discoveryUrl = $('#oidc_wp_discovery_url').val();
            
            if (!clientId || !discoveryUrl) {
                e.preventDefault();
                showAdminMessage('请填写必填字段：客户端ID和发现URL', 'error');
                return false;
            }
            
            // 验证URL格式
            if (discoveryUrl && !isValidUrl(discoveryUrl)) {
                e.preventDefault();
                showAdminMessage('发现URL格式不正确', 'error');
                return false;
            }
            
            var redirectUri = $('#oidc_wp_redirect_uri').val();
            if (redirectUri && !isValidUrl(redirectUri)) {
                e.preventDefault();
                showAdminMessage('重定向URI格式不正确', 'error');
                return false;
            }
        });
        
        // 显示管理消息
        function showAdminMessage(message, type) {
            var $messageDiv = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">忽略此通知。</span>' +
                '</button>' +
                '</div>');
            
            // 添加到页面顶部
            $('.wrap h1').after($messageDiv);
            
            // 自动隐藏（5秒后）
            setTimeout(function() {
                $messageDiv.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // 重置按钮状态
        function resetButton($button, text) {
            $button.text(text).prop('disabled', false);
        }
        
        // 验证URL格式
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // 添加帮助提示
        $('.form-table th label').each(function() {
            var $label = $(this);
            var fieldName = $label.attr('for');
            
            if (fieldName) {
                var helpText = getHelpText(fieldName);
                if (helpText) {
                    $label.append('<span class="dashicons dashicons-editor-help" title="' + helpText + '"></span>');
                }
            }
        });
        
        // 获取帮助文本
        function getHelpText(fieldName) {
            var helpTexts = {
                'oidc_wp_client_id': '从OIDC提供商获取的客户端标识符',
                'oidc_wp_client_secret': '从OIDC提供商获取的客户端密钥',
                'oidc_wp_discovery_url': 'OIDC提供商的发现文档URL',
                'oidc_wp_redirect_uri': 'OIDC提供商回调的URI地址',
                'oidc_wp_scope': '请求的OIDC作用域，用空格分隔'
            };
            
            return helpTexts[fieldName] || '';
        }
        
        // 添加CSS样式
        if (!$('#oidc-wp-admin-styles').length) {
            $('head').append('<style id="oidc-wp-admin-styles">' +
                '.discovery-url-examples { ' +
                    'background: #f9f9f9; ' +
                    'border: 1px solid #ddd; ' +
                    'padding: 15px; ' +
                    'margin: 10px 0; ' +
                    'border-radius: 4px; ' +
                '} ' +
                '.discovery-url-examples ul { margin: 10px 0; } ' +
                '.discovery-url-examples code { ' +
                    'background: #fff; ' +
                    'padding: 2px 5px; ' +
                    'border: 1px solid #ddd; ' +
                    'cursor: pointer; ' +
                    'border-radius: 2px; ' +
                '} ' +
                '.discovery-url-examples code:hover { ' +
                    'background: #e7f3ff; ' +
                    'border-color: #0073aa; ' +
                '} ' +
                '.dashicons-editor-help { ' +
                    'color: #0073aa; ' +
                    'margin-left: 5px; ' +
                    'cursor: help; ' +
                '} ' +
                '#test-oidc-connection { margin-left: 10px; } ' +
                '#discovery-url-examples, #generate-redirect-uri { ' +
                    'margin-left: 10px; ' +
                    'text-decoration: none; ' +
                '} ' +
            '</style>');
        }
        
    });
    
})(jQuery);
