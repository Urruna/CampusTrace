# 成员 2：帖子、检索、状态与举报

模块名称：帖子与举报审核
负责人：成员 2
目标：完成校园失物招领帖子的发布、公开检索、详情、本人管理、举报和管理员下架最小流程，并保留可复现的 baseline 安全缺口。
当前状态：代码、页面、路由、文档和自动化脚本已完成；实验虚拟机恢复后执行最终 HTTP 回归。

## 页面和路由

| 方法 | 路由 | 功能 | 当前行为 |
| --- | --- | --- | --- |
| GET | `/` | 公开列表、关键词/类型/状态筛选 | 只显示 `open`、`resolved` |
| GET | `/posts/{id}` | 帖子详情 | 已下架帖子返回 404 |
| GET | `/posts/new` | 发布页 | 要求已激活登录 |
| POST | `/posts/new` | 创建帖子 | 校验字段并写入审计 |
| GET | `/posts/{id}/edit` | 编辑页 | baseline 只检查已激活登录（R05） |
| POST | `/posts/{id}/edit` | 保存编辑 | baseline 不校验帖子归属（R05） |
| POST | `/posts/{id}/resolve` | 标记已解决 | 只允许发布者 |
| POST | `/posts/{id}/reports` | 提交举报 | 允许重复举报（R10） |
| GET | `/admin/reports` | 举报列表和处理入口 | baseline 只检查已激活登录（R10） |
| POST | `/admin/posts/{id}/remove` | 下架帖子 | baseline 不校验管理员角色（R10） |

## 涉及的数据表

- `posts`：帖子公开字段、发布者、当前状态和更新时间。
- `reports`：举报人、原因、处理状态、处理人和时间。
- `audit_events`：帖子创建、编辑、结帖、下架和举报创建的最小审计事件。
- 读取 `users` 中的用户名和管理员角色；公开帖子查询不返回联系方式。

## 主要文件

```text
app/controllers/PostController.php
app/controllers/AdminController.php
app/models/PostRepository.php
app/models/ReportRepository.php
app/views/posts/form.php
app/views/posts/index.php
app/views/posts/show.php
app/views/admin/reports.php
tests/security/test_posts.php
```

## 输入与输出

发布和编辑字段：

- `post_type`：只接受 `lost`、`found`。
- `title`：必填，最多 120 个字符。
- `description`：必填，最多 10000 个字符。
- `category`：必填，最多 40 个字符。
- `location`：必填，最多 120 个字符。
- 举报 `reason`：必填，最多 500 个字符。

成功写操作返回 302 并跳转到详情或举报列表；字段错误返回 422；不存在或已下架的帖子返回 404；非发布者结帖返回 403。

## 调用其他模块的接口

- `require_active_user()`：登录和激活状态判断。
- `current_user()`：页面操作入口展示和审计操作者。
- `set_flash()`、`redirect()`、`render()`、`h()`：统一请求和页面反馈。
- `csrf_token()` / `verify_csrf()`：当前成员 2 写请求按 baseline 计划暂不调用（R08）。
- `db()`：使用唯一 MySQLi 连接。

## 交接要求

- 成员 3 的 `/images/{id}` 必须关联 `post_images.post_id` 查询 `posts.status`，下架后停止响应旧图片地址。
- 成员 4 的认领与联系方式入口必须检查帖子不是 `removed`，下架后停止新申请和联系方式读取。
- 成员 4 在已解决帖子进入 hardened 前后都不得接受新认领；baseline 的具体旧联系入口行为由成员 4 记录。
- 页面上的“申请认领”只在 `open` 状态显示，最终服务端状态检查仍由成员 4 负责。

## Baseline 风险编号

| 编号 | 保留行为 | 代码位置 | 预期复现 |
| --- | --- | --- | --- |
| R05 | 编辑页和保存只检查已激活登录，不检查 `posts.owner_id` | `PostController::editForm()`、`PostController::update()` | 用户乙可修改用户甲的帖子 |
| R08 | 描述在详情页按 HTML 原样输出；发布、编辑、结帖、举报和下架不校验 CSRF | `posts/show.php`、成员 2 写路由 | 无害 HTML 标记被解析，缺少 CSRF 的请求仍写入 |
| R10 | 不限制重复举报，不限制举报频率，管理员入口和动作不检查 `admin` 角色 | `ReportRepository::create()`、`AdminController` | 重复举报成功，普通用户可以查看举报并下架 |

代码中使用 `SECURITY-GAP Rxx` 注释标记缺口。页面也显示对应 R10 教学提示，避免把失败行为误认为安全防护。

## 正常测试

| 编号 | 场景 | 预期 |
| --- | --- | --- |
| POST-01 | 发布、列表检索、类型/状态筛选、详情 | 正常完成，公开字段可见 |
| POST-02 | 发布者编辑本人帖子 | 保存成功并更新审计 |
| POST-04 | 发布者标记已解决 | 状态变为 `resolved`，仍可公开查看 |
| REPORT-01 | 激活用户提交举报 | 写入 `pending` 举报和审计，帖子仍公开 |
| ADMIN-02 | 管理员下架帖子 | 状态变为 `removed`，相关待处理举报变为 `resolved` |
| STATE-02 | 下架后访问列表和详情 | 列表不出现，详情 404 |

## 异常测试

| 编号 | 场景 | Baseline 结果 |
| --- | --- | --- |
| POST-03 | 用户乙提交用户甲帖子的编辑 | 放行，记录 R05 |
| AUTH-05 | 用户乙尝试结帖用户甲的帖子 | 403 拒绝 |
| SEC-01 | 发帖不提交 CSRF，描述加入无害标记 | 接受请求且标记原样输出，记录 R08 |
| ADMIN-01 | 普通用户访问举报列表并下架 | 放行，记录 R10 |
| POST-05 | 类型非法或必填字段为空 | 422 拒绝，不写入数据库 |
| REPORT-02 | 同一用户重复提交相同举报 | Baseline 接受，记录 R10 |

自动化脚本：`tests/security/test_posts.php`。

## 已知限制

- 当前实现按 baseline 计划故意保留 R05、R08、R10，不能用于公开环境。
- 实验虚拟机当前不可达，因此本次只完成代码与静态复核；HTTP 测试脚本需在虚拟机恢复后执行。
- 图片组件和认领入口仍由成员 3、成员 4 接入。
- 管理员举报页只提供“下架帖子”处理动作，未增加驳回表单或完整管理后台。
- 列表最多返回 100 条，举报列表最多返回 200 条，baseline 未实现分页。

## 演示步骤

1. 使用 `alice` 登录，打开 `/posts/new` 发布测试帖子。
2. 回到首页使用关键词、类型和状态筛选，打开详情。
3. 使用 `bob` 登录，直接修改 alice 的帖子，观察 R05 被放行。
4. 使用 `bob` 尝试结帖，预期返回 403；切回 `alice` 后结帖成功。
5. 使用 `bob` 连续提交两次相同举报，观察 R10。
6. 使用 `bob` 打开 `/admin/reports` 并下架帖子，观察 R10。
7. 再次打开帖子详情与首页搜索，确认下架后公开入口失效。

