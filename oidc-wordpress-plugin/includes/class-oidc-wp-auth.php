<?php
/**
 * OIDC WordPress Plugin 认证处理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_Auth {
    
    /**
     * OIDC提供商配置
     */
    private $provider_config;
    
    /**
     * 初始化认证处理
     */
    public function init() {
        add_action('wp_ajax_oidc_logout', array($this, 'handle_logout'));
        add_action('wp_ajax_nopriv_oidc_logout', array($this, 'handle_logout'));
        add_action('wp_logout', array($this, 'handle_wp_logout'));
    }
    
    /**
     * 处理OIDC回调
     */
    public function process_callback($code) {
        try {
            // 获取OIDC提供商配置
            $this->provider_config = $this->get_provider_config();
            
            // 交换授权码获取访问令牌
            $token_data = $this->exchange_code_for_token($code);
            
            // 获取用户信息
            $user_info = $this->get_user_info($token_data['access_token']);
            
            // 处理用户登录或注册
            $user = $this->handle_user($user_info);
            
            return $user;
            
        } catch (Exception $e) {
            error_log('OIDC认证错误: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取OIDC提供商配置
     */
    private function get_provider_config() {
        $discovery_url = get_option('oidc_wp_discovery_url');
        
        if (empty($discovery_url)) {
            throw new Exception(__('OIDC发现URL未配置', 'oidc-wp'));
        }
        
        // 使用发现文档处理类
        return OIDC_WP_Discovery::get_provider_config($discovery_url);
    }
    
    /**
     * 交换授权码获取访问令牌
     */
    private function exchange_code_for_token($code) {
        $client_id = get_option('oidc_wp_client_id');
        $client_secret = get_option('oidc_wp_client_secret');
        $redirect_uri = get_option('oidc_wp_redirect_uri');
        
        if (empty($client_id) || empty($client_secret)) {
            throw new Exception(__('OIDC客户端凭据未配置', 'oidc-wp'));
        }
        
        $token_endpoint = $this->provider_config['token_endpoint'];
        
        $response = wp_remote_post($token_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret)
            ),
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('令牌交换失败：', 'oidc-wp') . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);
        
        if (empty($token_data) || !isset($token_data['access_token'])) {
            throw new Exception(__('无效的令牌响应', 'oidc-wp'));
        }
        
        return $token_data;
    }
    
    /**
     * 获取用户信息
     */
    private function get_user_info($access_token) {
        $userinfo_endpoint = $this->provider_config['userinfo_endpoint'];
        
        $response = wp_remote_get($userinfo_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception(__('获取用户信息失败：', 'oidc-wp') . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $user_info = json_decode($body, true);
        
        if (empty($user_info) || !isset($user_info['sub'])) {
            throw new Exception(__('无效的用户信息响应', 'oidc-wp'));
        }
        
        return $user_info;
    }
    
    /**
     * 处理用户登录或注册
     */
    private function handle_user($user_info) {
        $provider = parse_url($this->provider_config['issuer'] ?? $this->provider_config['userinfo_endpoint'], PHP_URL_HOST);
        
        // 使用用户管理类
        return OIDC_WP_User_Manager::create_or_get_user($user_info, $provider);
    }
    
    /**
     * 根据OIDC主题获取用户
     */
    private function get_user_by_oidc_subject($oidc_subject, $provider) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}oidc_users WHERE oidc_subject = %s AND oidc_provider = %s",
            $oidc_subject,
            $provider
        ));
        
        if ($user_id) {
            return get_user_by('ID', $user_id);
        }
        
        return false;
    }
    

    
    /**
     * 处理OIDC登出
     */
    public function handle_logout() {
        check_ajax_referer('oidc_wp_nonce', 'nonce');
        
        $logout_url = get_option('oidc_wp_logout_url', '');
        
        if (!empty($logout_url)) {
            wp_send_json_success(array('logout_url' => $logout_url));
        } else {
            wp_send_json_success(array('logout_url' => home_url()));
        }
    }
    
    /**
     * 处理WordPress登出
     */
    public function handle_wp_logout() {
        // 清理OIDC相关的会话数据
        if (isset($_COOKIE['oidc_session'])) {
            unset($_COOKIE['oidc_session']);
            setcookie('oidc_session', '', time() - 3600, '/');
        }
    }
    
    /**
     * 获取OIDC用户信息
     */
    public function get_oidc_user_info($user_id) {
        global $wpdb;
        
        $oidc_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oidc_users WHERE wp_user_id = %d",
            $user_id
        ));
        
        return $oidc_info;
    }
    
    /**
     * 检查用户是否通过OIDC登录
     */
    public function is_oidc_user($user_id) {
        $oidc_info = $this->get_oidc_user_info($user_id);
        return !empty($oidc_info);
    }
    
    /**
     * 解除OIDC账户链接
     */
    public function unlink_oidc_account($user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'oidc_users',
            array('wp_user_id' => $user_id),
            array('%d')
        );
        
        return $result !== false;
    }
}
