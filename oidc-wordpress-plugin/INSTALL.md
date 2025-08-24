# OIDC WordPress Plugin 安装说明

## 系统要求

在安装此插件之前，请确保您的系统满足以下要求：

### WordPress
- WordPress 5.8 或更高版本
- 启用永久链接（Pretty Permalinks）

### PHP
- PHP 7.0 或更高版本
- 必需的PHP扩展：
  - `json` - JSON处理
  - `openssl` - 加密和SSL支持
  - `curl` - HTTP请求

### 数据库
- MySQL 5.6 或更高版本
- 或者 MariaDB 10.1 或更高版本

### 服务器
- 支持HTTPS（生产环境必需）
- 允许外部HTTP请求
- 支持URL重写

## 安装步骤

### 方法1：通过WordPress管理后台安装

1. 登录WordPress管理后台
2. 进入"插件" > "安装插件"
3. 点击"上传插件"
4. 选择插件的ZIP文件
5. 点击"立即安装"
6. 安装完成后点击"启用插件"

### 方法2：手动安装

1. 下载插件文件
2. 解压文件到本地
3. 将插件文件夹上传到 `/wp-content/plugins/` 目录
4. 在WordPress管理后台启用插件

### 方法3：通过Composer安装

```bash
composer require your-username/oidc-wordpress-plugin
```

## 初始配置

### 1. 基本设置

启用插件后，进入"设置" > "OIDC设置"页面，配置以下信息：

- **客户端ID**: 从OIDC提供商获取的客户端标识符
- **客户端密钥**: 从OIDC提供商获取的客户端密钥
- **发现URL**: OIDC提供商的发现文档URL
- **重定向URI**: OIDC提供商回调的URI地址
- **作用域**: 请求的OIDC作用域（默认：openid profile email）

### 2. 高级设置

- **启用OIDC登录**: 在登录页面显示OIDC登录按钮
- **自动创建用户**: 如果用户不存在，自动创建WordPress用户账户
- **自动链接用户**: 自动链接OIDC用户与现有WordPress用户（基于邮箱）

## OIDC提供商配置

### Google OAuth 2.0

1. 访问 [Google Cloud Console](https://console.cloud.google.com/)
2. 创建新项目或选择现有项目
3. 启用Google+ API
4. 创建OAuth 2.0客户端ID
5. 配置授权的重定向URI：`https://your-domain.com/wp-login.php?oidc=callback`
6. 复制客户端ID和客户端密钥

**发现URL**: `https://accounts.google.com/.well-known/openid_configuration`

### Microsoft Azure AD

1. 访问 [Azure Portal](https://portal.azure.com/)
2. 注册新应用程序
3. 配置重定向URI
4. 获取客户端ID和客户端密钥

**发现URL**: `https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration`

### Auth0

1. 访问 [Auth0 Dashboard](https://manage.auth0.com/)
2. 创建新应用程序
3. 配置回调URL
4. 获取客户端ID和客户端密钥

**发现URL**: `https://your-domain.auth0.com/.well-known/openid_configuration`

### Keycloak

1. 访问Keycloak管理控制台
2. 创建新客户端
3. 配置重定向URI
4. 获取客户端ID和客户端密钥

**发现URL**: `https://your-domain.com/auth/realms/your-realm/.well-known/openid_configuration`

## 测试配置

### 1. 测试连接

在设置页面点击"测试连接"按钮，验证OIDC配置是否正确。

### 2. 测试登录

1. 访问WordPress登录页面
2. 点击"OIDC登录"按钮
3. 完成OIDC认证流程
4. 验证是否成功登录WordPress

## 故障排除

### 常见问题

#### 1. "OIDC发现URL未配置"错误
- 检查发现URL是否正确
- 确保URL可以正常访问
- 验证URL格式

#### 2. "令牌交换失败"错误
- 验证客户端ID和密钥是否正确
- 检查重定向URI是否匹配
- 确认OIDC提供商配置

#### 3. "用户创建失败"错误
- 确保WordPress有创建用户的权限
- 检查邮箱地址是否有效
- 验证用户名是否唯一

#### 4. "JWT验证失败"错误
- 检查JWT令牌格式
- 验证签名算法
- 确认公钥配置

### 调试模式

启用WordPress调试模式以获取详细错误信息：

```php
// 在wp-config.php中添加
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 日志文件

调试信息会记录在 `/wp-content/debug.log` 文件中。

## 安全注意事项

### 1. HTTPS要求
- 生产环境必须使用HTTPS
- 确保OIDC提供商也使用HTTPS

### 2. 客户端密钥保护
- 不要在代码中硬编码客户端密钥
- 使用环境变量或安全的配置管理

### 3. 重定向URI验证
- 严格验证重定向URI
- 防止开放重定向攻击

### 4. 状态参数验证
- 使用随机状态参数防止CSRF攻击
- 验证状态参数的有效性

## 性能优化

### 1. 缓存配置
- 启用OIDC发现文档缓存
- 配置适当的缓存时间

### 2. 数据库优化
- 定期清理过期的OIDC用户数据
- 优化数据库查询

### 3. 网络请求优化
- 使用HTTP/2
- 启用连接复用

## 备份和恢复

### 1. 数据库备份
- 备份 `wp_oidc_users` 表
- 备份OIDC相关的用户元数据

### 2. 配置备份
- 备份插件设置
- 记录OIDC提供商配置

### 3. 恢复步骤
1. 恢复数据库表
2. 恢复插件设置
3. 验证OIDC连接

## 升级说明

### 1. 自动升级
- 通过WordPress管理后台自动升级
- 确保备份重要数据

### 2. 手动升级
1. 备份当前插件文件
2. 下载新版本
3. 替换插件文件
4. 激活插件

### 3. 升级后检查
- 验证OIDC功能
- 检查用户数据完整性
- 测试登录流程

## 支持和帮助

### 1. 文档
- 查看README.md文件
- 访问插件文档网站

### 2. 社区支持
- WordPress.org插件支持论坛
- GitHub Issues页面

### 3. 专业支持
- 联系插件开发者
- 寻求WordPress专家帮助

## 许可证

此插件使用GPL v2或更高版本许可证。请查看LICENSE文件了解详细信息。
