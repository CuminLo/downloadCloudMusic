### 简介

批量下载音乐(网易云音乐，QQ音乐), 下载如果失败将自动使用其他渠道下载

![img](https://ww1.sinaimg.cn/large/007i4MEmgy1g0795dodizj30wa0d2489.jpg)

### 使用
需要 `ffmpeg`

命令执行：

```php
php download.php --url [url] --output [download_path]
```

可以同时指定多个歌单或单曲：

```php
php download.php --url https://y.qq.com/n/yqq/song/003JWNKf2kBHa1.html --url https://y.qq.com/n/yqq/song/004f5vwq0hsLu1.html
```

选项说明：
- url
    - 必填
    - 网易云音乐歌单和单曲
        - 歌单地址像这样 `https://music.163.com/#/playlist?id=2538984182`
        - 单曲地址像这样 `https://music.163.com/#/song?id=120326`
    - QQ音乐歌单和单曲
        - 歌单地址像这样的 `https://y.qq.com/n/yqq/playlist/1372169940.html`
        - 单曲地址像这样的 `https://y.qq.com/n/yqq/song/003JWNKf2kBHa1.html`

- output
> 选填 默认是当前项目目录/Music

### 项目说明

> 自己需要批量下载，用了几个工具遇到下载失败处理方式不理想，于是搜集了一些API临时写一个小工具。

> 为了添加元数据，不得已还是使用了 `ffmpeg` 所以还是得要用 `composer`，本来就是一个小工具不想用添加太多的文件...

> 当前使用的多进程下载，但是对信号处理还不太了解，完善中。

### 开发计划

- [x] 网易云音乐批量从歌单下载
- [x] 网易云音乐单歌曲下载
- [x] 下载遇失败自动使用其他方式下载
- [x] QQ音乐批量下载
- [x] QQ音乐单歌曲下载
- [ ] 下载失败使用其他API(完善中，目前支持如果网易下载失败将使用QQ渠道)

### 类似工具

- [NetEaseCloudMusic-nonmembership-list-download](https://github.com/CcphAmy/NetEaseCloudMusic-nonmembership-list-download) - Python
- [MKOnlineMusicPlayer](https://github.com/mengkunsoft/MKOnlineMusicPlayer) - 已停止维护
- [NeteaseCloudMusicApi](https://github.com/metowolf/NeteaseCloudMusicApi) - 网易部分API
- [bzqll](https://www.bzqll.com/2019/01/262.html) - 大佬提供的API