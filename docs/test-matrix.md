# CampusTrace Baseline 测试矩阵

当前状态：公共骨架已部署并通过宿主机冒烟测试。  
测试时间：2026-11-08  
测试地址：`http://192.168.111.148:8080`

| 编号 | 场景 | 预期 | 当前状态 |
| --- | --- | --- | --- |
| ENV-01 | 打开 `/health` | PHP、扩展、数据库和存储检查通过 | 通过 |
| ENV-02 | 检查数据库字符集 | 使用 `utf8mb4` | 通过 |
| AUTH-01 | 使用 `alice` 登录 | 登录成功并回到列表 | 通过 |
| AUTH-02 | 使用 `carol` 登录 | 未激活账号被拒绝，返回 401 | 通过 |
| AUTH-03 | 登录后退出 | 回到登录页且 Session 清除 | 通过 |
| AUTH-04 | 未登录访问待实现写路由 | 当前由后续模块实现时补充权限检查 | 待模块实现 |
| POST-01 | 查看公开列表 | 只显示 `open` 和 `resolved` | 通过 |
| POST-02 | 按关键词、类型和状态筛选 | 返回匹配数据并排除不匹配数据 | 通过 |
| POST-03 | 查看不存在或已下架帖子 | 返回 404 | 通过 |
| MODULE-01 | 打开待实现模块路由 | 返回统一的 501 页面 | 通过 |
| WEB-01 | 宿主机访问 Nginx 8080 | 首页、详情和静态 CSS 正常返回 | 通过 |

baseline 完整阶段继续补充 baseline.md 中的 14 组安全用例。

复测命令：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://192.168.111.148:8080
```

