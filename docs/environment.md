# CampusTrace 实验环境记录

检查日期：2026-11-08  
检查方式：VMware 虚拟机配置、虚拟机内进程、配置文件、PHP CLI/CGI、MySQL 只读查询、宿主机网络访问  
虚拟机目录：`D:\ctf\windows\windowsserver2019`

## 1. 虚拟机与网络

| 项目 | 实测结果 |
| --- | --- |
| 虚拟机名称 | Windows Server 2019 |
| 客户机计算机名 | `WIN-3FD3H8929OJ` |
| 操作系统 | Microsoft Windows Server 2019 Datacenter |
| 系统版本 | 10.0.17763 |
| 系统架构 | 64 位 |
| 网络模式 | VMware NAT |
| 虚拟机 IP | `192.168.111.148` |
| 子网掩码 | `255.255.255.0` |
| 默认网关 | `192.168.111.2` |
| DNS | `192.168.111.2` |
| MAC 地址 | `00:0c:29:23:f2:9f` |
| 宿主机 VMnet8 地址 | `192.168.111.1` |
| VMware Tools | 未安装或未运行 |
| 共享目录配置 | 宿主机 `D:\ctf` 映射为虚拟机共享名 `share`，读写开启；VMware Tools 未运行，实际可用性未验证 |

宿主机网络测试结果：

- ICMP：虚拟机可达。
- TCP 80：可达，但当前由 IIS 响应，不是 PHPStudy。
- TCP 3306：可达，当前 MySQL 监听 `0.0.0.0:3306`。
- TCP 3389、5985、5986：不可达。

## 2. PHPStudy 与 Web 服务器

| 项目 | 实测结果 |
| --- | --- |
| PHPStudy 版本 | 8.1.1.3 |
| PHPStudy 服务 | `phpStudySrv`，运行中 |
| 当前选择的 Web 服务器 | Nginx 1.15.11 |
| 已安装但未选择的 Web 服务器 | Apache 2.4.39 |
| Nginx 程序目录 | `C:\phpstudy_pro\Extensions\Nginx1.15.11` |
| Nginx 监听配置 | 80 |
| Nginx 站点根目录 | `C:/phpstudy_pro/WWW` |
| Nginx 默认站点名 | `localhost` |
| IIS 服务 `W3SVC` | 运行中，自动启动 |
| 80 端口占用 | Windows `System`，PID 4，由 IIS/HTTP.sys 占用 |
| CampusTrace Nginx 实例 | 已启动，独立使用 8080 端口 |
| CampusTrace PHP-CGI | 已启动，监听 `127.0.0.1:9000` |

当前状态：

- PHPStudy 默认 Nginx 尝试监听 `0.0.0.0:80` 时失败，错误为 `bind() failed (10013: access permissions)`。
- 从宿主机访问 `http://192.168.111.148/` 返回 IIS 的 404，不是 CampusTrace 或 PHPStudy 站点。
- CampusTrace 使用独立 Nginx 配置监听 8080，不修改 IIS 和 PHPStudy 默认 80 站点。
- 从宿主机访问 `http://192.168.111.148:8080/` 已成功，返回 CampusTrace 页面。

## 3. PHP

| 项目 | 实测结果 |
| --- | --- |
| PHP 版本 | 7.3.4 NTS |
| 安装目录 | `C:\phpstudy_pro\Extensions\php\php7.3.4nts` |
| php.ini | `C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.ini` |
| CLI SAPI | `cli` |
| CGI SAPI | `cgi-fcgi` |
| PHP CLI 构建时间 | 2019-04-02 21:57:18 |
| 时区 | Asia/Shanghai |

PHP 扩展和图片能力：

| 项目 | 结果 |
| --- | --- |
| `mysqli` | 已启用 |
| `pdo_mysql` | 已启用 |
| `fileinfo` | 已启用 |
| `gd` | 已启用 |
| `mbstring` | 已启用 |
| `openssl` | 已启用 |
| `session` | 已启用 |
| `imagecreatefrompng()` | 可用 |
| `imagecreatefromjpeg()` | 可用 |

