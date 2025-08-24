<?php
/**
 * OIDC WordPress Plugin 发现文档处理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_Discovery {
    
    /**
     * OIDC发现文档缓存时间（秒）
     */
    const CACHE_DURATION = 3600; // 1小时
    
    /**
     * 获取OIDC提供商配置
     */
    public static function get_provider_config($discovery_url) {
        // 检查缓存
        $cache_key = 'oidc_discovery_' . md5($discovery_url);
        $cached_config = get_transient($cache_key);
        
        if ($cached_config !== false) {
            return $cached_config;
        }
        
        try {
            // 获取发现文档
            $config = self::fetch_discovery_document($discovery_url);
            
            // 验证配置
            $validated_config = self::validate_discovery_config($config);
            
            // 缓存配置
            set_transient($cache_key, $validated_config, self::CACHE_DURATION);
            
            return $validated_config;
            
        } catch (Exception $e) {
            error_log('OIDC发现文档获取失败: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取发现文档
     */
    private static function fetch_discovery_document($discovery_url) {
        $response = wp_remote_get($discovery_url, array(
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('无法获取OIDC发现文档：', 'oidc-wp') . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception(sprintf(__('OIDC发现文档请求失败，状态码：%d', 'oidc-wp'), $status_code));
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // 检查内容类型
        if (strpos($content_type, 'application/json') === false) {
            throw new Exception(__('OIDC发现文档内容类型不正确，应为application/json', 'oidc-wp'));
        }
        
        $config = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('OIDC发现文档JSON解析失败：', 'oidc-wp') . json_last_error_msg());
        }
        
        return $config;
    }
    
    /**
     * 验证发现文档配置
     */
    private static function validate_discovery_config($config) {
        // 必需字段检查
        $required_fields = array(
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint'
        );
        
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                throw new Exception(sprintf(__('OIDC发现文档缺少必需字段：%s', 'oidc-wp'), $field));
            }
        }
        
        // 验证URL格式
        $url_fields = array(
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint'
        );
        
        foreach ($url_fields as $field) {
            if (!filter_var($config[$field], FILTER_VALIDATE_URL)) {
                throw new Exception(sprintf(__('OIDC发现文档字段%s包含无效URL：%s', 'oidc-wp'), $field, $config[$field]));
            }
        }
        
        // 验证HTTPS（生产环境）
        if (!is_ssl() && !defined('WP_DEBUG') && !defined('WP_LOCAL_DEV')) {
            foreach ($url_fields as $field) {
                if (strpos($config[$field], 'https://') !== 0) {
                    throw new Exception(sprintf(__('生产环境要求OIDC提供商使用HTTPS，字段%s：%s', 'oidc-wp'), $field, $config[$field]));
                }
            }
        }
        
        // 添加可选字段的默认值
        $config['response_types_supported'] = $config['response_types_supported'] ?? array('code');
        $config['subject_types_supported'] = $config['subject_types_supported'] ?? array('public');
        $config['id_token_signing_alg_values_supported'] = $config['id_token_signing_alg_values_supported'] ?? array('RS256');
        
        return $config;
    }
    
    /**
     * 获取授权端点URL
     */
    public static function get_authorization_endpoint($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['authorization_endpoint'];
    }
    
    /**
     * 获取令牌端点URL
     */
    public static function get_token_endpoint($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['token_endpoint'];
    }
    
    /**
     * 获取用户信息端点URL
     */
    public static function get_userinfo_endpoint($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['userinfo_endpoint'];
    }
    
    /**
     * 获取发行者标识符
     */
    public static function get_issuer($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['issuer'];
    }
    
    /**
     * 检查是否支持授权码流程
     */
    public static function supports_authorization_code($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return in_array('code', $config['response_types_supported'] ?? array());
    }
    
    /**
     * 检查是否支持刷新令牌
     */
    public static function supports_refresh_token($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return in_array('refresh_token', $config['grant_types_supported'] ?? array());
    }
    
    /**
     * 获取支持的声明
     */
    public static function get_supported_claims($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['claims_supported'] ?? array();
    }
    
    /**
     * 获取支持的JWT签名算法
     */
    public static function get_supported_signing_algorithms($discovery_url) {
        $config = self::get_provider_config($discovery_url);
        return $config['id_token_signing_alg_values_supported'] ?? array('RS256');
    }
    
    /**
     * 清除发现文档缓存
     */
    public static function clear_cache($discovery_url = null) {
        if ($discovery_url) {
            $cache_key = 'oidc_discovery_' . md5($discovery_url);
            delete_transient($cache_key);
        } else {
            // 清除所有OIDC发现缓存
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oidc_discovery_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oidc_discovery_%'");
        }
    }
    
    /**
     * 测试发现文档连接
     */
    public static function test_connection($discovery_url) {
        try {
            $config = self::get_provider_config($discovery_url);
            
            return array(
                'success' => true,
                'config' => $config,
                'message' => __('OIDC发现文档连接成功', 'oidc-wp')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * 获取发现文档摘要信息
     */
    public static function get_discovery_summary($discovery_url) {
        try {
            $config = self::get_provider_config($discovery_url);
            
            return array(
                'issuer' => $config['issuer'],
                'authorization_endpoint' => $config['authorization_endpoint'],
                'token_endpoint' => $config['token_endpoint'],
                'userinfo_endpoint' => $config['userinfo_endpoint'],
                'response_types_supported' => $config['response_types_supported'],
                'grant_types_supported' => $config['grant_types_supported'] ?? array(),
                'claims_supported' => $config['claims_supported'] ?? array(),
                'scopes_supported' => $config['scopes_supported'] ?? array()
            );
            
        } catch (Exception $e) {
            return false;
        }
    }
}
