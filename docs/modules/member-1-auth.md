# 成员 1：账号、邀请码与请求安全

状态：已实现并实测，待 PR 集成

## 范围

- 注册、邀请码激活、登录、退出。
- 当前用户、激活状态和管理员权限检查。
- CSRF 工具在写请求中的接入。
- 账号和邀请码种子数据。

## 路由

- `GET|POST /register`
- `GET|POST /activate`
- `GET|POST /login`
- `POST /logout`
- `GET|POST /account/contact`

登录基础实现已存在于公共骨架，成员 1 继续补齐注册和邀请码激活。

联系方式页面只允许当前激活用户更新自己的 `contact_text`，不接受用户 ID 参数。

## 必须交接

- `current_user()`
- `require_login()`
- `require_active_user()`
- `require_admin()`
- 邀请码一次性使用的服务端规则

## 测试重点

- 无效用户名和口令。
- 未激活账号。
- 过期邀请码。
- 已使用邀请码。
- 重复注册。
- 缺失或错误 CSRF 令牌。
- 正常注册后不能直接登录。
- 有效邀请码只能使用一次。
- 联系方式更新只影响当前用户。

## 实现约定

- 注册密码至少 10 个字符，使用 `password_hash()` 保存。
- 用户名允许 3—30 位字母、数字或下划线。
- 激活在数据库事务中完成，邀请码记录使用行锁。
- 激活失败统一返回模糊提示，避免用户和邀请码枚举。
- 本人联系方式更新需要激活账号和 CSRF 防护。

## 自动化验证

运行：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\account_flow.php
```

测试会创建临时用户和邀请码，结束后自动清理。
2026-11-10 实测 22 项通过。

