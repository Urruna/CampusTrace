# CampusTrace Baseline 实施框架

> 项目名称：CampusTrace  
> 项目定位：A Secure Campus Lost-and-Found System  
> 当前目标：完成可运行、可复现、可分工的 baseline，暂不实现 hardened  
> 依据：[W08 失物招领网站方案](W08失物招领网站.md) 与 [2026 软件安全大作业](2026软件安全大作业.pdf)

## 1. 文档目的

本文档用于统一 CampusTrace baseline 的功能范围、数据结构、页面与接口边界、四人分工、验收方式和 GitHub 汇总流程。

baseline 不是半成品。它必须先完整跑通业务，再保留少量经过明确编号的安全缺口，作为 hardened 版本的对照起点。

开发原则：

1. 先建立一套公共骨架，四个人在同一个骨架上开发。
2. baseline 和 hardened 使用相同的业务、数据结构和测试口径。
3. baseline 中的安全缺口必须标注风险编号，并且只能在本地的模拟环境中出现。
4. 每个模块同时交付页面或组件、后端逻辑、测试和说明。
5. baseline 冻结并通过 12 组以上测试后，才开始 hardened。

## 2. Baseline 功能范围

### 2.1 必须完成的功能

| 模块 | 功能 | Baseline 状态 |
| --- | --- | --- |
| 账号 | 注册、邀请码激活、登录、退出、普通用户/管理员角色 | 完成正常流程 |
| 帖子 | 发布寻物或招领帖、列表、关键词检索、类别/状态筛选、详情、编辑本人帖子、标记已解决 | 完成正常流程 |
| 图片 | 每帖上传一张 JPEG 或 PNG、预览、公开显示、替换图片 | 完成正常流程，保留受控的图片安全缺口 |
| 联系 | 提交认领申请、查看本人申请、发布者批准/拒绝/撤回、获批申请人查看模拟联系方式 | 完成正常流程，保留授权校验缺口 |
| 审核 | 激活用户举报、管理员查看举报、下架帖子 | 完成最小流程 |
| 审计 | 记录与帖子状态、认领审批、举报处理相关的关键事件 | 完成最小记录 |

### 2.2 明确不进入 baseline 的功能

- 图片手动遮挡。
- EXIF/GPS 元数据清理和公开图重新编码。
- 一次性联系方式令牌。
- 登录限速、验证码和复杂防滥用策略。
- 实时聊天、地图、通知、推荐和图片识别。
- 完整管理后台和复杂统计面板。
- hardened 版本的安全修复。

这些功能属于后续增强，不应阻塞 baseline 冻结。

### 2.3 固定业务规则

1. 游客只能查看 `open` 和 `resolved` 帖子。
2. `removed` 帖子不出现在列表、详情和公开图片接口中。
3. 只有激活账号可以发布、认领、举报和修改资料。
4. 普通用户只能编辑自己发布的帖子。
5. 只有帖子发布者可以批准、拒绝或撤回该帖的认领申请。
6. 同一帖子任意时刻最多只有一个有效获批申请人。
7. 批准新的申请人时，旧申请自动变为 `revoked`。
8. 联系方式只保存模拟数据，不上传真实手机号或社交账号。
9. 已解决帖子停止接受新认领；是否继续显示联系方式由 hardened 修复。
10. 管理员只能处理举报和下架，不因管理员身份自动获得联系方式读取权。

### 2.4 状态定义

帖子状态：

| 状态 | 含义 |
| --- | --- |
| `open` | 正常开放 |
| `resolved` | 发布者已标记解决 |
| `removed` | 管理员下架 |

认领申请状态：

| 状态 | 含义 |
| --- | --- |
| `pending` | 等待发布者处理 |
| `approved` | 当前有效获批 |
| `rejected` | 已拒绝 |
| `revoked` | 曾批准后被撤回或被新批准替换 |

举报状态：

| 状态 | 含义 |
| --- | --- |
| `pending` | 等待管理员处理 |
| `resolved` | 已处理并产生处置结果 |
| `dismissed` | 审核后不采取下架措施 |

## 3. 技术方案

### 3.1 运行环境

- Windows Server 虚拟机。
- PHPStudy + Nginx + MySQL。
- 当前课程环境固定为 PHP 7.3.4 NTS，代码不得使用 PHP 7.4/8.x 语法。
- 使用 PHP Session 和 `mysqli`，baseline 不引入 Composer 或额外框架。
- CampusTrace 独立 Nginx 站点根目录指向 `public/`，不能直接暴露 `app/`、`database/` 和 `storage/`。
- MySQL 只供虚拟机内的 PHP 使用，默认连接 `127.0.0.1:3306`。

