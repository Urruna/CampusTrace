# 成员 3 手工测试图片

本目录提供 CampusTrace 多图片功能和图片风险的手工测试素材。

所有图片均为课程模拟失物和自制测试文件，不包含人物面部、真实联系方式或检测到的 GPS 隐私字段。只能在本地课程环境使用。

## 文件说明

| 文件 | 用途 |
| --- | --- |
| `blue_umbrella-01-outer.jpg` | 浅蓝色印花折叠伞外伞面，建议作为封面 |
| `blue_umbrella-02-inside.jpg` | 同一把伞的黑色内层和伞骨 |
| `blue_umbrella-03-handle.jpg` | 同一把伞的按钮手柄和挂绳 |
| `blue-phone-holder.jpg` | 单独帖子使用的蓝色卡通手机支架 |
| `white-dog-pouch.jpg` | 单独帖子使用的白色卡通拉链收纳包 |
| `manual-fake.png` | 普通文本伪装为 PNG，用于 R01 |
| `manual-invalid.txt` | 普通文本和错误扩展名，用于部分成功测试 |
| `manual-appended-payload.jpg` | 正常 JPEG 尾部追加无害标记，用于 R02 扩展测试 |
| `manual-exif-metadata.jpg` | 含无害 EXIF 描述标记的 JPEG，用于 R02 元数据测试 |
| `make-exif-test.py` | 使用 Pillow 从第一张伞图重新生成 EXIF 测试图片 |
| `inspect-exif.py` | 检查下载后的 JPEG 是否仍保留 EXIF 标记 |

## 运行要求

- CampusTrace 已启动，例如 `http://127.0.0.1:8081`。
- 使用模拟账号登录。
- Python 环境需要 Pillow：`python -c "import PIL; print(PIL.__version__)"`。

## MAN-IMG-01：一帖多图

1. 使用 `alice` 登录。
2. 新建帖子“丢失一把浅蓝色印花折叠伞”。
3. 一次上传：

```text
blue_umbrella-01-outer.jpg
blue_umbrella-02-inside.jpg
blue_umbrella-03-handle.jpg
```

4. 检查三张图片全部显示。
5. 检查第一张标记为“封面”。
6. 检查首页帖子卡片使用第一张作为封面。

## MAN-IMG-02：追加图片

1. 在上述帖子中继续上传一张正常图片。
2. 确认相册数量增加。
3. 确认已有封面没有自动改变。

## MAN-IMG-03：删除和封面回退

1. 删除一张非封面图片，确认封面不变。
2. 删除封面，确认剩余第一张成为新封面。
3. 删除最后一张，确认页面显示“该帖子暂未上传图片”。

## MAN-R01：伪装图片

1. 新建单独的测试帖子。
2. 上传 `manual-fake.png`。
3. Baseline 预期错误接收该文件。
4. 数据库会存在图片记录，但无法显示正常图片。
5. 这是 R01 成功复现，不是功能失败。

## MAN-MIXED：部分成功

一次选择：

```text
blue-phone-holder.jpg
manual-invalid.txt
```

预期：

- JPEG 保存成功。
- TXT 被拒绝。
- 页面出现部分文件未保存提示。
- 数据库只新增 JPEG 记录。

## MAN-R02-PAYLOAD：尾部夹带内容

`manual-appended-payload.jpg` 是在正常 JPEG 尾部加入以下无害文本：

```text
SIMULATED_PAYLOAD_ONLY
```

测试步骤：

1. 新建单独测试帖子并上传该图片。
2. 从页面打开 `/images/{id}` 并保存下载文件。
3. 检查下载文件中仍存在 `SIMULATED_PAYLOAD_ONLY`。

PowerShell 检查：

```powershell
Select-String .\downloaded.jpg `
  -Pattern 'SIMULATED_PAYLOAD_ONLY' `
  -SimpleMatch
```

Baseline 预期：标记仍存在，证明原始文件被原样返回，即 R02 已复现。

Hardened 预期：重新编码后标记消失。

## MAN-R02-EXIF：元数据未清理

`manual-exif-metadata.jpg` 包含以下无害 EXIF 字段：

```text
Description: SIMULATED_EXIF_METADATA_ONLY
Software: CampusTrace R02 Test
```

测试步骤：

1. 新建单独测试帖子并上传该图片。
2. 从 `/images/{id}` 保存下载文件。
3. 执行：

```powershell
python .\tests\fixtures\images\manual\inspect-exif.py .\downloaded.jpg
```

Baseline 预期：

```text
Description: SIMULATED_EXIF_METADATA_ONLY
Software: CampusTrace R02 Test
```

Hardened 预期：

```text
Description: None
Software: None
```

## 重新生成 EXIF 测试图片

如果测试图片丢失或需要重新生成：

```powershell
cd .\tests\fixtures\images\manual
python .\make-exif-test.py
```

脚本会使用同目录的第一张伞图重新生成 `manual-exif-metadata.jpg`。

## 手工测试记录建议

记录：

- 测试日期和 Git 提交。
- 使用的用户名。
- 上传文件名。
- 页面显示结果。
- 数据库 `post_images` 记录。
- 首页封面和详情相册截图。
- R01、R02 测试的下载文件或 EXIF 输出。

不要把数据库密码、Cookie、真实联系方式或未脱敏请求头写入记录。
