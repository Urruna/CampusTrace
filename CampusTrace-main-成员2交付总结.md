# CampusTrace 成员 2 交付总结

完成日期：2026-11-10  
项目目录：`D:\0nwpu\CampusTrace-main\CampusTrace-main`  
模块范围：帖子、检索、状态、举报与管理员下架  
数据库结构：未修改，沿用冻结的 `posts`、`reports`、`audit_events` 等七张表

## 1. 完成结论

成员 2 的业务代码、页面、路由、自动化测试和模块说明已经完成。帖子可以发布、公开检索、筛选、查看详情、编辑、结帖、举报和下架；关键写操作会记录最小审计事件。

本次按 baseline 要求保留了三个可复现的教学安全缺口：

- R05：编辑入口只检查已激活登录，不检查帖子所有者。
- R08：详情描述原样作为 HTML 输出；成员 2 写请求不校验 CSRF。
- R10：不限制重复举报；普通用户可访问举报审核入口并下架帖子。

这些行为已在代码中使用 `SECURITY-GAP Rxx` 注释，在页面和模块文档中明确说明，并通过自动化用例设计为预期复现结果，不能误记为安全防护成功。

## 2. 已实现功能

| 功能 | 路由 | 结果 |
| --- | --- | --- |
| 公开列表、关键词检索、类型/状态筛选 | `GET /` | 只返回 `open`、`resolved` 帖子 |
| 帖子详情 | `GET /posts/{id}` | 已下架、不存在或不可公开访问时返回 404 |
| 发布帖子 | `GET/POST /posts/new` | 要求激活账号，校验五个业务字段 |
| 编辑帖子 | `GET/POST /posts/{id}/edit` | 正常编辑可用，按 R05 故意不校验归属 |
| 标记已解决 | `POST /posts/{id}/resolve` | 只允许发布者，状态变为 `resolved` |
| 提交举报 | `POST /posts/{id}/reports` | 要求激活账号，写入待处理举报和审计 |
| 举报审核列表 | `GET /admin/reports` | 支持状态筛选，按 R10 暂不校验管理员角色 |
| 下架帖子 | `POST /admin/posts/{id}/remove` | 帖子变为 `removed`，待处理举报变为 `resolved` |

公开列表和详情不查询 `users.contact_text`。所有数据库操作使用 MySQLi 预处理语句。帖子创建、编辑、结帖、举报创建和下架使用事务写入 `audit_events`。

## 3. 主要文件

新增：

- `app/models/ReportRepository.php`
- `app/controllers/AdminController.php`
- `app/views/posts/form.php`
- `app/views/admin/reports.php`
- `tests/security/test_posts.php`
- `tests/security/README.md`

修改：

- `app/models/PostRepository.php`
- `app/controllers/PostController.php`
- `app/views/posts/show.php`
- `app/helpers.php`
- `app/views/layout.php`
- `public/index.php`
- `public/assets/app.css`
- `README.md`
- `docs/modules/member-2-posts.md`
- `docs/test-matrix.md`

共享文件改动已限制在成员 2 路由接入、状态文字、操作入口和必要页面样式范围内，没有修改数据库表结构。

## 4. Baseline 风险复现入口

| 风险 | 复现方式 | 预期结果 |
| --- | --- | --- |
| R05 | `bob` 登录后向 `/posts/{alice帖子id}/edit` 提交修改 | 修改成功，数据库帖子归属不变 |
| R08 | 不带 CSRF 发布含 `<mark data-r08="...">` 的描述 | 请求成功，详情中标记按 HTML 输出 |
| R10 | `bob` 连续提交两次相同举报，再访问举报页并下架 | 两次举报成功，普通用户可审核和下架 |

正常结帖权限不受 R05 影响：`bob` 尝试结帖 `alice` 的帖子会返回 403，`alice` 结帖成功。

## 5. 测试与验证

已执行：

- 对 `app/`、`public/`、`tests/` 下全部 25 个 PHP 文件执行 PHP 语法检查，全部通过。
- 扫描 PHP 文件，未发现 `match`、箭头函数、`str_contains`、nullsafe 等 PHP 7.4/8.x 专属语法。
- 静态复核控制器依赖、路由处理、状态值和审计写入。

未执行：

- 浏览器端和 HTTP 端到端回归。课程虚拟机 `192.168.111.148:8080` 当前连接超时，承载实验机的 SSH 主机也连接超时。
- PHP 7.3.4 实机运行。语法检查使用临时 PHP 8.3.0，仅作为解析器；项目仍按 PHP 7.3.4 语法约束编写。

已准备 `tests/security/test_posts.php`，共 15 个断言，覆盖：

- `alice`、`bob` 登录。
- 发布、详情、关键词/类型/状态筛选。
- 发布者编辑、他人编辑（R05）。
- 非发布者结帖拒绝、发布者结帖成功。
- 无 CSRF 写入和标签样式文本原样输出（R08）。
- 重复举报、普通用户访问审核并下架（R10）。
- 下架后详情 404、公开搜索不出现。
- 非法发帖字段返回 422。

实验机恢复后的运行命令：

```powershell
& 'C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe' `
  .\tests\security\test_posts.php `
  http://localhost:8080
```

## 6. 演示链路

1. 使用 `alice` 登录并发布帖子。
2. 在首页用关键词、类型和状态筛选后打开详情。
3. 使用 `bob` 修改 `alice` 的帖子，展示 R05。
4. 使用 `bob` 尝试结帖，预期 403；切回 `alice` 后结帖成功。
5. 使用 `bob` 重复提交相同举报，展示 R10。
6. 使用 `bob` 访问 `/admin/reports` 并下架帖子，展示 R10。
7. 再访问详情和搜索，确认下架后公开入口失效。

## 7. 交接注意事项

- 成员 3 的图片接口必须连接帖子状态，下架后停止提供旧图片。
- 成员 4 的认领和联系方式入口必须检查帖子不是 `removed`。
- 集成负责人合并时需重点确认共享路由、布局、Helpers 和 CSS 与其他成员改动是否冲突。
- 实验机恢复后先重新导入 `database/02_seed.sql`，再执行成员 2 脚本并记录实际结果。
- 当前测试脚本会新增测试数据并下架其新建帖子；正式演示前建议重置种子数据。