开工前必须验证：

- `mysqli`
- `fileinfo`
- `gd`
- `imagecreatefrompng`
- `imagecreatefromjpeg`
- 上传大小限制
- Nginx 从虚拟机和宿主机可以访问

### 3.2 目标目录

```text
CampusTrace/
├─ README.md
├─ baseline.md
├─ W08失物招领网站.md
├─ 2026软件安全大作业.pdf
├─ app/
│  ├─ bootstrap.php
│  ├─ config.example.php
│  ├─ database.php
│  ├─ auth.php
│  ├─ helpers.php
│  ├─ controllers/
│  ├─ models/
│  ├─ services/
│  └─ views/
│     ├─ layout.php
│     ├─ auth/
│     ├─ posts/
│     ├─ claims/
│     └─ admin/
├─ public/
│  ├─ index.php
│  └─ assets/
│     ├─ app.css
│     └─ app.js
├─ database/
│  ├─ 01_schema.sql
│  ├─ 02_seed.sql
│  └─ 99_reset.sql
├─ storage/
│  └─ baseline_images/
├─ tests/
│  ├─ smoke/
│  ├─ security/
│  └─ fixtures/
└─ docs/
   ├─ module-interface.md
   ├─ test-matrix.md
   └─ modules/
```

`public/` 是唯一允许被 Apache 访问的应用目录。`storage/` 必须位于站点根目录之外，并加入 `.gitignore`。

### 3.3 配置与数据

- 所有数据库和路径配置从 `app/config.example.php` 复制为本地配置。
- 本地配置文件和真实凭据不提交到 GitHub。
- 提供固定测试账号、邀请码、帖子和图片样本。
- 数据库初始化和重置只允许操作 CampusTrace 自己的数据库。
- baseline 使用独立数据库名，例如 `campustrace_baseline`。
- hardened 以后使用 `campustrace_hardened` 和独立图片目录。

## 4. 数据库设计

表中时间为本地课程环境时间；密码只保存 `password_hash()` 的结果，不保存明文。

### 4.1 核心表

| 表 | 关键字段 | 用途 |
| --- | --- | --- |
| `users` | `id`, `username`, `password_hash`, `role`, `contact_text`, `activated_at`, `created_at` | 用户、角色、激活状态和模拟联系方式 |
| `invite_codes` | `id`, `code_hash`, `expires_at`, `used_by`, `used_at` | 一次性邀请码 |
| `posts` | `id`, `owner_id`, `post_type`, `title`, `description`, `category`, `location`, `status`, `current_image_id`, `created_at`, `updated_at` | 寻物/招领信息 |
| `post_images` | `id`, `post_id`, `storage_name`, `mime_type`, `file_size`, `width`, `height`, `created_at` | 图片元数据 |
| `claims` | `id`, `post_id`, `requester_id`, `reason`, `clue`, `status`, `decided_by`, `decided_at`, `created_at` | 认领申请和授权状态 |
| `reports` | `id`, `post_id`, `reporter_id`, `reason`, `status`, `handled_by`, `handled_at`, `created_at` | 举报与处理 |
| `audit_events` | `id`, `actor_id`, `action`, `target_type`, `target_id`, `result`, `created_at` | 最小审计记录 |

### 4.2 约束要求

- `users.username` 唯一。
- `invite_codes.code_hash` 唯一。
- `post_images.storage_name` 唯一。
- `posts.current_image_id` 必须属于同一个帖子。
- 图片本体不存入数据库，只存存储标识和元数据。
- 联系方式保存在 `users.contact_text`，baseline 约定每个用户只有一个模拟联系方式。
- 不能只依赖前端保证“一个帖子只有一个 approved 申请”，批准操作必须在数据库事务中完成。
- 删除或下架帖子时，不依赖删除图片文件来表示访问控制；访问控制由服务端查询判断。

## 5. 权限矩阵

