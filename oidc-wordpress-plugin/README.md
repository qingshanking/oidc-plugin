# OIDC WordPress Plugin

一个支持OpenID Connect的WordPress认证插件，允许用户通过OIDC提供商登录WordPress。

## 系统要求

- PHP 7.0 或更高版本
- WordPress 5.8 或更高版本
- MySQL 5.6 或更高版本

## 功能特性

- 🔐 支持OpenID Connect 1.0标准
- 👥 自动用户创建和链接
- 🎨 美观的管理界面
- 📱 响应式设计
- 🔒 安全的认证流程
- ⚙️ 灵活的配置选项

## 安装方法

### 方法1：手动安装

1. 下载插件文件
2. 将插件文件夹上传到 `/wp-content/plugins/` 目录
3. 在WordPress管理后台激活插件
4. 进入"设置" > "OIDC设置"进行配置

### 方法2：通过WordPress上传

1. 在WordPress管理后台进入"插件" > "安装插件"
2. 点击"上传插件"
3. 选择插件ZIP文件并上传
4. 激活插件并进行配置

## 配置说明

### 基本配置

1. **客户端ID**: 从OIDC提供商获取的客户端标识符
2. **客户端密钥**: 从OIDC提供商获取的客户端密钥
3. **发现URL**: OIDC提供商的发现文档URL
4. **重定向URI**: OIDC提供商回调的URI地址
5. **作用域**: 请求的OIDC作用域（默认：openid profile email）

### 高级配置

- **启用OIDC登录**: 在登录页面显示OIDC登录按钮
- **自动创建用户**: 如果用户不存在，自动创建WordPress用户账户
- **自动链接用户**: 自动链接OIDC用户与现有WordPress用户（基于邮箱）

## 支持的OIDC提供商

- Google OAuth 2.0
- Microsoft Azure AD
- Auth0
- Keycloak
- 其他符合OIDC标准的提供商

## 使用说明

### 管理员配置

1. 在OIDC提供商处注册您的WordPress站点
2. 获取客户端ID和客户端密钥
3. 设置发现URL（通常是OIDC提供商的根URL）
4. 配置重定向URI（通常是您的WordPress登录页面）
5. 保存设置并测试连接

### 用户登录

1. 用户访问WordPress登录页面
2. 点击"OIDC登录"按钮
3. 重定向到OIDC提供商进行认证
4. 认证成功后返回WordPress并自动登录

## 数据库结构

插件会创建以下数据库表：

```sql
CREATE TABLE `wp_oidc_users` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `wp_user_id` bigint(20) NOT NULL,
  `oidc_subject` varchar(255) NOT NULL,
  `oidc_provider` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oidc_subject` (`oidc_subject`, `oidc_provider`),
  KEY `wp_user_id` (`wp_user_id`)
);
```

## 钩子和过滤器

### 动作钩子

- `oidc_wp_user_created`: 当新用户通过OIDC创建时触发
- `oidc_wp_user_linked`: 当现有用户与OIDC账户链接时触发
- `oidc_wp_login_success`: 当OIDC登录成功时触发

### 过滤器

- `oidc_wp_user_data`: 过滤从OIDC获取的用户数据
- `oidc_wp_username_generation`: 自定义用户名生成逻辑
- `oidc_wp_redirect_after_login`: 自定义登录后的重定向URL

## 故障排除

### 常见问题

1. **"OIDC发现URL未配置"错误**
   - 检查发现URL是否正确
   - 确保URL可以正常访问

2. **"令牌交换失败"错误**
   - 验证客户端ID和密钥是否正确
   - 检查重定向URI是否匹配

3. **用户创建失败**
   - 确保WordPress有创建用户的权限
   - 检查邮箱地址是否有效

### 调试模式

启用WordPress调试模式以获取详细错误信息：

```php
// 在wp-config.php中添加
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 安全注意事项

- 定期更新插件版本
- 使用HTTPS协议
- 保护客户端密钥
- 定期审查用户权限
- 监控登录活动

## 更新日志

### 版本 1.0.0
- 初始版本发布
- 支持基本的OIDC认证
- 用户自动创建和链接
- 管理界面设置

## 贡献

欢迎提交问题报告和功能请求！

## 许可证

GPL v2 或更高版本

## 支持

如果您需要技术支持，请：

1. 查看本文档的故障排除部分
2. 检查WordPress错误日志
3. 在GitHub上提交问题报告

## 致谢

感谢所有为OpenID Connect标准做出贡献的开发者和组织。
