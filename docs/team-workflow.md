# CampusTrace 团队协作与集成规则

## 唯一代码基线

- 所有人从 `integration/baseline` 拉取公共骨架。
- 功能分支只能从最新 `integration/baseline` 创建。
- 不直接修改 `main`。
- PR 默认提交到 `integration/baseline`。
- 只有在整站通过验收后，才由整合负责人把 `integration/baseline` 合并到 `main`。

固定分支：

| 分支 | 用途 |
| --- | --- |
| `main` | 稳定发布版本 |
| `integration/baseline` | 公共骨架和模块集成 |
| `feature/account` | 账号、邀请码和请求安全 |
| `feature/posts` | 帖子、状态和举报 |
| `feature/images` | 图片上传、显示和替换 |
| `feature/claims` | 认领申请和联系授权 |

## 文件所有权

| 范围 | 主要所有者 |
| --- | --- |
| 账号和认证 | 成员 1 |
| 帖子和举报 | 成员 2 |
| 图片 | 成员 3 |
| 认领和联系方式 | 成员 4 |
| 公共骨架、路由、数据库、集成和发布 | 整合负责人 |

成员可以阅读所有代码，但修改其他人的模块前必须先沟通。

以下共享文件默认只能由整合负责人修改：

- `public/index.php`
- `app/bootstrap.php`
- `app/database.php`
- `app/helpers.php`
- `app/auth.php`
- `app/views/layout.php`
- `public/assets/app.css`
- `database/01_schema.sql`

需要修改共享文件时，PR 必须说明：

1. 当前行为有什么问题。
2. 为什么不能放在模块内部解决。
3. 会影响哪些成员。
4. 已经运行了哪些回归测试。

## 数据库规则

- `campustrace_baseline` 的七张表是公共契约，不能直接重定义。
- 不修改其他成员负责的数据字段含义。
- 需要新增字段或表时，新增独立迁移文件，例如 `database/03_add_claim_expiry.sql`。
- 迁移文件必须可重复审查，并说明导入顺序。
- 所有表使用 InnoDB 和 `utf8mb4_unicode_ci`。
- 所有 SQL 使用预处理语句。
- 应用只使用受限账号 `campustrace_app`，不把 root 密码写入代码。

## 配置和秘密

- 本地配置使用 `app/config.local.php`。
- `app/config.local.php` 已被 `.gitignore` 排除。
- 不提交数据库密码、GitHub Token、真实联系方式或真实照片。
- 示例配置只保留占位值。
- 测试账号和模拟数据可以提交。

## 状态值

不得自行增加同义状态：

- 帖子：`open`、`resolved`、`removed`
- 认领：`pending`、`approved`、`rejected`、`revoked`
- 举报：`pending`、`resolved`、`dismissed`
- 角色：`user`、`admin`

## 跨模块接口

公共接口见 `docs/module-interface.md`。

任何接口变更必须提前通知：

- 新增/删除函数参数。
- 返回数组字段变化。
- 状态值变化。
- 路由变化。
- 数据库字段变化。

接口变更后，所有调用方必须在同一个 PR 或紧随其后的集成 PR 中完成适配。

## Baseline 风险

- 每个保留缺口必须使用 `R01` 至 `R10` 编号。
- 代码中的缺口使用如下格式注释：

```php
// SECURITY-GAP R05: baseline intentionally checks login only.
```

- 每个缺口必须有明确复现条件。
- 未复现的风险标记为“未复现”，不能写成“已防护”。
- 不把环境缺失误记为安全控制生效。

## PR 流程

1. 从 `integration/baseline` 拉取最新代码。
2. 在自己的功能分支开发。
3. 至少运行本模块正常流程和异常流程。
4. 运行上一轮已合并模块的回归测试。
5. 按 `.github/PULL_REQUEST_TEMPLATE.md` 创建 PR。
6. 由整合负责人和至少一位相关成员检查。
7. 合并后由整合负责人重置测试数据并运行整站冒烟测试。

PR 不接受：

- 只有截图，没有可执行结果。
- 直接贴入整份网站。
- 修改共享文件但未说明影响。
- 提交本地配置和密码。
- 把功能缺失写成安全防护成功。

## 提交信息

提交信息需要说明改变了什么以及解决了什么问题：

```text
feat(auth): add one-time invite activation
fix(posts): enforce owner check when editing
test(images): cover renamed and oversized upload samples
docs(claims): define approval state transitions
```

禁止使用 `update`、`fix`、`test` 这类无信息量信息。

## 集成顺序

```text
账号
→ 帖子
→ 图片
→ 认领
→ 举报和管理员下架
→ 全量测试
→ baseline 冻结
```

每合并一个模块都要执行：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://<虚拟机IP>:8080
```

## 沟通规则

提前协商以下事项：

- 是否修改共享接口。
- 是否需要数据库迁移。
- 是否依赖其他成员的页面或服务。
- 是否增加 baseline 风险缺口。
- 页面是否超出既有外观规范。
- 是否发现同一安全问题的不同修复口径。

出现问题时应提供：

1. 请求或操作复现步骤。
2. 预期结果。
3. 实际结果。
4. 相关日志或状态。
5. 涉及的路由、文件和风险编号。

## 完成标准

一个模块只有同时满足以下条件才可合并：

- 正常功能可运行。
- 至少一个异常场景可复现或被正确拒绝。
- 页面遵循统一外观规范。
- 服务端权限与状态检查已落实。
- 不破坏已合并模块。
- 文档、测试和已知限制已更新。

