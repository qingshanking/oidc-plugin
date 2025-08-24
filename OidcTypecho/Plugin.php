<?php
/**
 * OidcTypechoPlugin插件
 * 
 * @package OidcTypecho
 * @author 萧瑟
 * @version 1.2.0
 * @link https://blog.yanqingshan.com
 */
class OidcTypecho_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法
     * 
     * @access public
     * @return string
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 注册路由 - Typecho 1.2.1 兼容方式
        Helper::addRoute('oidc_callback', '/oidc/callback', 'OidcTypecho_Action', 'callback');
        // 添加登录链接
        //Typecho_Plugin::factory('Widget_Archive')->bottom = array('OidcTypecho_Plugin', 'renderLoginButton');
        return _t('插件已激活，请配置OAuth2参数');
        
    }

    /**
     * 禁用插件方法
     * 
     * @access public
     * @return string
     */
    public static function deactivate()
    {
        // 移除路由
        Helper::removeRoute('oidc_callback');
        return _t('插件已禁用');
    }

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 添加OIDC发现文档URL配置
        $discoveryUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'discoveryUrl', 
            null, 
            '', 
            _t('OIDC发现文档URL'), 
            _t('例如: https://your-oidc-provider/.well-known/openid_configuration<br/>配置此项后，其他URL将自动获取'));
        $form->addInput($discoveryUrl);


        $oidcSystemName = new Typecho_Widget_Helper_Form_Element_Text(
            'oidcSystemName', 
            null, 
            '', 
            _t('OIDC系统名称'), 
            _t('例如: IAM、CAS'));
        $form->addInput($oidcSystemName->addRule('required', _t('请输入OIDC系统名称'))); 

        // 修改OAuth2站点地址为可选
        $oauthUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'oauthUrl', 
            null, 
            '', 
            _t('OAuth2站点地址（可选）'), 
            _t('如果配置了发现文档URL，此项可选。例如: https://oauth.example.com'));
        $form->addInput($oauthUrl);

        $clientId = new Typecho_Widget_Helper_Form_Element_Text(
            'clientId', 
            null, 
            '', 
            _t('Client ID'), 
            _t('OAuth2客户端ID'));
        $form->addInput($clientId->addRule('required', _t('请输入Client ID')));

        $clientSecret = new Typecho_Widget_Helper_Form_Element_Text(
            'clientSecret', 
            null, 
            '', 
            _t('Client Secret'), 
            _t('OAuth2客户端密钥'));
        $form->addInput($clientSecret->addRule('required', _t('请输入Client Secret')));
        
        $scope = new Typecho_Widget_Helper_Form_Element_Text(
            'scope', 
            null, 
            'openid email phone profile', 
            _t('Scope'), 
            _t('OAuth2作用域'));
        $form->addInput($scope);
        
        // 添加用户名映射配置
        $usernameMapping = new Typecho_Widget_Helper_Form_Element_Textarea(
            'usernameMapping', 
            null, 
            "admin=admin\nzhangsan=admin", 
            _t('用户名映射规则'), 
            _t('格式：OAuth2用户名=Typecho用户名，每行一个规则。例如：admin=zhangsan'));
        $form->addInput($usernameMapping);

    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 渲染登录按钮
     */
    public static function renderLoginButton()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginConfig = $options->plugin('OidcTypecho');
        
        // 检查配置是否完整
        if (empty($pluginConfig->discoveryUrl) && (empty($pluginConfig->oauthUrl) || empty($pluginConfig->clientId))) {
            return;
        }
        
        // 生成state参数
        $state = bin2hex(random_bytes(16));
        
        // 构建授权URL
        $redirectUri = $options->index . '/oidc/callback';
        
        // 如果配置了发现文档URL，使用动态获取的授权端点
        if (!empty($pluginConfig->discoveryUrl)) {
            $discoveryData = self::getDiscoveryData($pluginConfig->discoveryUrl);
            if ($discoveryData && isset($discoveryData['authorization_endpoint'])) {
                $authorizeUrl = $discoveryData['authorization_endpoint'];
            } else {
                return; // 无法获取授权端点
            }
        } else {
            // 使用手动配置的URL
            $authorizeUrl = rtrim($pluginConfig->oauthUrl, '/') . '/oauth2/auth';
        }
        
        $authorizeUrl .= '?client_id=' . urlencode($pluginConfig->clientId);
        $authorizeUrl .= '&response_type=code';
        $authorizeUrl .= '&redirect_uri=' . urlencode($redirectUri);
        $authorizeUrl .= '&scope=' . $pluginConfig->scope;
        $authorizeUrl .= '&state=' . urlencode($state);
        
        echo '<a href="' . $authorizeUrl . '">单点登录</a>';
    }
    
    /**
     * 获取OIDC发现文档数据
     * 
     * @param string $discoveryUrl 发现文档URL
     * @return array|false 发现文档数据或false
     */
    private static function getDiscoveryData($discoveryUrl)
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
}    