| 操作 | 游客 | 未激活账号 | 激活用户 | 帖子发布者 | 管理员 |
| --- | --- | --- | --- | --- | --- |
| 查看开放帖子 | 允许 | 允许 | 允许 | 允许 | 允许 |
| 发布帖子 | 拒绝 | 拒绝 | 允许 | 允许 | 允许 |
| 编辑自己的帖子 | 拒绝 | 拒绝 | 允许 | 允许 | 不默认允许 |
| 编辑他人帖子 | 拒绝 | 拒绝 | 拒绝 | 拒绝 | 不默认允许 |
| 上传/替换图片 | 拒绝 | 拒绝 | 仅本人帖子 | 仅本人帖子 | 不默认允许 |
| 提交认领 | 拒绝 | 拒绝 | 允许 | 自己不能认领自己的帖子 | 允许 |
| 处理认领 | 拒绝 | 拒绝 | 拒绝 | 仅本人帖子 | 拒绝 |
| 阅读联系方式 | 拒绝 | 拒绝 | 仅获批申请人 | 可查看本人联系方式 | 无自动读取权 |
| 举报 | 拒绝 | 拒绝 | 允许 | 允许 | 允许 |
| 下架帖子 | 拒绝 | 拒绝 | 拒绝 | 拒绝 | 允许 |

“激活用户”不是固定角色；激活状态和角色需要分开判断。`owner` 是用户和帖子之间的关系，不是第四种账号角色。

## 6. Baseline 保留的安全缺口

缺口必须同时在代码注释、模块说明和测试矩阵中使用相同编号。

| 风险编号 | Baseline 行为 | 后续 hardened 方向 |
| --- | --- | --- |
| R01 | 图片只检查扩展名，不验证真实内容 | 检查真实类型、大小、尺寸、可解码性 |
| R02 | 公开接口直接返回原始图片 | 重新编码公开图并清理敏感元数据 |
| R04 | 图片接口只按图片编号查询，换图后旧图仍可读取 | 校验帖子状态和当前图片引用 |
| R05 | 编辑和换图入口只检查登录 | 服务端检查帖子所有者 |
| R06 | 已登录用户读取联系方式时不核对认领授权 | 绑定申请人、帖子和 approved 状态 |
| R07 | 联系方式可能进入公开响应或旧入口 | 字段白名单、独立受控入口、禁止缓存 |
| R08 | 部分帖子文本直接输出，写请求没有 CSRF 校验 | 按上下文编码并校验 CSRF 令牌 |
| R09 | 换图先替换旧引用，再处理失败回退 | 新图成功后再切换引用并清理失败产物 |
| R10 | 重复申请/举报和普通用户管理员操作缺少完整限制 | 限频、重复检查和角色校验 |

允许保留缺口，但不能：

- 上传或执行真实恶意程序。
- 部署到公网。
- 使用真实用户联系方式或敏感图片。
- 为了方便演示而关闭整个服务器或数据库。
- 把环境错误记录成安全防护成功。

## 7. 页面与接口约定

所有页面通过 `public/index.php` 进入，业务代码放在 `app/`。建议先使用服务端渲染，避免前后端接口进一步增加工作量。

| 方法 | 路径 | 功能 | 负责人 |
| --- | --- | --- | --- |
| GET | `/` | 帖子列表和筛选 | 成员 2 |
| GET | `/posts/{id}` | 帖子详情 | 成员 2 |
| GET/POST | `/register` | 注册 | 成员 1 |
| GET/POST | `/activate` | 邀请码激活 | 成员 1 |
| GET/POST | `/login` | 登录 | 成员 1 |
| POST | `/logout` | 退出 | 成员 1 |
| GET/POST | `/posts/new` | 发布帖子 | 成员 2 |
| GET/POST | `/posts/{id}/edit` | 编辑本人帖子 | 成员 2 |
| POST | `/posts/{id}/resolve` | 标记已解决 | 成员 2 |
| POST | `/posts/{id}/image` | 上传或替换图片 | 成员 3 |
| GET | `/images/{id}` | 读取公开图片 | 成员 3 |
| POST | `/posts/{id}/claims` | 提交认领申请 | 成员 4 |
| GET | `/claims/{id}` | 查看本人申请或发布者待审申请 | 成员 4 |
| POST | `/claims/{id}/approve` | 批准申请 | 成员 4 |
| POST | `/claims/{id}/reject` | 拒绝申请 | 成员 4 |
| POST | `/claims/{id}/revoke` | 撤回授权 | 成员 4 |
| GET | `/claims/{id}/contact` | 查看模拟联系方式 | 成员 4 |
| POST | `/posts/{id}/reports` | 提交举报 | 成员 2 |
| GET | `/admin/reports` | 管理员查看举报 | 成员 2 |
| POST | `/admin/posts/{id}/remove` | 管理员下架帖子 | 成员 2 |

