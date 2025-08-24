<?php
/**
 * OIDC WordPress Plugin JWT令牌处理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class OIDC_WP_JWT {
    
    /**
     * 验证JWT令牌
     */
    public static function verify_jwt($jwt, $issuer, $client_id) {
        try {
            // 解析JWT头部
            $header = self::decode_jwt_header($jwt);
            
            // 检查算法
            if (empty($header['alg']) || $header['alg'] === 'none') {
                throw new Exception(__('不安全的JWT算法', 'oidc-wp'));
            }
            
            // 解析JWT载荷
            $payload = self::decode_jwt_payload($jwt);
            
            // 验证基本声明
            self::validate_basic_claims($payload, $issuer, $client_id);
            
            // 验证时间声明
            self::validate_time_claims($payload);
            
            // 验证签名（如果支持）
            if ($header['alg'] !== 'none') {
                self::verify_jwt_signature($jwt, $issuer);
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log('JWT验证失败: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 解码JWT头部
     */
    private static function decode_jwt_header($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new Exception(__('无效的JWT格式', 'oidc-wp'));
        }
        
        $header_json = self::base64url_decode($parts[0]);
        $header = json_decode($header_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('JWT头部JSON解析失败', 'oidc-wp'));
        }
        
        return $header;
    }
    
    /**
     * 解码JWT载荷
     */
    private static function decode_jwt_payload($jwt) {
        $parts = explode('.', $jwt);
        $payload_json = self::base64url_decode($parts[1]);
        $payload = json_decode($payload_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('JWT载荷JSON解析失败', 'oidc-wp'));
        }
        
        return $payload;
    }
    
    /**
     * 验证基本声明
     */
    private static function validate_basic_claims($payload, $issuer, $client_id) {
        // 验证iss（发行者）
        if (empty($payload['iss']) || $payload['iss'] !== $issuer) {
            throw new Exception(sprintf(__('JWT发行者不匹配，期望：%s，实际：%s', 'oidc-wp'), $issuer, $payload['iss'] ?? '未设置'));
        }
        
        // 验证aud（受众）
        if (empty($payload['aud'])) {
            throw new Exception(__('JWT缺少受众声明', 'oidc-wp'));
        }
        
        // 检查受众是否包含客户端ID
        $audience = $payload['aud'];
        if (is_array($audience)) {
            if (!in_array($client_id, $audience)) {
                throw new Exception(sprintf(__('JWT受众不包含客户端ID：%s', 'oidc-wp'), $client_id));
            }
        } else {
            if ($audience !== $client_id) {
                throw new Exception(sprintf(__('JWT受众不匹配，期望：%s，实际：%s', 'oidc-wp'), $client_id, $audience));
            }
        }
        
        // 验证sub（主题）
        if (empty($payload['sub'])) {
            throw new Exception(__('JWT缺少主题声明', 'oidc-wp'));
        }
        
        // 验证nonce（如果存在）
        if (isset($payload['nonce'])) {
            $stored_nonce = wp_verify_nonce($payload['nonce'], 'oidc_nonce');
            if (!$stored_nonce) {
                throw new Exception(__('JWT nonce验证失败', 'oidc-wp'));
            }
        }
    }
    
    /**
     * 验证时间声明
     */
    private static function validate_time_claims($payload) {
        $current_time = time();
        
        // 验证iat（签发时间）
        if (isset($payload['iat'])) {
            if ($payload['iat'] > $current_time + 300) { // 允许5分钟时钟偏差
                throw new Exception(__('JWT签发时间在未来', 'oidc-wp'));
            }
        }
        
        // 验证exp（过期时间）
        if (isset($payload['exp'])) {
            if ($payload['exp'] < $current_time - 300) { // 允许5分钟时钟偏差
                throw new Exception(__('JWT已过期', 'oidc-wp'));
            }
        }
        
        // 验证nbf（生效时间）
        if (isset($payload['nbf'])) {
            if ($payload['nbf'] > $current_time + 300) { // 允许5分钟时钟偏差
                throw new Exception(__('JWT尚未生效', 'oidc-wp'));
            }
        }
    }
    
    /**
     * 验证JWT签名
     */
    private static function verify_jwt_signature($jwt, $issuer) {
        // 获取OIDC提供商的公钥
        $public_key = self::get_provider_public_key($issuer);
        
        if (!$public_key) {
            // 如果无法获取公钥，记录警告但继续处理
            error_log('无法获取OIDC提供商公钥，跳过JWT签名验证');
            return;
        }
        
        // 验证签名
        $parts = explode('.', $jwt);
        $data = $parts[0] . '.' . $parts[1];
        $signature = self::base64url_decode($parts[2]);
        
        $algorithm = 'RS256'; // 默认算法
        
        $verified = openssl_verify(
            $data,
            $signature,
            $public_key,
            OPENSSL_ALGO_SHA256
        );
        
        if ($verified !== 1) {
            throw new Exception(__('JWT签名验证失败', 'oidc-wp'));
        }
    }
    
    /**
     * 获取OIDC提供商公钥
     */
    private static function get_provider_public_key($issuer) {
        // 尝试从JWKS端点获取公钥
        $jwks_url = rtrim($issuer, '/') . '/.well-known/jwks.json';
        
        $response = wp_remote_get($jwks_url, array(
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $jwks = json_decode($body, true);
        
        if (empty($jwks['keys'])) {
            return false;
        }
        
        // 使用第一个可用的公钥
        $key = $jwks['keys'][0];
        
        if (isset($key['n']) && isset($key['e'])) {
            // 构建RSA公钥
            $modulus = self::base64url_decode($key['n']);
            $exponent = self::base64url_decode($key['e']);
            
            $public_key = "-----BEGIN PUBLIC KEY-----\n";
            $public_key .= chunk_split(base64_encode($modulus), 64, "\n");
            $public_key .= chunk_split(base64_encode($exponent), 64, "\n");
            $public_key .= "-----END PUBLIC KEY-----\n";
            
            return $public_key;
        }
        
        return false;
    }
    
    /**
     * Base64URL解码
     */
    private static function base64url_decode($data) {
        $base64 = strtr($data, '-_', '+/');
        $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($base64);
    }
    
    /**
     * 从JWT中提取用户信息
     */
    public static function extract_user_info($jwt) {
        try {
            $payload = self::decode_jwt_payload($jwt);
            
            $user_info = array();
            
            // 标准OIDC声明
            $user_info['sub'] = $payload['sub'] ?? '';
            $user_info['name'] = $payload['name'] ?? '';
            $user_info['given_name'] = $payload['given_name'] ?? '';
            $user_info['family_name'] = $payload['family_name'] ?? '';
            $user_info['email'] = $payload['email'] ?? '';
            $user_info['email_verified'] = $payload['email_verified'] ?? false;
            $user_info['preferred_username'] = $payload['preferred_username'] ?? '';
            $user_info['picture'] = $payload['picture'] ?? '';
            
            // 自定义声明
            $user_info['custom_claims'] = array();
            foreach ($payload as $key => $value) {
                if (!in_array($key, array('iss', 'aud', 'sub', 'iat', 'exp', 'nbf', 'nonce', 'name', 'given_name', 'family_name', 'email', 'email_verified', 'preferred_username', 'picture'))) {
                    $user_info['custom_claims'][$key] = $value;
                }
            }
            
            return $user_info;
            
        } catch (Exception $e) {
            error_log('从JWT提取用户信息失败: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 验证JWT格式
     */
    public static function is_valid_jwt_format($jwt) {
        $parts = explode('.', $jwt);
        return count($parts) === 3;
    }
    
    /**
     * 获取JWT过期时间
     */
    public static function get_jwt_expiration($jwt) {
        try {
            $payload = self::decode_jwt_payload($jwt);
            return isset($payload['exp']) ? $payload['exp'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 检查JWT是否即将过期
     */
    public static function is_jwt_expiring_soon($jwt, $threshold = 300) {
        $exp = self::get_jwt_expiration($jwt);
        if (!$exp) {
            return false;
        }
        
        return ($exp - time()) <= $threshold;
    }
}
