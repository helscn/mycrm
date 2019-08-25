# MyCRM系统帮助说明

## MyCRM简介

`MyCRM` 是一个定制化的客户关系管理系统，具有简单的客户信息管理功能，主要功能：
- 支持客户重要度评级
- 支持后台通过Python脚本自动检查与客户的最近联系时间
- 支持通过客户重要度评级及客户的最近联系时间及客户所在公司聚合显示待跟进客户列表
- 支持后台通过Python脚本自动检查客户邮件地址是否有效，检查速度5个地址/小时
- 支持批量导入、导出客户信息，可以方便的通过导出的客户信息进行邮件群发

`MyCrm` 前端用户界面使用了 `jQuery`、`EasyUI` 框架开发，本说明页面使用了 `Marked.js` 进行 `Markdown` 格式文件的本地 `HTML` 渲染显示。

`MyCrm` 后端数据库操作接口使用 `PHP` + `MariaDB` 开发，并包含两个 `Python` 脚本用于检查客户邮件地址有效性及个人邮箱中的邮件监控。

本项目由于没有包含用户权限控制及用户登陆认证，不适合放在公网主机空间使用。

------

## 使用说明

### 运行环境配置(仅列出开发环境)
 
- PHP7.0
- MariaDB10
- Python3.5

### 系统安装步骤

1. **下载本项目文件并放到服务器的Web访问目录中**
2. **将 ./script/database.sql 导入`MariaDB`或`MySql`数据库创建数据表**
3. **创建 ./mycrm/conn.php 及 ./script/config.json 配置文件，可参考 conn_sample.php 及 config_sample.json 进行设定**
4. **将./script/fetch_mail.py 和 ./script/verify_mail.py 脚台程序加入系统任务执行计划中，其中verify_mail.py需设为每小时执行一次**
5. **使用使用浏览器从Web服务器中访问本项目主界面 index.php ，在菜单栏 设置-客户跟进设置 中修改客户跟进及邮箱账号的设置**

### 项目结构

```dir
index.php                               // 项目的前端访问入口
readme.md                               // README帮助说明文件
[mycrm]                                 // 数据库操作接口目录
    ├── add_customer.php                // 增加客户记录的接口
    ├── conn.php                        // 连接数据库并返回conn数据库连接对象    
    ├── del_customer.php                // 通过ID删除客户记录的接口
    ├── data_summary.php                // 获取统计图表展示数据的接口
    ├── export_customers.php            // 导出客户记录为CSV格式，包括导出所有记录、地址有效记录、地址无效记录、待跟进客户记录的功能
    ├── functions.php                   // 公用的函数模块，包括CSV格式输出等功能
    ├── get_config.php                  // 获取数库中config表中配置项的接口
    ├── get_customer.php                // 获取筛选的json格式客户记录的接口，也可以下载筛选后的CSV格式记录数据
    ├── get_followup_customer.php       // 获取待跟进客户的json数据接口
    ├── get_messages.php                // 获取邮件消息的接口，也可以获取消息总数
    ├── update_config.php               // 更新系统设置的接口
    ├── update_customer.php             // 通过ID更新客户信息的接口
    └── upload_customers.php            // 通过CSV批量上传导入客户界面的接口
[easyui]                                // EasyUI框架目录
    ├── jquery.min.js                   // 依赖的jQuery框架文件
    ├── jquery.easyui.min.js            // 依赖的EasyUI基础框架文件
    ├── jquery.edatagrid.js             // 依赖的EasyUI DataGrid可编辑插件文件
    ├── style.css                       // 个人自定义Style样式表
    ├── [locale]                        // EasyUI本地语言包文件夹，不包括前端UI的语言包
    ├── [src]                           // EasyUI的源码文件夹，此文件夹可删除
    ├── [theme]                         // EasyUI的主题文件夹
    │       └── [icons]                 // EasyUi的UI界面图标放置文件夹
    └── [plugin]                        // EasyUI的插件文件夹
            └── [texteditor]            // EasyUi的TextEditor插件扩展文件夹
[script]                                // 后台运行的Python
    ├── config.json                     // 数据库及邮箱的搜索配置的Json文件，邮箱的IMAP设置在数据库中
    ├── fetch_mail.py                   // 获取个人邮箱中的邮件信息的脚本程序
    ├── fetch_mail.log                  // fetch_mail.py脚本运行的日志文件
    ├── verify_mail.py                  // 检查客户记录邮箱地址有效性的脚本程序，每次运行检查5个地址
    └── verify_mail.log                 // verify_mail.py脚本运行的日志文件
[ js ] 									// 其它第三方Js库文件
    ├── marked.min.js                   // 将Markdowno本地渲染为HTML文件的JS库
    └── highcharts.js                 	// 依赖的JS图表库文件
```
`easyui` 文件夹中的其它文件均为框架源文件，在本项目中未进行引用使用。