共享约定：

- 所有写操作使用 POST，不在 GET 中修改数据。
- 所有数据库查询使用参数化 SQL；SQL 安全是两版共有基础，不作为 baseline 缺口。
- 页面通过统一 `layout.php` 渲染。
- 跨成员调用只使用文档规定的函数或服务，不直接复制其他人的代码。
- 失败返回统一页面或统一 JSON 结构，不能把数据库异常直接显示给游客。

## 8. 四人分工

建议先指定一位“整合负责人”。整合负责人仍承担一个模块，但额外维护公共骨架、冻结接口、合并和 baseline 验收。

### 成员 1：账号、邀请码与公共请求基础

主要文件：

```text
app/auth.php
app/controllers/AuthController.php
app/views/auth/
database/02_seed.sql 中的账号与邀请码部分
tests/security/test_auth.php
docs/modules/member-1-auth.md
```

交付：

- 注册、登录、退出和激活页面。
- `password_hash()` / `password_verify()`。
- Session 中的用户身份。
- 激活状态和普通用户/管理员判断。
- 一次性邀请码。
- 供其他模块调用的 `current_user()`、`require_login()`、`require_active_user()`、`require_admin()`。
- 账号和邀请码测试。

### 成员 2：帖子、检索、状态与举报

主要文件：

```text
app/controllers/PostController.php
app/controllers/AdminController.php
app/models/Post.php
app/models/Report.php
app/views/posts/
app/views/admin/
tests/security/test_posts.php
docs/modules/member-2-posts.md
```

交付：

- 发布、列表、检索、筛选和详情。
- 本人编辑和标记已解决。
- 举报和最小管理员下架流程。
- 基线和后续 hardened 共用的状态字段。
- R05、R08、R10 的 baseline 复现入口。
- 帖子和举报测试。

### 成员 3：图片上传、显示与替换

主要文件：

```text
app/services/ImageService.php
app/controllers/ImageController.php
app/views/posts/image-preview.php
tests/fixtures/images/
tests/security/test_images.php
docs/modules/member-3-images.md
```

交付：

- 接收 JPEG/PNG。
- 保存图片到 `storage/baseline_images/`。
- 保存图片元数据到 `post_images`。
- 按图片编号读取公开图片。
- 替换图片的 baseline 流程。
- R01、R02、R04、R09 的 baseline 复现入口。
- 正常图片、改名校验、替换和旧链接测试。

### 成员 4：认领申请与联系授权

主要文件：

```text
app/controllers/ClaimController.php
app/models/Claim.php
app/views/claims/
tests/security/test_claims.php
docs/modules/member-4-claims.md
```

交付：

- 提交申请、查看申请、批准、拒绝和撤回。
- 保证同一帖子最多一个有效获批申请人。
- 发布者处理自己帖子下的申请。
- 模拟联系方式读取入口。
- R06、R07 的 baseline 复现入口。
- 正常审批、越权审批、未授权读取和状态变化测试。

### 整合负责人

整合负责人负责：

- 建立公共骨架、路由和页面模板。
- 冻结 `users.id`、`posts.id`、`posts.owner_id`、图片接口和状态值。
- 维护数据库建表和种子脚本。
- 审核跨模块权限调用。
- 按顺序合并分支。
- 重装数据库、运行完整测试并打 `baseline` 标签。
- 维护 README、测试矩阵和最终提交材料。

整合负责人不能替其他成员补完整个模块，否则个人贡献和答辩解释会出现问题。

## 9. 汇总方式

不能由四个人各做一个完整网站，最后复制页面拼接。正确流程如下。

### 9.1 先提交公共骨架

整合负责人先完成并提交：

1. 目录结构。
2. `public/index.php` 路由入口。
3. 数据库连接和基础配置。
4. `layout.php`、公共 CSS 和错误提示。
5. 七张核心表及测试种子。
6. 账号与帖子之间的共享接口约定。
7. README 和 `docs/module-interface.md`。

公共骨架通过“登录 → 打开列表 → 打开详情”最小流程后，冻结为 `skeleton-v0.1`。

### 9.2 每个人从同一骨架建立功能分支

建议分支：

```text
main
integration/baseline
feature/account
feature/posts
feature/images
feature/claims
```

四个人都从 `integration/baseline` 创建自己的功能分支，不直接修改 `main`。

修改范围要求：

