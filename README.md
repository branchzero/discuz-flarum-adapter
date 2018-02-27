# Discuz To Flarum Migrate Adapter

本项目参考了如下项目，对此表示感谢

 - https://github.com/MichaelBelgium/mybb_to_flarum

该项目的目的是为了将 Discuz 迁移到 Flarum 中

# 前言

 - **代码写的稀烂,只为了达到转换数据的目的**
 - **大数据量用户慎用这个工具**
 - **工具不接受任何issue,也没有精力去修复其中的一些问题,有问题直接提PR**
 - 不建议一点都没有代码基础的兄弟用这个工具，可能你转换的时候还需要自己动手改一部分代码才能正常跑起来。
 - 建议基于 FlarumChina 的版本来动手吧，原版有些地方中文化还是不太到位的
 - 执行migrate.php前保证discussions和flagrow_files表是空的（truncate无效，因为有外键）
 - 用命令行跑migrate.php，以避免超时问题，跑完记得删migrate.php和migrate_config.php文件避免留下安全隐患
 - 跑之前记得先把posts表的content字段改成mediumtext，否则转html之后可能存不下，然后报错
 - mysql 5.7记得给discussions的title字段加FULLTEXT索引，然后把posts表转成innodb(官方dev-master版最近已动，在release之前先手动改吧！)，要不然数据量上去搜索巨慢无比，虽然加了也是巨慢无比，这个最终的解决方案可能还是得上ElasticSearch

# Step 0（前期准备）

 - composer require branchzero/discuz-flarum-adapter
 - composer require flagrow/upload(以兼容 Discuz 附件迁移)
 - composer require jellybool/flarum-ext-slug(板块生成slug)

# Step 1 （迁移数据）

 - 挪migrate.php和migrate_config.php至站点根目录，配置migrate_config.php并执行

# Step 2（支持中文用户名和中文提及）

 - 加密算法兼容性修正（以后更新每次都要做）
    - 找到 vendor/flarum/core/src/Foundation/AbstractServer.php
    - 搜索 $app->register('Illuminate\Hashing\HashServiceProvider');
    - 替换成 $app->register('Branchzero\DiscuzFlarumAdapter\Hashing\HashServiceProvider');

执行以下命令
```
sed -i "s#a-z0-9_-#-_a-z0-9\\x7f-\\xff#" \
    vendor/flarum/core/src/Core/Validator/UserValidator.php

sed -i "s#a-z0-9_-#-_a-zA-Z0-9\\x7f-\\xff#" \
    vendor/flarum/flarum-ext-mentions/src/Listener/FormatPostMentions.php \
    vendor/flarum/flarum-ext-mentions/src/Listener/FormatUserMentions.php

sed -i "s#getIdForUsername(#getIdForUsername(rawurlencode(#; /getIdForUsername/s/'))/')))/" \
    vendor/flarum/flarum-ext-mentions/src/Listener/FormatUserMentions.php
```

支持中文搜索

找到 vendor/flarum/core/src/Core/Search/Discussion/Fulltext/MySqlFulltextDriver.php

修改 match 方法为

```
    public function match($string)
    {
        $discussionIds = Discussion::whereRaw("is_approved = 1")
            ->where('title', 'like', '%'.$string.'%')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->lists('id','start_post_id');
        $relevantPostIds = [];
        foreach ($discussionIds as $postId => $discussionId) {
            $relevantPostIds[$discussionId][] = $postId;
        }
        $discussionIds = Post::whereRaw("is_approved = 1")
            ->where('content', 'like', '%'.$string.'%')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->lists('discussion_id', 'id');
        foreach ($discussionIds as $postId => $discussionId) {
            $relevantPostIds[$discussionId][] = $postId;
        }
        return $relevantPostIds;
    }
```