# CampusTrace 模块接口约定

本文件在 `skeleton-v0.1` 阶段冻结，成员不得自行创建第二套身份、数据库连接或状态值。

## 公共函数

| 接口 | 行为 |
| --- | --- |
| `db(): mysqli` | 返回唯一的 MySQLi 连接，字符集为 `utf8mb4` |
| `app_config($key)` | 返回本地配置值 |
| `current_user()` | 返回当前用户数组或 `null` |
| `require_login()` | 未登录时跳转登录页 |
| `require_active_user()` | 要求登录且 `activated_at` 非空 |
| `require_admin()` | 要求管理员角色 |
| `h($value)` | HTML 文本编码 |
| `csrf_token()` / `csrf_field()` / `verify_csrf()` | 写请求校验工具 |
| `render($view, $data)` | 使用统一布局渲染页面 |
| `set_flash($type, $message)` | 写入一次性提示 |

## 固定状态值

- 帖子：`open`、`resolved`、`removed`
- 认领：`pending`、`approved`、`rejected`、`revoked`
- 举报：`pending`、`resolved`、`dismissed`
- 角色：`user`、`admin`

## 固定数据关系

- `posts.owner_id` 指向 `users.id`。
- `post_images.post_id` 指向 `posts.id`。
- `posts.current_image_id` 指向当前公开图片。
- `claims.post_id` 和 `claims.requester_id` 共同确定一次申请。
- 同一帖子任意时刻最多一个 `approved` 认领，由服务端事务保证。

## 实现规则

- 所有 SQL 使用预处理语句。
- 所有写操作使用 POST。
- 普通页面输出使用 `h()`。
- 查询只选择页面需要的字段，公开页面不得查询联系方式。
- 修改帖子、图片、认领和举报前，服务端必须检查对象归属。
- 各模块新增页面时，将路由加入 `public/index.php` 并同步更新 README。