- 成员只修改自己负责的目录。
- 公共模板、公共 CSS、数据库表结构、`bootstrap.php` 和路由表由整合负责人审核后修改。
- 如果必须改动其他人的文件，在 PR 中说明原因并请求对方确认。
- 新增数据库结构使用带编号的迁移文件，不在自己的分支里随意改表。

### 9.3 每份模块设计统一写成同一格式

每位成员在 `docs/modules/` 提交一份说明，至少包含：

```text
模块名称：
负责人：
目标：
页面和路由：
涉及的数据表：
输入与输出：
调用其他模块的接口：
Baseline 风险编号：
正常测试：
异常测试：
已知限制：
演示步骤：
```

这样整合时比较的是接口和代码，而不是四份风格不同的设计文档。

### 9.4 PR 必须携带的内容

每个功能 PR 至少说明：

- 完成的功能。
- 新增或修改的文件。
- 页面路径和路由。
- 数据库变更。
- 对其他模块的依赖。
- 已运行的测试和结果。
- 保留的 baseline 风险。
- 未完成事项。

“代码能打开”不等于完成。PR 还必须提供正常流程和至少一个异常流程的验证记录。

### 9.5 按依赖顺序合并

建议顺序：

1. 公共骨架。
2. `feature/account`。
3. `feature/posts`。
4. `feature/images`。
5. `feature/claims`。
6. 举报和管理员下架。
7. 测试矩阵与 README。
8. 整站回归。

每合并一个模块：

1. 重置 baseline 数据库。
2. 清理测试图片目录。
3. 重新导入 `database/02_seed.sql`。
4. 运行模块测试。
5. 运行已完成模块的回归测试。
6. 更新 `docs/test-matrix.md`。

不要等四个人全部完成后才第一次联调。账号和帖子应最先合并，图片随后接入帖子，认领最后接入帖子和账号。

### 9.6 冲突处理

- 路由冲突由整合负责人统一解决。
- 数据库冲突以冻结的表结构为准；需要改表时先统一修改迁移脚本。
- 页面样式冲突保留公共组件，模块专属样式放回各自组件。
- 权限冲突以权限矩阵为准，不以某个模块当前是否方便为准。
- 冲突解决后必须重新跑双方模块的测试。

### 9.7 冻结 baseline

满足以下条件后才能标记 `baseline`：

- 四项 W08 基础功能和最小审核流程可运行。
- 固定测试数据可重复初始化。
- 12 组以上测试有实际结果。
- 计划的 R01、R04、R05、R06、R07、R09 等缺口可复现。
- 正常功能没有被安全缺口意外破坏。
- README 写明 PHP、MySQL、Apache、扩展和访问地址。
- 两个版本都能使用相同的账号、帖子和图片初始化。
- 四个模块都有负责人、文件、路由、测试和贡献说明。
- 没有真实联系方式、敏感图片或公网服务依赖。

冻结后，在 Git 中创建 `baseline` 标签；hardened 从冻结版本单独建立分支。不要继续直接改 baseline。

## 10. Baseline 测试清单

先建立以下 14 组用例，后续可继续扩展。

| 编号 | 场景 | Baseline 预期 |
| --- | --- | --- |
| AUTH-01 | 有效邀请码注册并激活、登录、退出 | 正常完成 |
| AUTH-02 | 过期或重复使用邀请码 | 拒绝激活 |
| POST-01 | 发布、列表、关键词检索、类别筛选、详情 | 正常完成 |
| POST-02 | 发布者编辑自己的帖子 | 正常完成 |
| POST-03 | 用户乙通过请求修改用户甲的帖子 | Baseline 错误地放行，记录 R05 |
| IMG-01 | 上传正常 PNG/JPEG 并显示 | 正常完成 |
| IMG-02 | 普通文本改名为 `.png` | Baseline 错误地接收，记录 R01 |
| IMG-03 | 替换图片后访问旧图片地址 | Baseline 仍可读取，记录 R04 |
| CLAIM-01 | 提交认领、发布者批准、获批用户查看联系方式 | 正常完成 |
| CLAIM-02 | 已激活但未获批用户访问联系方式 | Baseline 错误地放行，记录 R06/R07 |
| STATE-01 | 帖子标为已解决或下架后访问旧联系/图片入口 | Baseline 暴露旧入口，记录 R04/R07 |
| SEC-01 | 标题包含无害标签样式文本，提交缺少 CSRF 的写请求 | Baseline 原样输出且接受请求，记录 R08 |
| ADMIN-01 | 普通用户提交下架动作 | Baseline 缺少完整角色限制，记录 R10 |
| FILE-01 | 图片替换中途失败 | Baseline 产生旧图丢失或孤立文件，记录 R09 |

