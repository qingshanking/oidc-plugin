<?php
/**
 * Plugin Name: OIDC WordPress Plugin
 * Plugin URI: https://github.com/qingshanking/oidc-wordpress-plugin
 * Description: 一个支持OpenID Connect的WordPress认证插件，允许用户通过OIDC提供商登录
 * Version: 1.0.0
 * Author: yanqs
 * Author URI: https://blog.yanqingshan.com/
 * License: GPL v2 or later
 * Text Domain: oidc-wp
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 包含配置文件
require_once __DIR__ . '/config.php';

// 包含必要的文件
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-plugin.php';
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-settings.php';
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-auth.php';
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-discovery.php';
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-jwt.php';
require_once OIDC_WP_PLUGIN_DIR . 'includes/class-oidc-wp-user-manager.php';

// 初始化插件
function oidc_wp_init() {
    $plugin = OIDC_WP_Plugin::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'oidc_wp_init');

// 激活插件时的钩子
register_activation_hook(__FILE__, 'oidc_wp_activate');
function oidc_wp_activate() {
    // 检查系统兼容性
    $compatibility_errors = oidc_wp_check_compatibility();
    if (!empty($compatibility_errors)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '插件激活失败：<br>' . implode('<br>', $compatibility_errors),
            '系统兼容性错误',
            array('back_link' => true)
        );
    }
    
    // 创建必要的数据库表
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}oidc_users (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        wp_user_id bigint(20) NOT NULL,
        oidc_subject varchar(255) NOT NULL,
        oidc_provider varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY oidc_subject (oidc_subject, oidc_provider),
        KEY wp_user_id (wp_user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // 设置默认选项
    add_option('oidc_wp_client_id', '');
    add_option('oidc_wp_client_secret', '');
    add_option('oidc_wp_discovery_url', '');
    add_option('oidc_wp_redirect_uri', home_url('/wp-login.php?oidc=callback'));
    add_option('oidc_wp_scope', 'openid profile email');
    add_option('oidc_wp_enable_login', '1');
    add_option('oidc_wp_auto_create_user', '1');
    add_option('oidc_wp_auto_link_user', '1');
}

// 停用插件时的钩子
register_deactivation_hook(__FILE__, 'oidc_wp_deactivate');
function oidc_wp_deactivate() {
    // 清理定时任务等
    wp_clear_scheduled_hook('oidc_wp_cleanup_expired_tokens');
}

// 卸载插件时的钩子
register_uninstall_hook(__FILE__, 'oidc_wp_uninstall');
function oidc_wp_uninstall() {
    // 删除选项
    delete_option('oidc_wp_client_id');
    delete_option('oidc_wp_client_secret');
    delete_option('oidc_wp_discovery_url');
    delete_option('oidc_wp_redirect_uri');
    delete_option('oidc_wp_scope');
    delete_option('oidc_wp_enable_login');
    delete_option('oidc_wp_auto_create_user');
    delete_option('oidc_wp_auto_link_user');
    
    // 删除数据库表
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}oidc_users");
}
