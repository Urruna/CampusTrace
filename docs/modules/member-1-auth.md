# 成员 1：账号、邀请码与请求安全

状态：待实现

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

登录基础实现已存在于公共骨架，成员 1 继续补齐注册和邀请码激活。

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