每条测试必须记录：

- 用例编号。
- 初始数据。
- 当前用户和角色。
- 请求地址与请求方法。
- 预期结果。
- 实际结果。
- 数据库变化。
- 文件变化。
- 证据位置。
- 对应的风险编号或模块负责人。

## 11. 页面骨架

Baseline 先做以下页面，不追求复杂视觉：

1. 首页帖子列表。
2. 搜索与筛选区。
3. 帖子详情。
4. 发布/编辑表单。
5. 图片预览。
6. 注册、激活、登录页面。
7. 认领申请表单。
8. 发布者申请管理页。
9. 举报表单。
10. 管理员举报列表。

共用组件：

- 顶部导航。
- 用户身份和退出入口。
- 成功/错误提示。
- 表单字段与错误文本。
- 帖子状态标签。
- 认领状态标签。

视觉参考已写在原方案的“统一前端风格”中。Baseline 只要求统一、可用和移动端不溢出，不要求做成完整产品。

## 12. 部署与访问

### 12.1 推荐部署

- 项目放在虚拟机 PHPStudy 的网站目录。
- CampusTrace 独立 Nginx 配置将站点根目录指向 `public/`。
- MySQL 与 PHP 在同一虚拟机。
- 上传文件写入 `storage/baseline_images/`。
- 不在网站根目录提供原始图片。
- 虚拟机内访问 `http://localhost:8080/`。

### 12.2 主机访问虚拟机

允许宿主机访问时：

1. 在虚拟机中查看 `ipconfig`，确认 VMnet 地址。
2. 确认 Nginx 监听虚拟机网卡，而不只监听 `127.0.0.1`。
3. 只允许 VMnet 私有网段的 HTTP 入站。
4. 不开放 MySQL 给宿主机。
5. 不在公网路由器上做端口转发。

IIS 已占用虚拟机 80 端口，因此 CampusTrace 使用独立的 8080 端口，不修改 IIS 和 PHPStudy 默认站点。实验二公共代码中的 `local_only` 会拒绝宿主机访问，CampusTrace 不能直接复制后保持原样。

## 13. 开工顺序

### 阶段 A：公共骨架

- 验证 PHP 和扩展。
- 建目录、配置样例、路由和布局。
- 建七张表、导入种子数据。
- 建立 README 和模块说明模板。
- 完成最小列表、详情和登录流程。

### 阶段 B：业务模块并行

- 成员 1 完成账号和邀请码。
- 成员 2 完成帖子和举报。
- 成员 3 完成图片上传和显示。
- 成员 4 完成认领和联系方式流程。

### 阶段 C：集成

- 按依赖顺序合并。
- 每合并一个模块就重置数据并跑回归。
- 修复接口不一致，不提前实现 hardened。

### 阶段 D：冻结

- 完成 14 组 baseline 测试。
- 补齐贡献说明和运行说明。
- 整理演示路径。
- 打 `baseline` 标签。

## 14. GitHub 提交建议

在完整骨架和本文档通过组内确认后，再初始化或推送到 GitHub。上传前检查：

- 不提交本地数据库密码。
- 不提交 `storage/` 中的测试上传文件。
- 不提交真实图片、真实联系方式或大体积临时产物。
- `.gitignore` 覆盖本地配置、存储目录、日志和编辑器临时文件。
- README 可以按步骤恢复数据库和测试账号。
- 每个成员的分支和 PR 能对应到具体模块。
- baseline 标签只在整站验收后创建。

最终推荐提交结构：

```text
README.md                 项目定位、运行方式、当前状态
baseline.md               baseline 实施和汇总规则
docs/                     接口、测试矩阵、模块说明
app/ public/ database/    源码和初始化材料
tests/                    测试脚本、固定样本和结果
```

## 15. 当前未完成事项

- 公共骨架已经部署到虚拟机，并通过 16 项宿主机冒烟测试。
- PHP、GD/JPEG、MySQL、Nginx 8080 和宿主机访问已在虚拟机中实测。
- Git 仓库尚未初始化。
- 四个业务模块尚未由成员实现。
- 四份具体模块说明和 PR 模板尚未创建。
- hardened 版本尚未开始，不能提前混入 baseline。

