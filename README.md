### 简介

批量下载音乐(网易云音乐，QQ音乐)

![img](https://ww1.sinaimg.cn/large/007i4MEmgy1g0795dodizj30wa0d2489.jpg)

### 使用

将项目 `clone` 下来，使用命令行执行：

```php
php download.php --url [url] --output [download_path]
```

选项说明：
- url
> 必填，支持歌单和单曲
> 网易云音乐
> QQ音乐

- output
> 选填 默认是当前项目目录/Music

### 项目说明

> 自己有需求。

> 使用了几个工具，不是很顺手，而开源项目大都是 `Python` 实现，我对 `Python` 不熟悉，所以就用 `PHP` 写了这个小工具，主要是为了实现自动切换到不同的资源去下载。

> 为了快速使用，所以没使用框架，也没有使用 `composer`，本身就是为了直接下载下来就能运行。

> 以后可能会考虑加上 `GUI`

> 当前使用的多进程下载。

### 开发计划

- [x] 网易云音乐批量从歌单下载
- [x] 网易云音乐单歌曲下载
- [ ] 下载遇失败自动使用其他方式下载
- [ ] QQ音乐批量下载
- [ ] QQ音乐单歌曲下载

### 类似工具

- [NetEaseCloudMusic-nonmembership-list-download](https://github.com/CcphAmy/NetEaseCloudMusic-nonmembership-list-download) - Python
- [MKOnlineMusicPlayer](https://github.com/mengkunsoft/MKOnlineMusicPlayer) - 已停止维护
- [NeteaseCloudMusicApi](https://github.com/metowolf/NeteaseCloudMusicApi) - 网易部分API