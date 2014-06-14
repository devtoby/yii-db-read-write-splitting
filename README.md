# Yii数据库读写分离组件

这是一个供Yii Framework（以下统称Yii）使用的数据库读写分离组件，使用此组件只需通过简单的配置，即可使你的应用自动的实现读写分离。

## 开始之前

Yii读写分离包含两个组件：

1. `MDbConnection` 读写自动路由组件，使用这个名字更希望应用使用这个组件来替代Yii默认的CDbConnection
2. `MDbSlaveConnection` 从库（Readonly）组件

一般在PHP项目中常用的实现读写分离的方法有两种：

1. **自动分离** 由系统来决定读与写应该在哪个数据库上，对工程师透明，正常情况下既有代码无需修改即可使用，可以做到完全的读写分离，而且从库故障时可以自动切换在主库读取，推荐使用；
2. **手动分离** 由工程师来决定读与写应该在主还是从数据库上，工程师在开发时必须注意着主从的存在及其所带来的影响，但可能出现主库读取过度的问题（根据以往经验很多人会将大部分的读写都放在了主库上）。

下面将分别对两种实现读写分离的方法进行说明。

## 自动分离

自动分离无需指定是使用主库还是从库，并支持在`ActiveRecord`及`QueryBuilder`中的读写自动分离。支持多从库配置，每个请求只会落在随机的一个从库上，该从库为配置里面随机的一个，无需担心配置在前面的从库压力会过大。

对于大部分业务来说切换为自动读写分离后不会对既有逻辑产生影响，可以做到平滑的切换，但对于写完立即读可能会存在读不到数据的情况，如有这样的写法请修改，仍然建议使用前Review自己的代码。

### 安装步骤

安装组件之前当然需要先把组件down一份到你的应用的`components`目录里面去，关于怎么down这个就不做介绍了，下面从放置组件开始。

#### 放置组件

将down下来的组件包中的`MDbConnection.php`、`MDbSlaveConnection.php`复制到你的应用组件目录中，正常来说路径应该在`protected/components`目录中。

#### 修改Yii应用的配置文件

修改Yii应用的配置文件，默认的配置文件为`protected/main.php`，然后在其中找components 部分下的 db 组件的配置，例如：

```php
...
'db'=>array(
    'connectionString' => 'mysql:host=192.168.10.100;dbname=testDb',
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
),
...
```

将其修改为：

```php
...
'db'=>array(
    'class' => 'MDbConnection', // 指定使用读写分离Class
    'connectionString' => 'mysql:host=192.168.10.100;dbname=testDb', // 主库配置
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
    'timeout' => 3, // 增加数据库连接超时时间，默认3s
    'slaves' => array(
        array(
            'connectionString' => 'mysql:host=192.168.10.101;dbname=testDb',
            'username' => 'appuser',
            'password' => 'apppassword',
        ), // 从库 1
        array(
            'connectionString' => 'mysql:host=192.168.10.102;dbname=testDb',
            'username' => 'appuser',
            'password' => 'apppassword',
        ), // 从库 2
    ), // 从库配置
),
...
```

***注意：slaves中的配置必须是二维数组，可配置的值为CDbConnection中支持的全部值（属性）。***

### 配置继承

为简化应用配置的复杂度、以及结合大部分应用的使用场景，从库配置（部分配置）如果没有设置则会自动继承主库的配置，会继承的配置为：

* username
* password
* charset
* tablePrefix
* timeout
* emulatePrepare
* enableParamLogging

因此配置文件也可以简化为：

```php
...
'db'=>array(
    'class' => 'MDbConnection', // 指定使用读写分离Class
    'connectionString' => 'mysql:host=192.168.10.100;dbname=testDb', // 主库配置
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
    'slaves' => array(
        array(
            'connectionString' => 'mysql:host=192.168.10.101;dbname=testDb',
        ), // 从库 1
        array(
            'connectionString' => 'mysql:host=192.168.10.102;dbname=testDb',
        ), // 从库 2
    ), // 从库配置
),
...
```

### 关闭从库

如果需要临时关闭从库查询，或者没有从库只需注释掉slaves部分的配置即可。

### 注意

* 在所有从库无法连接时，读操作会在主库上进行，反之则不会；
* 进行写操作（在主库）后，立即读数据（在从库），可能会存在延时问题，在使用时请避免此类写法。

## 手动分离

在自动分离中，数据库的读写对工程师是透明的，因此会在开发过程中出现未考虑主从延时的问题，导致一些潜在的漏洞。相比之下手动分离则提醒着工程师时刻注意着主从的存在。

### 安装步骤

同自动分离部分，你首先也需要down一份组件下来。

#### 放置组件

手动分离只依赖组件包中的`MDbSlaveConnection.php`，将其复制到你的应用组件目录中，正常来说路径应该在`protected/components`目录中。

#### 修改Yii应用的配置文件

修改Yii应用的配置文件，默认的配置文件为`protected/main.php`，然后在 components 中 db 的配置后增加从库组件的配置，例如：

```php
...
'dbRead'=>array(
    'connectionString' => 'mysql:host=192.168.10.101;dbname=testDb',
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
),
...
```

**手动分离中，从库目前不会继承主库的配置哦！**

#### 使用示例

在应用中进行读操作

```php
Yii::app()->dbRead->createCommand()
    ->select('id, username, email')
    ->from('{{user}}')
    ->where('id=:id', array(':id'=>$id))
    ->queryRow();
```

在应用中进行写操作

```php
Yii::app()->db->createCommand()
    ->insert('{{user}}', array(
        'username' => 'devtoby',
        'email' => 'quflylong@qq.com',
    ));
```

## 反馈问题

[快来提一个Issue吧。](https://github.com/devtoby/yii-db-read-write-splitting/issues/new)