------

## 客户端UI界面

### 菜单 功能说明

#### 数据管理

此项包括客户记录的批量导入、导出功能：

| 菜单项 | 功能说明 |
|------|------|
|导出所有客户记录|导出数据库所有的客户记录为CSV格式，其中导出的CSV文件表头为中文表头|
|导出有效客户记录|导出数据库有效性为 1 的客户记录为CSV格式，其中导出的CSV文件表头为中文表头|
|导出无效客户记录|导出数据库有效性为 0 的客户记录为CSV格式，其中导出的CSV文件表头为中文表头|
|导出待跟进客户记录|导出数据库所有待跟进客户记录，其中导出的CSV文件表头为英文表头，以便导入邮件群发软件|
|批量上传客户记录|导出数据库所有的客户记录为CSV格式，其中表头为中文表头|

待跟进客户的筛选条件参考 [待跟进客户 标签页](#followup_module)。

批量上传客户记录时，如果上传的数据表中的邮箱地址已经存在，则会更新数据库中的客户记录，否则将会插入一条新的客户记录。

特别的，上传的数据表中可以将多个邮箱地址写在同一行中，只需要用逗号或空格等分隔符分隔即可，程序会将多个邮箱地址拆开为多条记录分开保存，除邮箱地址外，多条记录中的其它客户信息均保持相同。

#### 系统日志

| 菜单项 | 功能说明 |
|------|------|
|系统通知消息|展开右边侧栏，重新显示进入index.php页面时的系统通知消息，系统消息指 [`messages` 表](#db_messages)中的 `type` 值为 `system` 的所有消息|
|客户邮箱有效性检查日志|在主界面打开一个Tab标签页，显示后台Python程序检查邮箱有效性的日志内容，日志路径 ./script/verify_mail.log|
|邮箱近期邮件的监控日志|在主界面打开一个Tab标签页，显示后台Python程序获取个人邮箱邮件的日志内容，日志路径 ./script/fetch_mail.log|

#### 设置

此项包括系统使用及后台程序运行的参数设置内容。

| 菜单项 | 功能说明 |
|------|------|
|参数设置|打开一个参数设置对话框，设定数据库 [`config` 表](#db_config_items) 中邮件服务相关的设置项|
|外观主题|设置前端UI界面的主题风格，主题引用文件放在 `easyui/theme` 文件夹中 |

参数设置对话框中的设置项说明如下：

| 设置项 | 功能说明 |
|------|------|
|邮箱监控天数上限|设定后台 `fetch_mail.py` 程序只检查邮箱中发件时间距今多长时间的邮件，超过此天数的邮件停止检查|
|邮箱消息保存天数|CRM系统中邮件消息的保存期限，超过此时间的消息 `fetch_mail.py` 程序会自动删除，此设置不会删除邮箱中的原始邮件|
|邮箱服务器地址|个人邮箱使用IMAP协议登陆时服务器地址， `fetch_mail.py` 程序通过连接此服务器获取邮件|
|邮箱服务器端口|您的公司邮箱使用IMAP协议登陆的SSL连接端口号，一般为默认993|
|邮箱服务器账号|您的公司邮箱使用IMAP协议登陆时的账号名|
|邮箱服务器密码|您的公司邮箱使用IMAP协议登陆时的账号登陆密码，部分邮箱服务商需填写专门的第三方客户端登陆密码|

#### 帮助

此项包括 `MyCRM` 系统的相关信息及使用说明。

| 菜单项 | 功能说明 |
|------|------|
|帮助|在主界面打开一个Tab标签页，显示本帮忙文件内容|
|关于|显示本项目的开发说明及许可证协议|

#### 我的消息

此菜单项不包含子菜单，当在 [客户管理列表](#customers_manage_module) 或 [待跟进客户列表](#followup_module) 中选择取一个客户记录时，会在此菜单项显示消息总数，如 **我的消息(1)**

点击此菜单项或点击侧栏折叠栏会展开UI界面右侧的侧栏显示消息明细内容，如果是通过点击折叠栏展开侧栏，当鼠标移出侧栏消息显示界面范围时侧栏会自动折叠。

### <span id="customers_manage_module">客户管理 标签页</span>

此页面用于展示客户记录明细，可以通过搜索栏对显示的客户信息进行筛选，如果选择了 **只显示有效客户** 则只会显示确认邮箱地址有效的客户及还未确认地址有效性的客户，其中未确认有效性的客户记录会有下划线做为标识，而无效客户则以红色字体标识。

记录表中的上方功能按钮可以对客户记录进行增加、删除、修改操作，双击客户记录也可进行修改，但需注意客户记录的备注和最近联系时间无法通过双击记录进行修改，若需修改客户备注，必须通过点击菜单栏功能按钮打开单独的编辑窗口进行，编辑窗口支持富文本格式(RichText)的备注内容。

### <span id="followup_module">待跟进客户 标签页</span>

此页面用于根据邮箱中与客户的最近联系时间筛选的待跟进客户，可以设置待跟进客户的评级及联系间隔天数，其中待跟进客户是指满足以下要求的客户记录：

1. 客户评级满足需跟进客户的评级要求；
2. `Fetch_mail.py` 脚本监控的个人邮箱中，发件箱中与客户最近一次的发件时间与当前时间大于设定的联系间隔天数；
3. 与当前客户公司中的（通过记录中的公司名判断）所有其它客户联系人最近发件时间均大于设定的联系间隔天数，除非当前客户记录的公司名为空；
4. 客户邮箱地址不是无效邮箱地址（包括有效地址和未确认地址）。

### <span id="charts_module">数据统计 标签页</span>

此标签页(Tab)用于显示数据库中的统计信息，包括客户记录的评级、有效性统计及个人邮箱中收、发邮件的数量统计。

------

## 后端 Python 自动化任务脚本

后端 `Python` 自动化任务脚本放于项目 `script` 文件夹中，包括 `.py` 的脚本文件及 `.json` 配置文件。

安全上考虑，建议不要将 `config.json` 配置文件放于Web目录中，可以考虑移至非公开文件夹，并修改 `.py` 脚本中的配置文件路径。

### config.json 参数配置文件



### verify_mail.py 脚本

本脚本程序用于检查数据库中的客户邮件地址是否有效，检查方法是通过 `<a href="https://verify-email.org/">Verify-Email</a>` 网站的访客接口实现的，其接口访问格式如下：

>https://verify-email.org/home/verify-as-guest/maibox@host.com

如果为有效地址，则服务器会返回类似如下格式的 `Json` 数据：

```json
{
	"email":"maibox@host.com",
	"response":{
		"status":1,
		"log":"Success"
	},
	"credits":4
}
```

其中 `status` 为返回状态码，有效地址返回1，`log` 为检查结果说明， `credits` 为剩余的邮箱地址检查次数，一小时后将会恢复为5次，以下是一个无效邮箱的返回结果示例：

```json
{
	"email":"fake_mailbox@host.com",
	"response":{
		"status":0,
		"log":"MailboxDoesNotExist"
	},
	"credits":3
}
```

如果短时间内邮箱检查次数超过了服务器允许的上限，需要等待一小时后再进行检查，否则 `API` 将会返回如下结果：

```json
"You have reached the limit of 5 emails per hour"
```

由于 `<a href="https://verify-email.org/">Verify-Email</a>` 网站限制，非付费用户每个IP每小时只能检查5个邮箱地址，故程序设定为每次检查5个地址后自动停止，为了后台持续检查邮箱地址是否有效，需要将此脚本程序加入到系统自动运行计划中并设定为每小时运行一次。

本程序会在数据库中保存上次成功完成邮箱检查的时间，当发现当前时间与上次运行时间小于5min时程序自动停止运行，如果间隔时间在5min~61min时，程序将会自动暂停直到间隔时间达到61min才开始检查邮箱有效性。

### fetch_mail.py 脚本

本脚本程序用于检查个人邮箱中的邮件，并将邮件信息保存在数据库中以便在管理系统中直接查看，为减小数据库体积，邮件中的附件（包括附件图片）并不会进行保存。

保存在数据库中的邮件会按照邮箱中所在的文件夹进行归类，归类说明如下：

- 如果邮件所在文件夹包含在 `config.json` 中的 `exclude` 列表中，则此文件夹中的所有邮件会直接忽略，不会保存于数据库中；
- 如果邮件发件人名称为 `config.json` 中的 `admin` 相同（不比较邮箱地址，只考虑发件人昵称），则此邮件保存在数据库中时类型 `type` 会设为 `system`；
- 特别地，如果为腾讯企业邮的退信邮件，则类型 `type` 也会设为 `system` ，同时自动将退信地址加到此邮件消息的收件人中，以便在客户记录中直接看到退信邮件；
- 如果邮件发件人邮箱地址与 `config.json` 中的 `address` 相同（不比较发件人昵称，只考虑发件人地址），则此邮件保存在数据库中时类型 `type` 会设为 `sendto`；
- 所有不满足以上条件中的邮件均会将类型 `type` 会设为 `receive`；

------

## 后端数据库设计

### <span id="db_config">config 表</span>

此表用于保存系统的相关配置信息，数据库设计说明：

|字段名|类型|说明|
|------|------|------|
|parameter|varchar(45)|参数名|
|value|text|保存的参数值|

#### <span id="db_config_items">config 表设置项说明：</span>

|参数名|说明|
|------|------|
|followup_days|客户跟进提醒的最小间隔天数，值为正整数|
|followup_importance|客户跟进提醒的最低客户评级，大于或等于此评级的会进行提醒|
|monitor_mail_days|跟踪获取多少天内与客户的来往邮件|
|theme|设置的外观主题名称|
|mail_checked_date|程序上次运行检查邮件的时间|
|mail_host|邮箱服务器地址|
|mail_port|邮件服务器使用SSL连接的端口号|
|mail_username|邮箱账号用户名|
|mail_password|邮箱账号密码|

### <span id="db_customers">customers 表</span>

此表用于保存客户的相关信息：

|字段名|类型|说明|
|------|------|------|
|id| int unsigned primary key|记录ID编号|
|name|varchar(128)|客户姓名|
|email|varchar(256)|客户邮件地址|
|importance|tinyint unsigned|客户评级，值为0-5|
|company|varchar(128)|客户的公司名|
|country|varchar(30)|客户所在国家|
|address|varchar(256)|客户地址|
|phone|varchar(30)|客户的联系电话|
|website|varchar(256)|客户的网站地址|
|comment|varchar(512)|自定义的备注信息|
|last_contact_date|datetime|最近一次联系客户的时间，由后台程序自动更新|
|last_checked_date|datetime|上次检查邮件地址有效性的时间，由 `verify_mail.py` 脚本自动更新|
|valid|tinyint unsigned|客户邮箱是否有效，由 `verify_mail.py` 脚本自动更新，值为0代表无效地址，1代表有效地址，2代表脚本还未进行检查|

### <span id="db_messages">messages 表</span>

此表用于保存个人邮箱中的邮件内容，但不包括联系人邮件地址，邮件地址保存在 [`msg_addrs`](#db_msg_addrs) 表中

|字段名|类型|说明|
|------|------|------|
| id | int unsigned primary key | 记录ID编号 |
|sender|varchar(256)|发件人名称|
|receivers|text|发件或收件人邮箱地址，多个地址用","分隔 *此项可以移至address表中*
|date|datetime|邮件发送/接收时间|
|type|enum('receive','sendto','comment','system')|当前消息记录的类型|
|subject|vchar(256)|邮件的主题|
|content|text|邮件内容|

- 消息记录的 `type` 类型包括以下四种：
  1. **receive** : 表示此消息为收到的客户邮件消息
  2. **sendto** : 表示此消息为发送给客户的邮件消息
  3. **comment** : 表示备注消息，保留用于后续系统功能升级
  4. **system** : 表示此消息为邮箱中的系统消息，打开 `MyCRM` 前端界面时会默认自动显示

### <span id="db_msg_addrs">msg_addrs 表</span>

|字段名|类型|说明|
|------|------|------|
| id | int unsigned primary key | 记录ID编号 |
|msg_id| int unsigned| `Foreign Key` 项，表示 `messages` 表中的ID编号|
|address|varchar(256)|发件或收件人的邮箱地址，表示 `messages` 表中的消息哪些人可以看得到 |
