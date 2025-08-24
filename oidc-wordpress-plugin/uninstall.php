<?php
/**
 * OIDC WordPress Plugin 卸载文件
 * 
 * 当插件被删除时，此文件会被执行
 * 用于清理插件创建的所有数据
 */

// 如果直接访问此文件，则退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 检查用户权限
if (!current_user_can('activate_plugins')) {
    return;
}

// 获取WordPress数据库前缀
global $wpdb;

// 删除插件创建的数据库表
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}oidc_users");

// 删除插件选项
delete_option('oidc_wp_client_id');
delete_option('oidc_wp_client_secret');
delete_option('oidc_wp_discovery_url');
delete_option('oidc_wp_redirect_uri');
delete_option('oidc_wp_scope');
delete_option('oidc_wp_enable_login');
delete_option('oidc_wp_auto_create_user');
delete_option('oidc_wp_auto_link_user');
delete_option('oidc_wp_send_welcome_email');
delete_option('oidc_wp_default_user_role');
delete_option('oidc_wp_cleanup_expiry_days');
delete_option('oidc_wp_logout_url');

// 删除所有OIDC相关的用户元数据
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'oidc_%'");

// 删除所有OIDC相关的瞬态数据
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oidc_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oidc_%");

// 清理定时任务
wp_clear_scheduled_hook('oidc_wp_cleanup_expired_tokens');

// 记录卸载日志
if (function_exists('error_log')) {
    error_log('OIDC WordPress Plugin 已卸载，所有数据已清理');
}

// 可选：显示卸载完成消息
if (function_exists('wp_die')) {
    wp_die(
        'OIDC WordPress Plugin 已成功卸载，所有相关数据已清理。',
        '插件卸载完成',
        array('response' => 200)
    );
}
