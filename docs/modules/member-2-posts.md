# 成员 2：帖子、检索、状态与举报

状态：列表和详情已由公共骨架提供，其余待实现

## 范围

- 发布寻物或招领帖。
- 关键词检索、类型和状态筛选。
- 查看详情。
- 编辑本人帖子。
- 标记已解决。
- 举报和管理员下架。
- 最小审计记录。

## 路由

- `GET /`
- `GET /posts/{id}`
- `GET|POST /posts/new`
- `GET|POST /posts/{id}/edit`
- `POST /posts/{id}/resolve`
- `POST /posts/{id}/reports`
- `GET /admin/reports`
- `POST /admin/posts/{id}/remove`

## 必须交接

- 帖子状态只允许 `open`、`resolved`、`removed`。
- 编辑和状态转换必须检查发布者或管理员权限。
- 公开查询不得返回联系方式。
- 下架后详情、图片和联系入口均由服务端停止响应。

## Baseline 缺口

- R05：编辑入口先保留“只检查登录”的受控缺口。
- R08：在指定位置保留输出或 CSRF 缺口，并用注释标记。
- R10：举报频率和管理员动作暂时缺少完整限制。

