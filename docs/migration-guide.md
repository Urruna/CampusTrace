# CampusTrace Baseline 测试人员迁移指南

本文档用于将 CampusTrace baseline 从当前课程虚拟机迁移到另一台 Windows 测试电脑，或在另一台电脑上重新建立可复现环境。

基线代码：`integration/baseline`
建议测试分支：`integration/baseline`
自动化验证基线：91 项通过
验证日期：2026-11-11

## 1. 重要说明

- 本项目只用于隔离的本地课程环境。
- Baseline 故意保留 R01—R10 风险，部分测试 PASS 表示风险已按预期复现，不代表问题已经修复。
- 测试只能使用模拟账号、虚构联系方式和自制图片。
- 不要将站点部署到公网，不要使用真实用户数据。
- 如果多人同时测试，每人必须使用自己的数据库和图片目录，不能共用一个正在修改的数据库。

## 2. 推荐测试环境

优先使用同一课程虚拟机镜像：

```text
Windows Server 2019
PHPStudy 8.1.1.3
PHP 7.3.4 NTS
Nginx 1.15.11
MySQL 5.7.26
```

必须确认：

- PHP 扩展：`mysqli`、`fileinfo`、`gd`、`curl`、`mbstring`、`openssl`、`session`。
- `imagecreatefrompng()` 可用。
- `imagecreatefromjpeg()` 可用。
- `file_uploads` 开启。
- PHP 与 MySQL 位于同一虚拟机。
- 8080 端口没有被其他程序占用。

不建议直接使用生产数据库或个人电脑上的正式 MySQL 实例。

## 3. 获取代码

从私有 GitHub 仓库获取代码：

```powershell
git clone https://github.com/Urruna/CampusTrace.git
cd CampusTrace
git checkout integration/baseline
git log -1 --oneline
```

迁移记录中应保存本次实际检出的提交哈希，避免不同成员测试不同版本。

`app/config.local.php` 不在 Git 中，这是有意的安全设计。克隆后必须自行创建。

## 4. 推荐目录

最容易复现的路径：

```text
C:\phpstudy_pro\WWW\CampusTrace
```

如果测试电脑使用相同路径，可以直接复用现有启停脚本。
如果路径不同，需要修改以下配置。

### 4.1 Nginx 配置

文件：

```text
deploy\nginx\campustrace-8080.conf
```

至少检查：

```text
listen 8080;
server_name localhost;
root <项目根目录>/public;
include <PHPStudy目录>/Extensions/Nginx1.15.11/conf/fastcgi_params;
```

站点根目录必须指向 `public/`，不能指向整个项目目录。

### 4.2 Windows 启停脚本

文件：

```text
deploy\windows\start-campustrace.ps1
deploy\windows\stop-campustrace.ps1
```

如果 PHPStudy 不在 `C:\phpstudy_pro`，需要修改：

- `$nginxRoot`
- `$nginxConfig`
- `$phpDir`
- `$phpCgi`
- `$phpIni`
- `$mysqlDir`
- `$mysqld`
- `$mysqlIni`
- `$runtimeDir`

如果 8080 被占用，需要同时修改 Nginx 配置和启停脚本中的端口说明。

## 5. 创建本地配置

复制：

```text
app\config.example.php
```

为：

```text
app\config.local.php
```

配置至少需要填写：

```text
app_url
db_host
db_port
db_name
db_user
db_password
db_charset
storage_dir
log_dir
```

建议：

```text
db_host = 127.0.0.1
db_port = 3306
db_name = campustrace_baseline
db_charset = utf8mb4
```

数据库密码只保存在当前测试电脑的 `config.local.php`，不要提交 Git。

## 6. 初始化数据库

### 6.1 创建数据库和应用账号

使用测试电脑的 phpMyAdmin 或 MySQL 管理工具，以数据库管理员身份执行占位示例：

```sql
CREATE DATABASE IF NOT EXISTS campustrace_baseline
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'campustrace_app'@'127.0.0.1'
    IDENTIFIED BY '<替换为本地测试密码>';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON campustrace_baseline.*
    TO 'campustrace_app'@'127.0.0.1';

FLUSH PRIVILEGES;
```

如果该账号已经存在，不要重复创建；由测试电脑管理员确认账号密码和权限即可。

应用账号只需要 DML 权限。建库、建表和迁移应由数据库管理员完成。

### 6.2 导入表和数据

以数据库管理员身份按顺序导入：

```text
database\01_schema.sql
database\02_seed.sql
```

导入后确认：

- 七张表都存在。
- 表引擎为 InnoDB。
- 表字符集为 `utf8mb4`。
- 模拟账号和初始帖子已存在。

### 6.3 重置测试数据

仅在确认当前数据库属于 CampusTrace 测试库时执行重置。推荐顺序：

1. `database\99_reset.sql`
2. `database\01_schema.sql`
3. `database\02_seed.sql`

不要对共享数据库或他人正在测试的数据库执行重置。

## 7. 配置存储目录

需要存在的目录：

```text
storage\baseline_images
storage\logs
storage\run
```

负责运行 PHP/Nginx 的 Windows 账号必须具有修改权限。测试人员应通过目录属性或受控 ACL 授予所需账号权限，不要直接对 `Everyone` 开放完全控制。

上传图片必须保存在 `public/` 之外，不能通过 Nginx 静态目录直接暴露。

## 8. 启动服务

### 8.1 检查端口

