<?php
/**
 * OidcTypechoPlugin插件
 * 
 * @package OidcTypecho
 * @author 萧瑟
 * @version 1.2.0
 */
class OidcTypecho_Action extends Typecho_Widget implements Widget_Interface_Do
{
    // 实现Widget_ActionInterface接口的action方法
    public function action()
    {
        // 必须实现此方法，但我们不需要在此处添加任何逻辑
    }
    /**
     * 执行接口
     */
    public function execute()
    {
    }
    
    /**
     * 回调处理
     */
    public function callback()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $options->plugin('OidcTypecho');
        // 获取code和state
        $code = $this->request->get('code');
        $state = $this->request->get('state');
        if (empty($code)) {
            $error = $this->request->get('error');
            $errorDescription = $this->request->get('error_description');
            $this->loginError("授权失败: {$error} - {$errorDescription}");
        }
        // 获取token
        $token = $this->getAccessToken($code,$state);

        if (empty($token)) {
            $this->loginError('获取访问令牌失败');
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($token);
        if (empty($userInfo)) {
            $this->loginError('获取用户信息失败');
        }
        
        // 处理用户登录
        $this->processUserLogin($userInfo);
        
        //echo $code;
        
    }
    
    /**
     * 处理用户登录
     * 
     * @param array $userInfo 用户信息
     */
    private function processUserLogin($userInfo)
    {
        // 这里需要根据实际返回的用户信息结构来调整
        // 假设用户信息中包含唯一标识字段username
        if (empty($userInfo['preferred_username'])) {
            $this->loginError('用户信息不完整');
        }
        
        $username = $userInfo['preferred_username'];
        
                // 使用数组映射处理用户名
        $username = $this->mapUsername($username);
        
        //$username = ($username == 'admin') ? 'xiaose' : $username;
        //$username = ($username == 'yanqs') ? 'xiaose' : $username;
        //echo $username;
        
        try {
            // 尝试获取用户
            $user = Typecho_Widget::widget('Widget_User');
            $db = Typecho_Db::get();
            $query = $db->select()->from('typecho_users')
                ->where('name = ?', $username)
                ->orWhere('mail = ?', $username);
            
            $result = $db->fetchRow($query);
            
            if ($result) {
                // 用户存在，通过用户ID直接登录（无需密码）
                //$user->useUidLogin($result['uid']);
                $user->simpleLogin($result['uid'],false);
                if ($user->hasLogin()) {
                    // 登录成功，跳转到首页
                    $adminUrl = Typecho_Common::url('admin/', $this->options->index);
                    $this->response->redirect($adminUrl);
                } else {
                    echo '会话或Cookie设置失败，无法登录';
                }
                
            } else {
                // 用户不存在
                echo '该用户不存在';
                //$this->response->redirect("https://blog.yanqingshan.com/");
            }
        } catch (Exception $e) {
            $this->loginError('登录过程中发生错误: ' . $e->getMessage());
        }
    }
    
