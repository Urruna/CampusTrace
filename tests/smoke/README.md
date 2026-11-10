# Smoke tests

公共骨架完成后先验证：

1. `/health` 所有检查通过。
2. `alice` 可以登录。
3. `carol` 无法登录。
4. 首页只显示开放和已解决帖子。
5. 关键词与类型筛选正常。
6. 登录后可以退出。
7. 待实现模块返回统一的 501 页面。

账号模块完整测试在虚拟机中运行：

```powershell
C:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe `
  C:\phpstudy_pro\WWW\CampusTrace\tests\security\account_flow.php
```

该脚本会创建一个临时用户和临时邀请码，测试结束后自动清理。