PHP 上传与错误配置：

| 配置项 | 当前值 |
| --- | --- |
| `file_uploads` | On |
| `upload_max_filesize` | 100M |
| `post_max_size` | 100M |
| `max_file_uploads` | 20 |
| `display_errors` | On |
| `log_errors` | On |
| `error_reporting` | 32759，对应 `E_ALL & ~E_NOTICE` |

注意：

- `display_errors=On` 只适合本地开发。提交和演示前应改为 Off，同时保留日志。
- PHP 7.3.4 已停止官方安全支持，本项目只能定位为本地课程环境，不能宣称服务器平台处于受支持状态。

## 4. MySQL

| 项目 | 实测结果 |
| --- | --- |
| MySQL 版本 | 5.7.26 |
| 安装目录 | `C:\phpstudy_pro\Extensions\MySQL5.7.26` |
| 数据目录 | `C:\phpstudy_pro\Extensions\MySQL5.7.26\data` |
| 端口 | 3306 |
| 当前状态 | 已使用普通参数启动 |
| 监听地址 | `0.0.0.0:3306` |
| 服务端字符集 | `utf8` |
| 服务端排序规则 | `utf8_unicode_ci` |
| 默认存储引擎 | `MyISAM` |
| SQL mode | `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` |
| 现有课程数据库 | `software_security` |
| CampusTrace 数据库 | `campustrace_baseline` 已创建 |
| CampusTrace 应用账号 | `campustrace_app` 已创建，仅授予目标库的 DML 权限 |

CampusTrace 应使用数据库级设置覆盖当前默认值：

```sql
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
ENGINE=InnoDB
```

MySQL 当前监听 `0.0.0.0:3306`，宿主机也能访问 3306。既然 PHP 和 MySQL 在同一虚拟机，建议后续将 MySQL 限制为本机访问，只向宿主机保留 HTTP 端口。

## 5. CampusTrace 部署状态

公共骨架已经部署到虚拟机，数据库与种子数据已经创建。

计划部署结构：

| 项目 | 计划值 |
| --- | --- |
| 项目根目录 | `C:\phpstudy_pro\WWW\CampusTrace`，已部署 |
| Apache/Nginx 站点根目录 | `C:\phpstudy_pro\WWW\CampusTrace\public` |
| 上传存储目录 | `C:\phpstudy_pro\WWW\CampusTrace\storage\baseline_images` |
| 数据库 | `campustrace_baseline` |
| 应用数据库账号 | `campustrace_app` |
| 站内访问地址 | `http://localhost:8080/` |
| 宿主机访问地址 | `http://192.168.111.148:8080/` |

上传目录计划位于 `public/` 之外，因此应通过 PHP 受控读取，不应配置为静态文件目录。

## 6. 当前阻塞项与下一步

1. CampusTrace 已通过独立 8080 Nginx 配置运行，不依赖 IIS 的 80 端口。
2. CampusTrace 的站点根目录是项目自己的 `public/`，未修改全局 `C:/phpstudy_pro/WWW` 默认站点。
3. 七张表和模拟数据已导入，字符集与存储引擎均为 `utf8mb4_unicode_ci` 和 `InnoDB`。
4. 应用使用受限账号 `campustrace_app`，数据库管理账号不进入应用配置。
5. 应用入口通过 `ini_set()` 关闭 `display_errors`，保留服务端错误日志。
6. MySQL 仍监听 `0.0.0.0:3306`，后续应限制为仅本机访问。
7. 宿主机已通过 VMnet8 访问 CampusTrace 的 8080 端口。

## 7. 尚未验证的项目

- 尚未在另一台测试电脑上完成完整迁移和 91 项自动化复现。
- 不同 PHPStudy 安装路径下的启停脚本适配尚未实测。
- VMware 共享目录在客户机内的实际挂载和读写。

