<?php
/**
 * OIDC WordPress Plugin 设置页面类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_Settings {
    
    /**
     * 设置页面slug
     */
    private $page_slug = 'oidc-wp-settings';
    
    /**
     * 设置组名
     */
    private $option_group = 'oidc_wp_options';
    
    /**
     * 初始化设置页面
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_oidc_test_discovery', array($this, 'ajax_test_discovery'));
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            __('OIDC设置', 'oidc-wp'),
            __('OIDC设置', 'oidc-wp'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 初始化设置
     */
    public function init_settings() {
        register_setting($this->option_group, 'oidc_wp_client_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting($this->option_group, 'oidc_wp_client_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting($this->option_group, 'oidc_wp_discovery_url', array(
            'type' => 'url',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting($this->option_group, 'oidc_wp_redirect_uri', array(
            'type' => 'url',
            'sanitize_callback' => 'esc_url_raw',
            'default' => home_url('/wp-login.php?oidc=callback')
        ));
        
        register_setting($this->option_group, 'oidc_wp_scope', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'openid profile email'
        ));
        
        register_setting($this->option_group, 'oidc_wp_enable_login', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        
        register_setting($this->option_group, 'oidc_wp_auto_create_user', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        
        register_setting($this->option_group, 'oidc_wp_auto_link_user', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ));
        
        register_setting($this->option_group, 'oidc_wp_custom_callback_path', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // 添加设置节
        add_settings_section(
            'oidc_wp_general_section',
            __('基本设置', 'oidc-wp'),
            array($this, 'render_general_section'),
            $this->page_slug
        );
        
        add_settings_section(
            'oidc_wp_advanced_section',
            __('高级设置', 'oidc-wp'),
            array($this, 'render_advanced_section'),
            $this->page_slug
        );
        
        // 添加设置字段
        add_settings_field(
            'oidc_wp_client_id',
            __('客户端ID', 'oidc-wp'),
            array($this, 'render_client_id_field'),
            $this->page_slug,
            'oidc_wp_general_section'
        );
        
        add_settings_field(
            'oidc_wp_client_secret',
            __('客户端密钥', 'oidc-wp'),
            array($this, 'render_client_secret_field'),
            $this->page_slug,
            'oidc_wp_general_section'
        );
        
        add_settings_field(
            'oidc_wp_discovery_url',
            __('发现URL', 'oidc-wp'),
            array($this, 'render_discovery_url_field'),
            $this->page_slug,
            'oidc_wp_general_section'
        );
        
        add_settings_field(
            'oidc_wp_redirect_uri',
            __('重定向URI', 'oidc-wp'),
            array($this, 'render_redirect_uri_field'),
            $this->page_slug,
            'oidc_wp_general_section'
        );
        
        add_settings_field(
            'oidc_wp_scope',
            __('作用域', 'oidc-wp'),
            array($this, 'render_scope_field'),
            $this->page_slug,
            'oidc_wp_general_section'
        );
        
        add_settings_field(
            'oidc_wp_enable_login',
            __('启用OIDC登录', 'oidc-wp'),
            array($this, 'render_enable_login_field'),
            $this->page_slug,
            'oidc_wp_advanced_section'
        );
        
        add_settings_field(
            'oidc_wp_auto_create_user',
            __('自动创建用户', 'oidc-wp'),
            array($this, 'render_auto_create_user_field'),
            $this->page_slug,
            'oidc_wp_advanced_section'
        );
        
        add_settings_field(
            'oidc_wp_auto_link_user',
            __('自动链接用户', 'oidc-wp'),
            array($this, 'render_auto_link_user_field'),
            $this->page_slug,
            'oidc_wp_advanced_section'
        );
        
        add_settings_field(
            'oidc_wp_custom_callback_path',
            __('自定义回调路径', 'oidc-wp'),
            array($this, 'render_custom_callback_path_field'),
            $this->page_slug,
            'oidc_wp_advanced_section'
        );
    }
    
    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'oidc_wp_messages',
                'oidc_wp_message',
                __('设置已保存。', 'oidc-wp'),
                'updated'
            );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('oidc_wp_messages'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page_slug);
                submit_button();
                ?>
            </form>
            
            <div class="oidc-wp-help-section">
                <h2><?php _e('使用说明', 'oidc-wp'); ?></h2>
                <p><?php _e('要使用此插件，您需要：', 'oidc-wp'); ?></p>
                <ol>
                    <li><?php _e('在OIDC提供商处注册您的WordPress站点', 'oidc-wp'); ?></li>
                    <li><?php _e('获取客户端ID和客户端密钥', 'oidc-wp'); ?></li>
                    <li><?php _e('设置发现URL（通常是OIDC提供商的根URL）', 'oidc-wp'); ?></li>
                    <li><?php _e('配置重定向URI（通常是您的WordPress登录页面）', 'oidc-wp'); ?></li>
                </ol>
                
                <h3><?php _e('测试连接', 'oidc-wp'); ?></h3>
                <p><?php _e('配置完成后，您可以测试OIDC连接：', 'oidc-wp'); ?></p>
                <a href="<?php echo esc_url(home_url('/wp-login.php')); ?>" class="button button-secondary" target="_blank">
                    <?php _e('测试登录页面', 'oidc-wp'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * 渲染基本设置节
     */
    public function render_general_section() {
        echo '<p>' . __('配置OIDC提供商的基本信息。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染高级设置节
     */
    public function render_advanced_section() {
        echo '<p>' . __('配置OIDC认证的高级选项。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染客户端ID字段
     */
    public function render_client_id_field() {
        $value = get_option('oidc_wp_client_id');
        echo '<input type="text" id="oidc_wp_client_id" name="oidc_wp_client_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('从OIDC提供商获取的客户端ID。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染客户端密钥字段
     */
    public function render_client_secret_field() {
        $value = get_option('oidc_wp_client_secret');
        echo '<input type="password" id="oidc_wp_client_secret" name="oidc_wp_client_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('从OIDC提供商获取的客户端密钥。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染发现URL字段
     */
    public function render_discovery_url_field() {
        $value = get_option('oidc_wp_discovery_url');
        echo '<div class="oidc-discovery-url-field">';
        echo '<input type="url" id="oidc_wp_discovery_url" name="oidc_wp_discovery_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<button type="button" id="oidc_test_discovery" class="button" style="margin-left: 10px;">' . __('测试连接', 'oidc-wp') . '</button>';
        echo '<div id="oidc_discovery_result" style="margin-top: 10px;"></div>';
        echo '<p class="description">' . __('OIDC提供商的发现URL，例如：https://accounts.google.com/.well-known/openid_configuration', 'oidc-wp') . '</p>';
        echo '</div>';
    }
    
    /**
     * 渲染重定向URI字段
     */
    public function render_redirect_uri_field() {
        $value = get_option('oidc_wp_redirect_uri');
        echo '<input type="url" id="oidc_wp_redirect_uri" name="oidc_wp_redirect_uri" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('OIDC提供商回调的URI地址。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染作用域字段
     */
    public function render_scope_field() {
        $value = get_option('oidc_wp_scope');
        echo '<input type="text" id="oidc_wp_scope" name="oidc_wp_scope" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('请求的OIDC作用域，用空格分隔。', 'oidc-wp') . '</p>';
    }
    
    /**
     * 渲染启用登录字段
     */
    public function render_enable_login_field() {
        $value = get_option('oidc_wp_enable_login');
        echo '<input type="checkbox" id="oidc_wp_enable_login" name="oidc_wp_enable_login" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="oidc_wp_enable_login">' . __('在登录页面显示OIDC登录按钮', 'oidc-wp') . '</label>';
    }
    
    /**
     * 渲染自动创建用户字段
     */
    public function render_auto_create_user_field() {
        $value = get_option('oidc_wp_auto_create_user');
        echo '<input type="checkbox" id="oidc_wp_auto_create_user" name="oidc_wp_auto_create_user" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="oidc_wp_auto_create_user">' . __('如果用户不存在，自动创建WordPress用户账户', 'oidc-wp') . '</label>';
    }
    
    /**
     * 渲染自动链接用户字段
     */
    public function render_auto_link_user_field() {
        $value = get_option('oidc_wp_auto_link_user');
        echo '<input type="checkbox" id="oidc_wp_auto_link_user" name="oidc_wp_auto_link_user" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="oidc_wp_auto_link_user">' . __('自动链接OIDC用户与现有WordPress用户（基于邮箱）', 'oidc-wp') . '</label>';
    }
    
    /**
     * 渲染自定义回调路径字段
     */
    public function render_custom_callback_path_field() {
        $value = get_option('oidc_wp_custom_callback_path');
        echo '<input type="text" id="oidc_wp_custom_callback_path" name="oidc_wp_custom_callback_path" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('可选：自定义回调路径，例如：/oidc/callback。留空使用默认回调。', 'oidc-wp') . '</p>';
        echo '<p class="description">' . __('<strong>推荐的回调地址：</strong>', 'oidc-wp') . '</p>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li><code>' . home_url('/wp-login.php?oidc=callback') . '</code> - 默认方式</li>';
        echo '<li><code>' . admin_url('admin-ajax.php?action=oidc_callback') . '</code> - AJAX方式（推荐）</li>';
        echo '<li><code>' . home_url('/oidc/callback') . '</code> - 自定义路径（需要配置重写规则）</li>';
        echo '</ul>';
    }
    
    /**
     * 显示管理通知
     */
    public function admin_notices() {
        $client_id = get_option('oidc_wp_client_id');
        $discovery_url = get_option('oidc_wp_discovery_url');
        
        if (empty($client_id) || empty($discovery_url)) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'settings_page_' . $this->page_slug) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . __('请完成OIDC配置以启用OIDC登录功能。', 'oidc-wp') . '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * AJAX测试发现URL
     */
    public function ajax_test_discovery() {
        check_ajax_referer('oidc_wp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('权限不足', 'oidc-wp'));
        }
        
        $discovery_url = sanitize_url($_POST['discovery_url']);
        
        if (empty($discovery_url)) {
            wp_send_json_error(__('请输入发现URL', 'oidc-wp'));
        }
        
        $result = OIDC_WP_Discovery::test_connection($discovery_url);
        
        if ($result['success']) {
            $config = $result['config'];
            $response = array(
                'success' => true,
                'message' => $result['message'],
                'data' => array(
                    'issuer' => $config['issuer'] ?? '',
                    'authorization_endpoint' => $config['authorization_endpoint'] ?? '',
                    'token_endpoint' => $config['token_endpoint'] ?? '',
                    'userinfo_endpoint' => $config['userinfo_endpoint'] ?? '',
                    'scopes_supported' => $config['scopes_supported'] ?? array(),
                    'response_types_supported' => $config['response_types_supported'] ?? array()
                )
            );
            wp_send_json_success($response);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
