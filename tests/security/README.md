# 成员 2 安全与业务回归测试

## 前置条件

- CampusTrace 已部署并可访问。
- 数据库已按 `database/01_schema.sql` 和 `database/02_seed.sql` 初始化。
- 测试账号密码为 README 中统一的模拟密码。
- 使用独立测试数据；脚本会新增帖子、举报和审计记录，并下架其中一个新建帖子。

## 运行

在虚拟机内的项目目录执行：

```powershell
& 'C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe' `
  .\tests\security\test_posts.php `
  http://localhost:8080
```

也可以从能够访问站点的机器执行：

```powershell
php .\tests\security\test_posts.php http://192.168.111.148:8080
```

## 预期结果

脚本共检查 15 个断言。正常流程应通过；以下 baseline 缺口也必须按预期成功复现：

- 用户乙可编辑用户甲的帖子（R05）。
- 无 CSRF 的发帖和举报请求被接受，描述中的无害 HTML 标记原样输出（R08）。
- 重复举报被接受，普通用户可访问举报审核并下架（R10）。

所有步骤结束后，已下架测试帖子不会出现在公开列表，详情返回 404。