    //使用用户uid登录
    private function useUidLogin($uid, $expire = 0)
    {
        
        $db = Typecho_Db::get();
        $authCode = function_exists('openssl_random_pseudo_bytes') ?
        bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
        //$user = array('uid'=>$uid,'authCode'=>$authCode);

        Typecho_Cookie::set('__typecho_uid', $uid, $expire);
        Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), $expire);
        
                //更新最后登录时间以及验证码
        $db->query($db
            ->update('typecho_users')
            ->expression('logged', 'activated')
            ->rows(['authCode' => $authCode])
            ->where('uid = ?', $uid));
        
    }
    

     /**
     * 获取访问令牌
     * 
     * @param string $code 授权码
     * @return string|false 访问令牌或false
     */
    private function getAccessToken($code,$state)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $options->plugin('OidcTypecho');
        
        // 确定token端点URL
        if (!empty($pluginConfig->discoveryUrl)) {
            $discoveryData = $this->getDiscoveryData($pluginConfig->discoveryUrl);
            if ($discoveryData && isset($discoveryData['token_endpoint'])) {
                $tokenUrl = $discoveryData['token_endpoint'];
            } else {
                return false;
            }
        } else {
            $tokenUrl = rtrim($pluginConfig->oauthUrl, '/') . '/oauth2/token';
        }
        
        $redirectUri = $options->index . '/oidc/callback';
        
        // 构建请求头
        $authString = $pluginConfig->clientId . ':' . $pluginConfig->clientSecret;
        $authHeader = 'Basic ' . base64_encode($authString);
        
        
        $headers = array(
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        
        
        // 构建请求体
        $postData = array(
            'grant_type'=> 'authorization_code',
            //'scope'=>'all',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'scope' => $pluginConfig->scope
        );
        
        //var_dump($postData);
        
        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200 || empty($response)) {
            return false;
        }
        
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($responseData['access_token'])) {
            return false;
        }
        
        return $responseData['access_token'];
    }
    
        /**
     * 获取用户信息
     * 
     * @param string $token 访问令牌
     * @return array|false 用户信息数组或false
     */
    private function getUserInfo($token)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $options->plugin('OidcTypecho');
        
        // 确定用户信息端点URL
        if (!empty($pluginConfig->discoveryUrl)) {
            $discoveryData = $this->getDiscoveryData($pluginConfig->discoveryUrl);
            if ($discoveryData && isset($discoveryData['userinfo_endpoint'])) {
                $userInfoUrl = $discoveryData['userinfo_endpoint'];
            } else {
                return false;
            }
        } else {
            $userInfoUrl = rtrim($pluginConfig->oauthUrl, '/') . '/oauth2/userinfo';
        }
        
        // 构建请求头
        $headers = array(
            'Authorization: Bearer ' . $token
        );
        
        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        
        if ($httpCode != 200 || empty($response)) {
            return false;
        }
        
        $userInfo = json_decode($response, true);
        
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($userInfo)) {
            return false;
        }
        
        return $userInfo;
    } 
    
    /**
     * 获取OIDC发现文档数据
     * 
     * @param string $discoveryUrl 发现文档URL
     * @return array|false 发现文档数据或false
     */
    private function getDiscoveryData($discoveryUrl)
    {
        // 检查是否有缓存
        $cacheKey = 'oidc_discovery_' . md5($discoveryUrl);
        $cachedData = Typecho_Cookie::get($cacheKey);
        
        if ($cachedData) {
            $data = json_decode($cachedData, true);
            if ($data && isset($data['expires_at']) && $data['expires_at'] > time()) {
                return $data['data'];
            }
        }
        
        // 获取发现文档
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $discoveryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200 || empty($response)) {
            return false;
        }
        
        $discoveryData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // 缓存数据（1小时）
        $cacheData = array(
            'data' => $discoveryData,
            'expires_at' => time() + 3600
        );
        Typecho_Cookie::set($cacheKey, json_encode($cacheData), time() + 3600);
        
        return $discoveryData;
    }
    
    /**
     * 用户名映射处理
     * 
     * @param string $oauthUsername OAuth2返回的用户名
     * @return string 映射后的用户名
     */
    private function mapUsername($oauthUsername)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $options->plugin('OidcTypecho');
        
        // 获取用户名映射配置
        $mappingConfig = !empty($pluginConfig->usernameMapping) ? $pluginConfig->usernameMapping : '';
        
        if (empty($mappingConfig)) {
            // 如果没有配置映射规则，返回原用户名
            return $oauthUsername;
        }
        
        // 将配置字符串转换为数组
        $mappingLines = explode("\n", $mappingConfig);
        $usernameMapping = array();
        
        foreach ($mappingLines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue; // 跳过空行或格式不正确的行
            }
            
            list($oauthName, $typechoName) = explode('=', $line, 2);
            $oauthName = trim($oauthName);
            $typechoName = trim($typechoName);
            
            if (!empty($oauthName) && !empty($typechoName)) {
                $usernameMapping[$oauthName] = $typechoName;
            }
        }
        
        // 检查是否有匹配的映射规则
        if (isset($usernameMapping[$oauthUsername])) {
            return $usernameMapping[$oauthUsername];
        }
        
        // 如果没有匹配的规则，返回原用户名
        return $oauthUsername;
    }

}    