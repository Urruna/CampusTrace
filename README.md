# CampusTrace

**A Secure Campus Lost-and-Found System**

CampusTrace 是一个用于《软件安全》课程大作业的本地失物招领系统。项目围绕图片上传、记录归属、认领授权、联系方式访问和旧入口失效开展安全设计与复测。

当前目录是 baseline 公共骨架，已包含：

- PHP 7.3.4 兼容的公共启动流程和路由入口。
- 注册、邀请码激活、登录、退出、本人联系方式管理。
- 帖子列表、检索和详情的最小可用流程。
- 用户、邀请码、帖子、图片、认领、举报、审计七张数据表。
- 固定的模拟账号、帖子和邀请码。
- 环境检查页。
- 独立的 Nginx 8080 配置和 Windows 启停脚本。
- 认领申请、发布者审批和模拟联系方式读取。
- 帖子图片上传、预览、受控读取和替换。
- 其余待实现模块的路由占位。

当前骨架已于 2026-11-08 部署到课程虚拟机，并通过宿主机冒烟测试。

尚未完成：

- 发帖、编辑和举报页面。
- 其他模块的 baseline 安全对照入口。
- 最终 14 组 baseline 回归测试。

## 环境

实测课程虚拟机环境：

```text
Windows Server 2019 Datacenter 64 位
PHPStudy 8.1.1.3
PHP 7.3.4 NTS
Nginx 1.15.11
MySQL 5.7.26
```

详细记录见 [docs/environment.md](docs/environment.md)。

## 部署

1. 将项目放在虚拟机的 `C:\phpstudy_pro\WWW\CampusTrace`。
2. 从 `app/config.example.php` 创建 `app/config.local.php`，填入本地数据库账号。
3. 在 MySQL 中创建 `campustrace_baseline` 和受限应用账号。
4. 按顺序导入 `database/01_schema.sql` 和 `database/02_seed.sql`。
5. 在虚拟机 PowerShell 中运行：

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy\windows\start-campustrace.ps1
```

IIS 默认占用 80 端口，因此 CampusTrace 使用独立的 8080 端口，不修改现有 IIS 站点。

访问地址：

```text
虚拟机内：http://localhost:8080/
宿主机：  http://192.168.111.148:8080/
环境检查：http://192.168.111.148:8080/health
```

复测：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://192.168.111.148:8080
```

## 测试账号

所有账号均为本地模拟账号，统一测试密码：

```text
CampusTrace!Demo2026
```

| 用户名 | 角色 | 激活状态 | 用途 |
| --- | --- | --- | --- |
| `alice` | 普通用户 | 已激活 | 帖子发布者 |
| `bob` | 普通用户 | 已激活 | 认领人 |
| `carol` | 普通用户 | 未激活 | 未激活访问测试 |
| `admin` | 管理员 | 已激活 | 举报和下架测试 |

这些账号不得用于真实系统。

## 当前路由

| 方法 | 路径 | 状态 |
| --- | --- | --- |
| GET | `/` | 已实现列表和筛选 |
| GET | `/posts/{id}` | 已实现详情 |
| GET/POST | `/login` | 已实现 |
| POST | `/logout` | 已实现 |
| GET | `/health` | 已实现环境检查 |
| GET/POST | `/register` | 已实现 |
| GET/POST | `/activate` | 已实现 |
| GET/POST | `/account/contact` | 已实现，仅本人可修改 |
| GET/POST | `/posts/new` | 成员 2 待实现 |
| GET/POST | `/posts/{id}/edit` | 成员 2 待实现 |
| POST | `/posts/{id}/resolve` | 成员 2 待实现 |
| POST | `/posts/{id}/image` | 已实现上传与替换，保留 R01/R05/R09 |
| GET | `/images/{id}` | 已实现原始图片读取，保留 R02/R04 |
| GET/POST | `/posts/{id}/claims` | 已实现认领表单、发布者申请列表与提交 |
| GET | `/claims/{id}` | 已实现认领详情 |
| POST | `/claims/{id}/approve` | 已实现批准与替换旧获批人 |
| POST | `/claims/{id}/reject` | 已实现拒绝 |
| POST | `/claims/{id}/revoke` | 已实现撤回 |
| GET | `/claims/{id}/contact` | 已实现 Baseline 模拟联系入口，保留 R06/R07 |
| POST | `/posts/{id}/reports` | 成员 2 待实现 |
| GET | `/admin/reports` | 成员 2 待实现 |
| POST | `/admin/posts/{id}/remove` | 成员 2 待实现 |

公共接口说明见 [docs/module-interface.md](docs/module-interface.md)。

成员 4 的整站测试步骤与证据表见 [认领模块 Baseline 操作手册](docs/modules/member-4-baseline-test.md)。

团队协作规则见 [docs/team-workflow.md](docs/team-workflow.md)，页面外观规范见 [docs/ui-style-guide.md](docs/ui-style-guide.md)。

## 安全边界

- 只运行于本地课程虚拟机。
- 只使用模拟账号、虚构联系方式和自制图片。
- 不对公网服务实施测试。
- 不提交 `app/config.local.php`、真实数据库密码或上传文件。
- PHP 7.3.4 已停止官方安全支持，项目结论仅适用于当前课程环境。

