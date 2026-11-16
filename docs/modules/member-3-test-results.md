# 成员 3 多图片测试结果 / Member 3 Multi-image Test Results

## 结果摘要 / Result Summary

- 测试日期 / Test date: 2026-11-16
- 分支 / Branch: `feature/images`
- 环境 / Environment: PHPStudy PHP 7.3.4, MySQL 3307, CampusTrace 8081
- 测试方式 / Test type: 端到端 HTTP、数据库和文件系统自动测试 / End-to-end HTTP, database and filesystem automation
- 结果 / Result: **24/24 PASS**
- 风险用例通过表示 Baseline 缺口已复现 / A PASS on a risk case means the Baseline gap was reproduced

## 测试命令 / Test Command

```powershell
& 'D:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe' `
  .\tests\security\test_images_multi.php `
  http://127.0.0.1:8081
```

## 测试截图 / Test Evidence

![多图片测试结果 / Multi-image test results](./evidence/member-3-multi-image-test-2026-11-16.png)

## 中英对照结果 / Bilingual Results

| # | 自动化输出 / Automated output | 中文说明 / Chinese description | 结果 / Result |
| --- | --- | --- | --- |
| 1 | Anonymous multi-image upload is redirected to login | 匿名用户进行多图片上传时跳转到登录页 | PASS |
| 2 | An all-invalid batch changes neither gallery nor cover | 整批文件均不合格时，不改变相册和封面 | PASS |
| 3 | IMG-01 mixed batch uses partial success without CSRF | 合法与错误文件混合上传时部分成功，并保留 R08 缺口 | PASS |
| 4 | IMG-01 first successful image becomes the default cover | 首次上传的第一张成功图片成为默认封面 | PASS |
| 5 | IMG-01 every successful image has its own stored file | 每张成功图片均具有独立存储文件 | PASS |
| 6 | IMG-01 guest detail renders all gallery images | 访客详情页展示全部相册图片 | PASS |
| 7 | IMG-01 owner detail renders the multi-file upload input | 发布者详情页展示多文件上传控件 | PASS |
| 8 | Image UI does not expose Baseline or risk identifiers | 图片界面不向用户暴露 Baseline 或风险编号 | PASS |
| 9 | Post list renders the current cover image | 帖子列表展示当前封面图片 | PASS |
| 10 | R02 reproduced: image endpoint returns the original bytes | 图片接口返回原始字节，R02 缺口已复现 | PASS，R02 |
| 11 | IMG-02 appending does not change the existing cover | 追加图片不改变已有封面 | PASS |
| 12 | R01 reproduced: renamed text is accepted as an image | 文本改名为图片后被接收，R01 缺口已复现 | PASS，R01 |
| 13 | R01 reproduced: fake image bytes are publicly served | 伪装图片字节被公开返回，R01 缺口已复现 | PASS，R01 |
| 14 | R05 reproduced: another active user can append an image | 其他激活用户可追加图片，R05 缺口已复现 | PASS，R05 |
| 15 | Only the post owner can delete an image | 只有帖子发布者可以删除图片 | PASS |
| 16 | Owner delete request redirects | 发布者删除请求完成并跳转 | PASS |
| 17 | Deleting a non-cover image handles file cleanup failure | 删除非封面图片时处理物理文件清理失败 | PASS |
| 18 | Deleting a non-cover image removes its metadata and old URL | 删除非封面图片后移除元数据并使旧地址失效 | PASS |
| 19 | Deleting a non-cover image keeps the current cover | 删除非封面图片后保持当前封面 | PASS |
| 20 | Deleting the cover promotes the next image and handles cleanup failure | 删除封面后下一张自动接替，并处理清理失败 | PASS |
| 21 | Removed post detail is no longer public | 下架帖子详情不再公开 | PASS |
| 22 | R04 reproduced: removed post images remain directly readable | 下架帖子图片仍可直接读取，R04 缺口已复现 | PASS，R04 |
| 23 | Deleting the final image clears the gallery and cover | 删除最后一张图片后清空相册和封面 | PASS |
| 24 | FILE-01 R09 reproduced: failed file move leaves metadata and cover behind | 文件移动失败后残留元数据或错误封面，R09 缺口已复现 | PASS，R09 |

## 覆盖范围 / Coverage

本次测试覆盖 / This test run covers:

- 多文件上传与部分成功 / Multi-file upload and partial success
- 封面和相册展示 / Cover and gallery rendering
- 图片追加与删除 / Image append and deletion
- 数据库、图片文件和 HTTP 响应一致性 / Database, stored file and HTTP response consistency
- R01、R02、R04、R05、R08、R09 Baseline 行为 / Baseline behavior for R01, R02, R04, R05, R08 and R09
- 删除后的旧地址失效 / Old URL invalidation after deletion
- 下架帖子图片仍可访问 / Removed-post image access remains available

## 手工测试图片 / Manual Test Images

自动化脚本会在 `tests/.tmp/` 中生成测试图片并在结束后清理，因此手工测试应另外准备自制图片。

建议保存到：

```text
C:\test\CampusTrace\tests\.tmp\manual-images
```

可以使用：

- 使用 Windows 画图新建一张纯色图，分别另存为 `manual-valid.png` 和 `manual-valid.jpg`。
- 使用记事本写入普通文本，保存为 `manual-fake.png`，用于复现 R01。
- 使用记事本创建 `manual-invalid.txt`，用于验证扩展名拒绝。
- 使用自己制作的简单图标或图形，避免真实照片、联系方式或敏感元数据。

该目录已被 Git 忽略，手工测试图片不会提交到仓库。
