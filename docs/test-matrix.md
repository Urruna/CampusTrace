# CampusTrace Baseline 测试矩阵

当前状态：四模块代码正在整站集成验证。  
测试地址：`http://192.168.111.148:8080`

## 基础与账号

| 编号 | 场景 | 预期 | 当前状态 |
| --- | --- | --- | --- |
| ENV-01 | 打开 `/health` | PHP、扩展、数据库和存储检查通过 | 通过 |
| ENV-02 | 检查数据库字符集 | 使用 `utf8mb4` | 通过 |
| WEB-01 | 宿主机访问 Nginx 8080 | 首页、详情和静态 CSS 正常返回 | 通过 |
| AUTH-01 | 使用 `alice` 登录 | 登录成功并回到列表 | 通过 |
| AUTH-02 | 使用 `carol` 登录 | 未激活账号被拒绝，返回 401 | 通过 |
| AUTH-03 | 登录后退出 | 回到登录页且 Session 清除 | 通过 |
| AUTH-04 | 未登录访问联系方式页面 | 跳转登录页 | 通过 |
| AUTH-05 | 使用有效数据注册 | 用户创建成功且保持未激活 | 通过 |
| AUTH-06 | 重复用户名注册 | 拒绝并保持原数据 | 通过 |
| AUTH-07 | 密码不足 10 位 | 拒绝 | 通过 |
| AUTH-08 | 注册缺少 CSRF | 返回 400 | 通过 |
| AUTH-09 | 有效邀请码激活 | 用户和邀请码状态同时更新 | 通过 |
| AUTH-10 | 重复使用邀请码 | 返回 422 | 通过 |
| AUTH-11 | 使用过期邀请码 | 返回 422 | 通过 |
| AUTH-12 | 修改本人联系方式 | 只更新当前用户 | 通过 |
| AUTH-13 | 联系方式更新缺少 CSRF | 返回 400，数据不变 | 通过 |
| AUTH-14 | 激活请求缺少 CSRF | 返回 400，账号保持未激活 | 通过 |

## 帖子与举报

| 编号 | 场景 | 预期 | 当前状态 |
| --- | --- | --- | --- |
| M2-POST-01 | 发布帖子、关键词/类型/状态筛选、详情 | 正常完成且公开字段可见 | 待集成实测 |
| M2-POST-02 | 发布者编辑本人帖子 | 保存成功并产生审计 | 待集成实测 |
| M2-POST-03 | 用户乙编辑用户甲帖子 | Baseline 放行并修改成功，记录 R05 | 待集成实测 |
| M2-STATE-01 | 用户乙结帖，随后发布者结帖 | 前者 403，后者变为 `resolved` | 待集成实测 |
| M2-SEC-01 | 不提交 CSRF 发布含无害 HTML 标记的帖子 | 接受请求且标记原样输出，记录 R08 | 待集成实测 |
| M2-REPORT-01 | 同一用户重复提交相同举报 | Baseline 两次均接受，记录 R10 | 待集成实测 |
| M2-ADMIN-01 | 普通用户访问举报列表并下架帖子 | Baseline 放行，记录 R10 | 待集成实测 |
| M2-STATE-02 | 下架后打开详情和公开搜索 | 详情 404，列表不出现 | 待集成实测 |

## 图片

| 编号 | 场景 | 预期 | 当前状态 |
| --- | --- | --- | --- |
| IMG-01 | 上传正常 PNG/JPEG 并显示 | 元数据、当前引用、文件和预览一致 | 待集成实测 |
| IMG-02 | 普通文本改名为 `.png` | Baseline 接收，记录 R01 | 待集成实测 |
| IMG-03 | 替换后访问旧图片 ID | Baseline 仍返回 200，记录 R04 | 待集成实测 |
| IMG-04 | 比较上传与读取文件字节 | 原始字节一致，记录 R02 | 待集成实测 |
| IMG-05 | 其他激活用户替换帖子图片 | Baseline 错误放行，记录 R05 | 待集成实测 |
| STATE-IMG-01 | 已解决或下架帖子访问旧图片 ID | Baseline 仍返回 200，记录 R04 | 待集成实测 |
| FILE-01 | 模拟图片文件移动失败 | 数据库引用改变但文件缺失，记录 R09 | 待集成实测 |

## 认领与联系方式

| 编号 | 场景 | 预期 | 当前状态 |
| --- | --- | --- | --- |
| CLAIM-01 | 申请、审批、获批人读取模拟联系方式 | 正常完成 | 待集成实测 |
| CLAIM-02 / R06 | 未获授权用户直接读取联系方式 | Baseline 返回 200 并显示模拟联系方式 | 待集成实测 |
| STATE-CLAIM-01 / R07 | 已解决或下架后重新请求旧联系地址 | Baseline 仍返回联系方式 | 待集成实测 |

## 自动测试命令

账号模块：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\account_flow.php
```

帖子模块：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\test_posts.php `
  http://127.0.0.1:8080
```

图片模块：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\test_images.php `
  http://127.0.0.1:8080
```

认领模块：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\test_claims.php `
  http://127.0.0.1:8080
```

整站冒烟测试：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass `
  -File .\tests\smoke\run-from-host.ps1 `
  -BaseUrl http://192.168.111.148:8080
```
