<?php
/**
 * OIDC WordPress Plugin 用户管理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_User_Manager {
    
    /**
     * 创建或获取OIDC用户
     */
    public static function create_or_get_user($oidc_user_info, $provider) {
        try {
            // 检查是否已存在链接的OIDC用户
            $existing_user = self::get_user_by_oidc_subject($oidc_user_info['sub'], $provider);
            
            if ($existing_user) {
                // 更新用户信息
                self::update_user_info($existing_user, $oidc_user_info);
                return $existing_user;
            }
            
            // 检查是否应该自动链接用户
            if (get_option('oidc_wp_auto_link_user', '1') && !empty($oidc_user_info['email'])) {
                $existing_user = get_user_by('email', $oidc_user_info['email']);
                
                if ($existing_user) {
                    // 链接现有用户与OIDC账户
                    self::link_user_to_oidc($existing_user->ID, $oidc_user_info['sub'], $provider);
                    
                    // 更新用户信息
                    self::update_user_info($existing_user, $oidc_user_info);
                    
                    // 触发链接事件
                    do_action('oidc_wp_user_linked', $existing_user->ID, $oidc_user_info, $provider);
                    
                    return $existing_user;
                }
            }
            
            // 检查是否应该自动创建用户
            if (get_option('oidc_wp_auto_create_user', '1')) {
                $new_user = self::create_user_from_oidc($oidc_user_info, $provider);
                
                if ($new_user) {
                    // 触发创建事件
                    do_action('oidc_wp_user_created', $new_user->ID, $oidc_user_info, $provider);
                    
                    return $new_user;
                }
            }
            
            throw new Exception(__('无法找到或创建用户账户', 'oidc-wp'));
            
        } catch (Exception $e) {
            error_log('OIDC用户管理错误: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据OIDC主题获取用户
     */
    public static function get_user_by_oidc_subject($oidc_subject, $provider) {
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
     * 链接用户与OIDC账户
     */
    public static function link_user_to_oidc($user_id, $oidc_subject, $provider) {
        global $wpdb;
        
        // 检查是否已存在链接
        $existing_link = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}oidc_users WHERE wp_user_id = %d AND oidc_provider = %s",
            $user_id,
            $provider
        ));
        
        if ($existing_link) {
            // 更新现有链接
            $wpdb->update(
                $wpdb->prefix . 'oidc_users',
                array(
                    'oidc_subject' => $oidc_subject,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_link),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // 创建新链接
            $wpdb->insert(
                $wpdb->prefix . 'oidc_users',
                array(
                    'wp_user_id' => $user_id,
                    'oidc_subject' => $oidc_subject,
                    'oidc_provider' => $provider,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * 从OIDC信息创建新用户
     */
    public static function create_user_from_oidc($oidc_user_info, $provider) {
        // 生成唯一用户名
        $username = self::generate_unique_username($oidc_user_info);
        
        // 准备用户数据
        $user_data = array(
            'user_login' => $username,
            'user_email' => $oidc_user_info['email'] ?? '',
            'first_name' => $oidc_user_info['given_name'] ?? $oidc_user_info['name'] ?? '',
            'last_name' => $oidc_user_info['family_name'] ?? '',
            'display_name' => $oidc_user_info['name'] ?? $username,
            'user_pass' => wp_generate_password(32, false),
            'user_registered' => current_time('mysql'),
            'role' => get_option('oidc_wp_default_user_role', 'subscriber')
        );
        
        // 过滤用户数据
        $user_data = apply_filters('oidc_wp_user_data', $user_data, $oidc_user_info, $provider);
        
        // 创建用户
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            throw new Exception(__('创建用户失败：', 'oidc-wp') . $user_id->get_error_message());
        }
        
        // 链接OIDC账户
        self::link_user_to_oidc($user_id, $oidc_user_info['sub'], $provider);
        
        // 更新用户元数据
        self::update_user_meta($user_id, $oidc_user_info);
        
        // 发送欢迎邮件（可选）
        if (get_option('oidc_wp_send_welcome_email', '0')) {
            wp_new_user_notification($user_id, null, 'user');
        }
        
        return get_user_by('ID', $user_id);
    }
    
    /**
     * 更新用户信息
     */
    public static function update_user_info($user, $oidc_user_info) {
        $user_data = array(
            'ID' => $user->ID,
            'first_name' => $oidc_user_info['given_name'] ?? $user->first_name,
            'last_name' => $oidc_user_info['family_name'] ?? $user->last_name,
            'display_name' => $oidc_user_info['name'] ?? $user->display_name
        );
        
        // 过滤用户数据
        $user_data = apply_filters('oidc_wp_user_update_data', $user_data, $user, $oidc_user_info);
        
        // 更新用户
        wp_update_user($user_data);
        
        // 更新用户元数据
        self::update_user_meta($user->ID, $oidc_user_info);
        
        return true;
    }
    
    /**
     * 更新用户元数据
     */
    private static function update_user_meta($user_id, $oidc_user_info) {
        // 更新标准元数据
        if (!empty($oidc_user_info['picture'])) {
            update_user_meta($user_id, 'oidc_picture', $oidc_user_info['picture']);
        }
        
        if (!empty($oidc_user_info['email_verified'])) {
            update_user_meta($user_id, 'oidc_email_verified', $oidc_user_info['email_verified']);
        }
        
        if (!empty($oidc_user_info['preferred_username'])) {
            update_user_meta($user_id, 'oidc_preferred_username', $oidc_user_info['preferred_username']);
        }
        
        // 更新自定义声明
        if (!empty($oidc_user_info['custom_claims'])) {
            foreach ($oidc_user_info['custom_claims'] as $key => $value) {
                update_user_meta($user_id, 'oidc_' . $key, $value);
            }
        }
        
        // 更新最后OIDC登录时间
        update_user_meta($user_id, 'oidc_last_login', current_time('mysql'));
    }
    
    /**
     * 生成唯一用户名
     */
    private static function generate_unique_username($oidc_user_info) {
        $base_username = '';
        
        if (!empty($oidc_user_info['preferred_username'])) {
            $base_username = sanitize_user($oidc_user_info['preferred_username']);
        } elseif (!empty($oidc_user_info['email'])) {
            $base_username = sanitize_user(explode('@', $oidc_user_info['email'])[0]);
        } elseif (!empty($oidc_user_info['name'])) {
            $base_username = sanitize_user($oidc_user_info['name']);
        } else {
            $base_username = 'oidc_user';
        }
        
        // 应用过滤器
        $base_username = apply_filters('oidc_wp_username_generation', $base_username, $oidc_user_info);
        
        $username = $base_username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * 获取OIDC用户信息
     */
    public static function get_oidc_user_info($user_id) {
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
    public static function is_oidc_user($user_id) {
        $oidc_info = self::get_oidc_user_info($user_id);
        return !empty($oidc_info);
    }
    
    /**
     * 解除OIDC账户链接
     */
    public static function unlink_oidc_account($user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'oidc_users',
            array('wp_user_id' => $user_id),
            array('%d')
        );
        
        if ($result !== false) {
            // 清理OIDC相关的用户元数据
            $meta_keys = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE 'oidc_%'",
                $user_id
            ));
            
            foreach ($meta_keys as $meta_key) {
                delete_user_meta($user_id, $meta_key);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取所有OIDC用户
     */
    public static function get_all_oidc_users($provider = null) {
        global $wpdb;
        
        if ($provider) {
            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT u.*, o.oidc_subject, o.oidc_provider, o.created_at as oidc_created_at 
                 FROM {$wpdb->users} u 
                 INNER JOIN {$wpdb->prefix}oidc_users o ON u.ID = o.wp_user_id 
                 WHERE o.oidc_provider = %s 
                 ORDER BY o.created_at DESC",
                $provider
            ));
        } else {
            $users = $wpdb->get_results(
                "SELECT u.*, o.oidc_subject, o.oidc_provider, o.created_at as oidc_created_at 
                 FROM {$wpdb->users} u 
                 INNER JOIN {$wpdb->prefix}oidc_users o ON u.ID = o.wp_user_id 
                 ORDER BY o.created_at DESC"
            );
        }
        
        return $users;
    }
    
    /**
     * 统计OIDC用户数量
     */
    public static function count_oidc_users($provider = null) {
        global $wpdb;
        
        if ($provider) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}oidc_users WHERE oidc_provider = %s",
                $provider
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oidc_users");
        }
        
        return (int) $count;
    }
    
    /**
     * 清理过期的OIDC用户数据
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        // 删除30天未登录的OIDC用户链接（可选）
        $expiry_days = get_option('oidc_wp_cleanup_expiry_days', 30);
        if ($expiry_days > 0) {
            $expiry_date = date('Y-m-d H:i:s', strtotime("-{$expiry_days} days"));
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE o FROM {$wpdb->prefix}oidc_users o 
                 INNER JOIN {$wpdb->usermeta} um ON o.wp_user_id = um.user_id 
                 WHERE um.meta_key = 'oidc_last_login' 
                 AND um.meta_value < %s",
                $expiry_date
            ));
            
            if ($deleted > 0) {
                error_log("清理了 {$deleted} 个过期的OIDC用户链接");
            }
        }
    }
}
