# CampusTrace Baseline 测试矩阵

当前状态：公共骨架已部署并通过宿主机冒烟测试；图片模块脚本已实现，待课程 VM 实测。
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
| AUTH-05 | 使用有效数据注册 | 用户创建成功且保持未激活 | 通过 |
| AUTH-06 | 重复用户名注册 | 拒绝并保持原数据 | 通过 |
| AUTH-07 | 密码不足 10 位 | 拒绝 | 通过 |
| AUTH-08 | 注册缺少 CSRF | 返回 400 | 通过 |
| AUTH-09 | 有效邀请码激活 | 用户和邀请码状态同时更新 | 通过 |
| AUTH-10 | 重复使用邀请码 | 返回 422 | 通过 |
| AUTH-11 | 使用过期邀请码 | 返回 422 | 通过 |
| AUTH-12 | 未登录访问联系方式页面 | 跳转登录页 | 通过 |
| AUTH-13 | 修改本人联系方式 | 只更新当前用户 | 通过 |
| AUTH-14 | 联系方式更新缺少 CSRF | 返回 400，数据不变 | 通过 |
| AUTH-15 | 激活请求缺少 CSRF | 返回 400，账号保持未激活 | 通过 |
| POST-01 | 查看公开列表 | 只显示 `open` 和 `resolved` | 通过 |
| POST-02 | 按关键词、类型和状态筛选 | 返回匹配数据并排除不匹配数据 | 通过 |
| POST-03 | 查看不存在或已下架帖子 | 返回 404 | 通过 |
| MODULE-01 | 打开待实现模块路由 | 返回统一的 501 页面 | 通过 |
| WEB-01 | 宿主机访问 Nginx 8080 | 首页、详情和静态 CSS 正常返回 | 通过 |
| IMG-01 | 上传正常 PNG/JPEG 并显示 | 元数据、当前引用、文件和预览一致 | 待 VM 实测 |
| IMG-02 | 普通文本改名为 `.png` | Baseline 接收，记录 R01 | 待 VM 实测 |
| IMG-03 | 替换后访问旧图片 ID | Baseline 仍返回 200，记录 R04 | 待 VM 实测 |
| IMG-04 | 比较上传与读取文件字节 | 原始字节一致，记录 R02 | 待 VM 实测 |
| STATE-01 | 已解决或下架帖子访问旧图片 ID | Baseline 仍返回 200，记录 R04 | 待 VM 实测 |
| IMG-05 | 其他激活用户替换帖子图片 | Baseline 错误放行，记录 R05 | 待 VM 实测 |
| FILE-01 | 模拟图片文件移动失败 | 数据库引用改变但文件缺失，记录 R09 | 待 VM 实测 |

baseline 完整阶段继续补充 baseline.md 中的 14 组安全用例。

账号模块自动化测试：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\account_flow.php
```

测试会临时创建用户和邀请码，结束后自动清理。
2026-11-10 实测 22 项通过。

复测命令：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://192.168.111.148:8080
```

图片模块在 VM 内复测：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  .\tests\security\test_images.php `
  http://127.0.0.1:8080
```

