<?php
/**
 * OIDC WordPress Plugin 配置文件
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件版本信息
define('OIDC_WP_VERSION', '1.0.0');
define('OIDC_WP_MIN_WP_VERSION', '5.8');
define('OIDC_WP_MIN_PHP_VERSION', '7.0');
define('OIDC_WP_MIN_MYSQL_VERSION', '5.6');

// 插件路径和URL
define('OIDC_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OIDC_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OIDC_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 插件名称和描述
define('OIDC_WP_PLUGIN_NAME', 'OIDC WordPress Plugin');
define('OIDC_WP_PLUGIN_DESCRIPTION', '一个支持OpenID Connect的WordPress认证插件');

// 数据库表名
define('OIDC_WP_USERS_TABLE', 'oidc_users');

// 选项名称前缀
define('OIDC_WP_OPTION_PREFIX', 'oidc_wp_');

// 缓存键前缀
define('OIDC_WP_CACHE_PREFIX', 'oidc_discovery_');

// 默认设置
define('OIDC_WP_DEFAULT_SCOPE', 'openid profile email');
define('OIDC_WP_DEFAULT_USER_ROLE', 'subscriber');
define('OIDC_WP_DEFAULT_CLEANUP_DAYS', 30);

// 安全设置
define('OIDC_WP_STATE_EXPIRY', 300); // 5分钟
define('OIDC_WP_NONCE_EXPIRY', 300); // 5分钟
define('OIDC_WP_MAX_REDIRECTS', 5);

// 错误代码
define('OIDC_WP_ERROR_INVALID_CONFIG', 'invalid_config');
define('OIDC_WP_ERROR_DISCOVERY_FAILED', 'discovery_failed');
define('OIDC_WP_ERROR_TOKEN_EXCHANGE_FAILED', 'token_exchange_failed');
define('OIDC_WP_ERROR_USER_CREATION_FAILED', 'user_creation_failed');
define('OIDC_WP_ERROR_AUTHENTICATION_FAILED', 'authentication_failed');

// 日志级别
define('OIDC_WP_LOG_LEVEL_ERROR', 'error');
define('OIDC_WP_LOG_LEVEL_WARNING', 'warning');
define('OIDC_WP_LOG_LEVEL_INFO', 'info');
define('OIDC_WP_LOG_LEVEL_DEBUG', 'debug');

// 支持的OIDC提供商
define('OIDC_WP_SUPPORTED_PROVIDERS', array(
    'google' => array(
        'name' => 'Google',
        'discovery_url' => 'https://accounts.google.com/.well-known/openid_configuration',
        'icon' => 'google-icon.png'
    ),
    'microsoft' => array(
        'name' => 'Microsoft Azure AD',
        'discovery_url' => 'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration',
        'icon' => 'microsoft-icon.png'
    ),
    'auth0' => array(
        'name' => 'Auth0',
        'discovery_url' => 'https://your-domain.auth0.com/.well-known/openid_configuration',
        'icon' => 'auth0-icon.png'
    ),
    'keycloak' => array(
        'name' => 'Keycloak',
        'discovery_url' => 'https://your-domain.com/auth/realms/your-realm/.well-known/openid_configuration',
        'icon' => 'keycloak-icon.png'
    )
));

// 系统兼容性检查
function oidc_wp_check_compatibility() {
    $errors = array();
    
    // 检查WordPress版本
    if (version_compare(get_bloginfo('version'), OIDC_WP_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            'WordPress版本过低。需要版本 %s 或更高，当前版本：%s',
            OIDC_WP_MIN_WP_VERSION,
            get_bloginfo('version')
        );
    }
    
    // 检查PHP版本
    if (version_compare(PHP_VERSION, OIDC_WP_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            'PHP版本过低。需要版本 %s 或更高，当前版本：%s',
            OIDC_WP_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }
    
    // 检查MySQL版本
    global $wpdb;
    $mysql_version = $wpdb->db_version();
    if (version_compare($mysql_version, OIDC_WP_MIN_MYSQL_VERSION, '<')) {
        $errors[] = sprintf(
            'MySQL版本过低。需要版本 %s 或更高，当前版本：%s',
            OIDC_WP_MIN_MYSQL_VERSION,
            $mysql_version
        );
    }
    
    // 检查必需的PHP扩展
    $required_extensions = array('json', 'openssl', 'curl');
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = sprintf('缺少必需的PHP扩展：%s', $ext);
        }
    }
    
    // 检查PHP 8兼容性
    if (version_compare(PHP_VERSION, '8.0', '>=')) {
        // PHP 8特定检查
        if (!function_exists('str_contains')) {
            // 这不应该发生，但以防万一
            $errors[] = 'PHP 8检测到但缺少某些函数';
        }
    }
    
    // 检查WordPress函数
    if (!function_exists('wp_remote_get')) {
        $errors[] = 'WordPress HTTP API不可用';
    }
    
    if (!function_exists('wp_create_user')) {
        $errors[] = 'WordPress用户管理功能不可用';
    }
    
    return $errors;
}

// 获取插件状态
function oidc_wp_get_plugin_status() {
    $status = array(
        'version' => OIDC_WP_VERSION,
        'compatibility' => array(),
        'configuration' => array(),
        'database' => array()
    );
    
    // 兼容性检查
    $compatibility_errors = oidc_wp_check_compatibility();
    $status['compatibility'] = array(
        'valid' => empty($compatibility_errors),
        'errors' => $compatibility_errors
    );
    
    // 配置检查
    $required_options = array(
        'oidc_wp_client_id',
        'oidc_wp_discovery_url'
    );
    
    $config_errors = array();
    foreach ($required_options as $option) {
        if (empty(get_option($option))) {
            $config_errors[] = $option . ' 未配置';
        }
    }
    
    $status['configuration'] = array(
        'valid' => empty($config_errors),
        'errors' => $config_errors
    );
    
    // 数据库检查
    global $wpdb;
    $table_name = $wpdb->prefix . OIDC_WP_USERS_TABLE;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    $status['database'] = array(
        'table_exists' => $table_exists,
        'user_count' => $table_exists ? OIDC_WP_User_Manager::count_oidc_users() : 0
    );
    
    return $status;
}

// 记录日志
function oidc_wp_log($message, $level = OIDC_WP_LOG_LEVEL_INFO, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context
        );
        
        error_log('OIDC WP [' . $level . ']: ' . $message . ' ' . json_encode($context));
    }
}

// 获取插件设置URL
function oidc_wp_get_settings_url() {
    return admin_url('options-general.php?page=oidc-wp-settings');
}

// 获取插件文档URL
function oidc_wp_get_docs_url() {
    return 'https://github.com/qingshanking/oidc-wordpress-plugin';
}

// 获取支持URL
function oidc_wp_get_support_url() {
    return 'https://github.com/qingshanking/oidc-wordpress-plugin';
}