在虚拟机 PowerShell 中运行：

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8080 -ErrorAction SilentlyContinue
Get-NetTCPConnection -State Listen -LocalPort 9000 -ErrorAction SilentlyContinue
```

如果 8080 已被其他程序占用，应先停止该程序或修改 CampusTrace 端口配置。

### 8.2 启动

在项目根目录运行：

```powershell
powershell -ExecutionPolicy Bypass `
  -File .\deploy\windows\start-campustrace.ps1
```

脚本会检查并启动：

- MySQL 3306
- PHP-CGI 9000
- CampusTrace Nginx 8080

### 8.3 停止

```powershell
powershell -ExecutionPolicy Bypass `
  -File .\deploy\windows\stop-campustrace.ps1
```

停止脚本会停止 CampusTrace 的 Nginx 和 PHP-CGI，但保留 MySQL 运行。

## 9. 访问地址

虚拟机内部：

```text
http://localhost:8080/
http://localhost:8080/health
```

宿主机访问：

```text
http://<测试虚拟机IP>:8080/
http://<测试虚拟机IP>:8080/health
```

在虚拟机内执行：

```powershell
ipconfig
```

获取实际 VMnet IP。不要使用当前开发者电脑固定的 `192.168.111.148`，除非测试电脑的虚拟机确实获得该地址。

如果宿主机无法访问，检查：

1. 虚拟机网络是否为 NAT 或 Host-only。
2. Nginx 是否监听 8080。
3. Windows 防火墙是否允许 VMnet 私有网段的 8080 入站。
4. 是否错误访问了 IIS 的 80 端口。

## 10. 健康检查

打开：

```text
http://localhost:8080/health
```

必须确认：

- PHP 版本符合测试环境。
- mysqli、fileinfo、GD、PNG 和 JPEG 解码通过。
- 数据库连接成功。
- 数据库字符集为 `utf8mb4`。
- 图片存储目录可写。

健康检查存在失败项时，不要继续记录安全测试结果。

## 11. 登录测试

测试密码统一为：

```text
CampusTrace!Demo2026
```

| 用户名 | 状态 | 用途 |
| --- | --- | --- |
| `alice` | 已激活 | 帖子发布者 |
| `bob` | 已激活 | 认领人和第二名用户 |
| `carol` | 未激活 | 激活和未激活测试 |
| `admin` | 已激活 | 举报与管理员测试 |

## 12. 执行自动化测试

以下命令必须在**运行 Web 服务和数据库的虚拟机内**执行，并使用项目根目录。

### 账号模块

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\account_flow.php
```

### 认领模块

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\test_claims.php `
  http://127.0.0.1:8080
```

### 图片模块

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\test_images.php `
  http://127.0.0.1:8080
```

### 帖子模块

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\test_posts.php `
  http://127.0.0.1:8080
```

如果 PHPStudy 路径不同，替换 PHP 可执行文件路径。

账号和认领测试会创建临时用户、邀请码和帖子，结束时自动清理；图片测试会生成测试图片并清理临时记录。仍应在独立测试数据库中运行，不要与他人共用正在变化的数据库。

### 宿主机冒烟测试

在宿主机 PowerShell 中运行：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://<测试虚拟机IP>:8080
```

`run-from-host.ps1` 默认使用开发机 IP，迁移后必须显式传入 `-BaseUrl`。

## 13. 预期测试结果

Baseline 正常参考结果：

```text
账号模块：22/22 通过
认领模块：23/23 通过
图片模块：15/15 通过
帖子模块：15/15 通过
整站冒烟：16/16 通过
PHP 语法检查：42 个 PHP 文件，0 错误
```

部分模块测试通过表示 Baseline 风险被成功复现，例如：

- R01：伪装图片被接收。
- R02：原始图片字节被返回。
- R04：旧图片仍可读取。
- R05：他人可以编辑帖子或替换图片。
- R06/R07：未授权或旧联系入口仍返回模拟联系方式。
- R08：部分写请求缺少 CSRF，部分文本原样输出。
- R09：图片替换失败时数据库引用已经改变。
- R10：重复举报或普通用户管理员操作被放行。

这是预期行为，不应在 baseline 中提前修复。

## 14. 测试记录建议

每次迁移至少记录：

```text
Git 提交哈希
Windows 版本
PHPStudy、PHP、Nginx、MySQL 版本
PHP 扩展检查结果
数据库名称和配置是否成功
项目绝对路径
访问 URL
测试执行日期
每项测试总数、通过数、失败数
失败测试的请求、响应、数据库状态和日志位置
```

证据中不要保存：

- 数据库密码。
- 完整 Session Cookie。
- 完整的真实联系方式。
- 原始未脱敏请求头。

## 15. 常见故障

| 现象 | 优先检查 |
| --- | --- |
| 502 Bad Gateway | PHP-CGI 是否监听 9000 |
| 404 且没有 8080 | 是否误访问 IIS 的 80 端口 |
| 数据库连接失败 | `config.local.php`、MySQL 服务、账号权限 |
| 表不存在 | 是否导入 `01_schema.sql` |
| 初始账号不存在 | 是否导入 `02_seed.sql` |
| 图片上传返回 500 | `storage\baseline_images` 权限 |
| 宿主机无法访问 8080 | 虚拟机 IP、防火墙、Nginx 监听 |
| CSS 无样式 | 站点根目录是否指向 `public/` |
| 测试脚本请求错误 IP | 修改命令行传入的 BaseUrl |

## 16. 完成迁移的标准

- Git 分支和提交哈希已记录。
- `/health` 全部通过。
- 模拟账号可以登录。
- 四项模块测试均达到预期总数。
- 宿主机或虚拟机冒烟测试通过。
- 测试数据可重置。
- 测试输出和证据已保存到测试电脑。
- `app/config.local.php`、数据库密码和上传文件没有提交 Git。
