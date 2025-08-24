<?php
/**
 * OIDC WordPress Plugin 主类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_Plugin {
    
    /**
     * 插件实例
     */
    private static $instance = null;
    
    /**
     * 设置页面实例
     */
    private $settings;
    
    /**
     * 认证处理实例
     */
    private $auth;
    
    /**
     * 获取插件实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_oidc_login', array($this, 'ajax_oidc_login'));
        add_action('wp_ajax_nopriv_oidc_login', array($this, 'ajax_oidc_login'));
        add_action('wp_ajax_oidc_callback', array($this, 'ajax_oidc_callback'));
        add_action('wp_ajax_nopriv_oidc_callback', array($this, 'ajax_oidc_callback'));
    }
    
    /**
     * 初始化插件
     */
    public function init() {
        // 初始化设置页面和认证处理 - 使用更安全的钩子
        add_action('wp_loaded', array($this, 'init_components'));
        
        // 添加登录按钮到登录页面 - 使用更安全的钩子
        add_action('wp_loaded', array($this, 'maybe_add_login_form_hooks'));
        
        // 处理OIDC回调 - 使用更安全的钩子
        add_action('template_redirect', array($this, 'maybe_handle_callback'));
        
        // 添加OIDC登录链接到用户菜单 - 使用更安全的钩子
        add_action('wp_loaded', array($this, 'maybe_add_nav_menu_hooks'));
    }
    
    /**
     * 加载前端脚本
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'oidc-wp-frontend',
            OIDC_WP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            OIDC_WP_VERSION,
            true
        );
        
        wp_localize_script('oidc-wp-frontend', 'oidc_wp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oidc_wp_nonce'),
            'strings' => array(
                'login_success' => __('登录成功！', 'oidc-wp'),
                'login_error' => __('登录失败，请重试。', 'oidc-wp'),
            )
        ));
    }
    
    /**
     * 加载管理后台脚本
     */
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_oidc-wp-settings' === $hook) {
            wp_enqueue_script(
                'oidc-wp-admin',
                OIDC_WP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                OIDC_WP_VERSION,
                true
            );
            
            // 本地化脚本数据
            wp_localize_script('oidc-wp-admin', 'oidc_wp_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oidc_wp_admin_nonce'),
                'strings' => array(
                    'test_success' => __('测试成功！', 'oidc-wp'),
                    'test_failed' => __('测试失败', 'oidc-wp'),
                    'testing' => __('测试中...', 'oidc-wp'),
                    'test_connection' => __('测试连接', 'oidc-wp')
                )
            ));
            
            wp_enqueue_style(
                'oidc-wp-admin',
                OIDC_WP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                OIDC_WP_VERSION
            );
        }
    }
    
    /**
     * 添加OIDC登录按钮到登录表单
     */
    public function add_login_button() {
        $client_id = get_option('oidc_wp_client_id');
        $discovery_url = get_option('oidc_wp_discovery_url');
        
        if (empty($client_id) || empty($discovery_url)) {
            return;
        }
        
        $redirect_uri = get_option('oidc_wp_redirect_uri');
        $scope = get_option('oidc_wp_scope', 'openid profile email');
        
        $auth_url = $this->build_auth_url($discovery_url, $client_id, $redirect_uri, $scope);
        
        echo '<div class="oidc-login-section">';
        echo '<p class="oidc-login-text">' . __('或者使用您的账户登录：', 'oidc-wp') . '</p>';
        echo '<a href="' . esc_url($auth_url) . '" class="button button-primary oidc-login-button">';
        echo '<span class="oidc-icon"></span>';
        echo __('OIDC登录', 'oidc-wp');
        echo '</a>';
        echo '</div>';
    }
    
    /**
     * 构建认证URL
     */
    private function build_auth_url($discovery_url, $client_id, $redirect_uri, $scope) {
        try {
            // 从发现URL获取提供商配置
            $provider_config = OIDC_WP_Discovery::get_provider_config($discovery_url);
            
            if (empty($provider_config['authorization_endpoint'])) {
                throw new Exception(__('无法获取授权端点', 'oidc-wp'));
            }
            
            $params = array(
                'response_type' => 'code',
                'client_id' => $client_id,
                'redirect_uri' => $redirect_uri,
                'scope' => $scope,
                'state' => wp_create_nonce('oidc_auth'),
                'nonce' => wp_create_nonce('oidc_nonce')
            );
            
            return add_query_arg($params, $provider_config['authorization_endpoint']);
            
        } catch (Exception $e) {
            error_log('构建认证URL失败: ' . $e->getMessage());
            // 回退到使用发现URL（虽然不正确，但保持向后兼容）
            $params = array(
                'response_type' => 'code',
                'client_id' => $client_id,
                'redirect_uri' => $redirect_uri,
                'scope' => $scope,
                'state' => wp_create_nonce('oidc_auth'),
                'nonce' => wp_create_nonce('oidc_nonce')
            );
            
            return add_query_arg($params, $discovery_url);
        }
    }
    
    /**
     * 检查并处理OIDC回调
     */
    public function maybe_handle_callback() {
        // 检查多种回调方式
        $is_callback = false;
        
        // 方式1: /wp-login.php?oidc=callback
        if (isset($_GET['oidc']) && $_GET['oidc'] === 'callback') {
            $is_callback = true;
        }
        
        // 方式2: /wp-admin/admin-ajax.php?action=oidc_callback
        if (isset($_GET['action']) && $_GET['action'] === 'oidc_callback') {
            $is_callback = true;
        }
        
        // 方式3: 自定义回调路径
        $custom_callback = get_option('oidc_wp_custom_callback_path', '');
        if (!empty($custom_callback) && strpos($_SERVER['REQUEST_URI'], $custom_callback) !== false) {
            $is_callback = true;
        }
        
        if ($is_callback) {
            $this->handle_callback();
        }
    }
    
    /**
     * 处理OIDC回调
     */
    private function handle_callback() {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die(__('无效的OIDC回调参数', 'oidc-wp'));
        }
        
        // 验证state参数
        if (!wp_verify_nonce($_GET['state'], 'oidc_auth')) {
            wp_die(__('OIDC state验证失败', 'oidc-wp'));
        }
        
        $code = sanitize_text_field($_GET['code']);
        
        try {
            $user = $this->auth->process_callback($code);
            if ($user) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                
                // 重定向到用户想要访问的页面或仪表板
                $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : admin_url();
                wp_redirect($redirect_to);
                exit;
            }
        } catch (Exception $e) {
            wp_die(__('OIDC认证失败：', 'oidc-wp') . $e->getMessage());
        }
    }
    
    /**
     * AJAX OIDC登录处理
     */
    public function ajax_oidc_login() {
        check_ajax_referer('oidc_wp_nonce', 'nonce');
        
        $client_id = get_option('oidc_wp_client_id');
        $discovery_url = get_option('oidc_wp_discovery_url');
        
        if (empty($client_id) || empty($discovery_url)) {
            wp_die(__('OIDC配置不完整', 'oidc-wp'));
        }
        
        $redirect_uri = get_option('oidc_wp_redirect_uri');
        $scope = get_option('oidc_wp_scope', 'openid profile email');
        
        $auth_url = $this->build_auth_url($discovery_url, $client_id, $redirect_uri, $scope);
        
        wp_send_json_success(array('auth_url' => $auth_url));
    }
    
    /**
     * AJAX OIDC回调处理
     */
    public function ajax_oidc_callback() {
        // 检查必要的参数
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die(__('无效的OIDC回调参数', 'oidc-wp'));
        }
        
        // 验证state参数
        if (!wp_verify_nonce($_GET['state'], 'oidc_auth')) {
            wp_die(__('OIDC state验证失败', 'oidc-wp'));
        }
        
        $code = sanitize_text_field($_GET['code']);
        
        try {
            $user = $this->auth->process_callback($code);
            if ($user) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                
                // 重定向到用户想要访问的页面或仪表板
                $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : admin_url();
                wp_redirect($redirect_to);
                exit;
            }
        } catch (Exception $e) {
            wp_die(__('OIDC认证失败：', 'oidc-wp') . $e->getMessage());
        }
    }
    
    /**
     * 初始化组件
     */
    public function init_components() {
        // 初始化设置页面
        if (is_admin()) {
            $this->settings = new OIDC_WP_Settings();
            $this->settings->init();
        }
        
        // 初始化认证处理
        $this->auth = new OIDC_WP_Auth();
        $this->auth->init();
    }
    
    /**
     * 检查并添加登录表单钩子
     */
    public function maybe_add_login_form_hooks() {
        if (get_option('oidc_wp_enable_login', '1')) {
            $this->add_login_form_hooks();
        }
    }
    
    /**
     * 添加登录表单钩子
     */
    public function add_login_form_hooks() {
        add_action('login_form', array($this, 'add_login_button'));
        add_action('woocommerce_login_form', array($this, 'add_login_button'));
    }
    
    /**
     * 检查并添加导航菜单钩子
     */
    public function maybe_add_nav_menu_hooks() {
        if (get_option('oidc_wp_enable_login', '1')) {
            $this->add_nav_menu_hooks();
        }
    }
    
    /**
     * 添加导航菜单钩子
     */
    public function add_nav_menu_hooks() {
        add_filter('wp_nav_menu_items', array($this, 'add_nav_menu_items'), 10, 2);
    }
    
    /**
     * 添加导航菜单项
     */
    public function add_nav_menu_items($items, $args) {
        // 使用 wp_get_current_user() 来安全地检查用户登录状态
        $current_user = wp_get_current_user();
        if (!$current_user->exists() && 'primary' === $args->theme_location) {
            $client_id = get_option('oidc_wp_client_id');
            $discovery_url = get_option('oidc_wp_discovery_url');
            
            if (!empty($client_id) && !empty($discovery_url)) {
                $redirect_uri = get_option('oidc_wp_redirect_uri');
                $scope = get_option('oidc_wp_scope', 'openid profile email');
                
                $auth_url = $this->build_auth_url($discovery_url, $client_id, $redirect_uri, $scope);
                
                $items .= '<li class="menu-item oidc-login-menu-item">';
                $items .= '<a href="' . esc_url($auth_url) . '" class="oidc-login-link">';
                $items .= __('OIDC登录', 'oidc-wp');
                $items .= '</a>';
                $items .= '</li>';
            }
        }
        
        return $items;
    }
}
