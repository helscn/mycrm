<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta name="keywords" content="web,crm">
	<meta name="description" content="一个简单的CRM管理系统，为老婆独家定制！">
    <title>CRM System</title>
    <?php
        include 'mycrm/conn.php';
		$sql="SELECT value from config where parameter='theme'";
		$result=mysqli_query($conn,$sql);
		$row = mysqli_fetch_object($result);
		mysqli_close($conn);
		$theme=$row->{'value'};
        echo '<link rel="stylesheet" type="text/css" href="easyui/themes/'.$theme.'/easyui.css">';
    ?>
	<link rel="stylesheet" type="text/css" href="easyui/themes/icon.css">
	<link rel="stylesheet" type="text/css" href="easyui/plugins/texteditor/texteditor.css">
	<link rel="stylesheet" type="text/css" href="easyui/style.css">
	<script type="text/javascript" src="easyui/jquery.min.js"></script>
	<script type="text/javascript" src="easyui/jquery.easyui.min.js"></script>
	<script type="text/javascript" src="easyui/jquery.edatagrid.js"></script>
	<script type="text/javascript" src="easyui/plugins/texteditor/jquery.texteditor.js"></script>
	<script type="text/javascript" src="easyui/locale/easyui-lang-zh_CN.js" charset="utf-8"></script>
	<script type="text/javascript" src="easyui/marked.min.js"></script>
	
	<script type="text/javascript">
		// UI初始化及事件绑定
		$(function(){
			// 定义自定义数据验证规则
			$.extend($.fn.validatebox.defaults.rules, {
				stars: {
					validator: function(value, param){
						return /^[0-5]$/.test(value);
					},
					message: '输入重要程度星级，值为0-5.'
				},
				bool: {
					validator: function(value,param){
						return /^[012]$/.test(value);
					},
					message: '输入0-2的半角数字，0代表❌，1代表✅，2代表❓'
				},
				positiveInt: {
					validator: function(value,param){
						return /^\d+$/.test(value);
					},
					message: '必须输入一个合法的正整数！'
				}
			});


			// 绑定数据管理菜单的点击事件
			$("#menu_db").menu({ 
            	onClick: function (item) { 
                	if (item.text=="导出所有客户记录"){
						window.open("mycrm/export_customers.php?type=all");
					}else if(item.text=="导出有效客户记录"){
						window.open("mycrm/export_customers.php?type=valid");
					}else if(item.text=="导出无效客户记录"){
						window.open("mycrm/export_customers.php?type=invalid");
					}else if(item.text=="导出待跟进客户记录"){
						window.open("mycrm/export_customers.php?type=followup");
					}else if(item.text=="批量上传客户记录"){
						$('#dlg_upload').dialog('open');
					}
              	}
			});
			
			// 绑定系统日志菜单的点击事件
			$("#menu_log").menu({
				onClick: function (item) { 
                	if (item.text=="系统通知消息"){
						update_msg('');
						show_msg();
					}else if(item.text=="客户邮箱有效性检查日志"){
						title="邮箱有效性检查日志";
						if ($('#main_tabs').tabs('exists', title)){
							$('#main_tabs').tabs('select',title);
						} else {
							$.ajax({url:"script/verify_mail.log",success:function(result){
								$('#main_tabs').tabs('add',{
									title:title,
									content:html2Escape(result),
									closable:true,
									iconCls:'icon-log'
								});
							}});
						}
						//window.open("script/verify_mail.log");
					}else if(item.text=="邮箱近期邮件的监控日志"){
						title="邮箱监控日志";
						if ($('#main_tabs').tabs('exists', title)){
							$('#main_tabs').tabs('select',title);
						} else {
							$.ajax({url:"script/fetch_mail.log",success:function(result){
								$('#main_tabs').tabs('add',{
									title:title,
									content:html2Escape(result),
									closable:true,
									iconCls:'icon-log'
								});
							}});
						}
					}
              	}
			});

			// 绑定设置菜单的点击事件
			$("#menu_setting").menu({ 
            	onClick: function (item) { 
                	if (item.text=="参数设置"){
						$('#dlg_config').dialog('open');
					}else if(item.text.indexOf("主题") != -1){
						theme=item.text.replace("主题","").trim().toLowerCase();
						$.getJSON("mycrm/update_config.php?theme="+theme,function(result){
							location.reload();
						});
					}
              	}
			});

			// 绑定帮助菜单的点击事件
			$("#menu_help").menu({ 
            	onClick: function (item) { 
                	if (item.text=="关于..."){
						$.messager.alert('关于','My CRM客户关系管理系统作者为 <a href="https://github.com/helscn/mycrm" target="my_crm_author">helscn</a>，当前仍处于产品基础原型阶段，使用了<a href="https://jquery.com" target="jquery">jQuery</a>、<a href="http://www.jeasyui.net" target="easyui">easyUI</a>开源框架进行项目开发，遵循<a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="GPLv3">GPLv3许可证协议</a>发布。');
					}else if(item.text=="帮助"){
						if ($('#main_tabs').tabs('exists', item.text)){
							$('#main_tabs').tabs('select', item.text);
						} else {
							//content = '<iframe scrolling="auto" frameborder="0"  src="README.html" style="width:100%;height:100%;"></iframe>';
							$.ajax({url:"README.md",success:function(result){
								$('#main_tabs').tabs('add',{
									title:item.text,
									content:marked(result),
									closable:true,
									iconCls:'icon-help'
								});
							}});
						}
					}
              	}
			});

			// 绑定是否只显示有效客户的switchbutton事件
			$('#onlyValid').switchbutton({
				onChange: function(checked){
					update_dg();
				}
			});

			// 初始化客户查询总表的datagrid
			$('#dg').edatagrid({
				url: 'mycrm/get_customer.php',
				saveUrl: 'mycrm/add_customer.php',
				updateUrl: 'mycrm/update_customer.php',
				destroyUrl: 'mycrm/del_customer.php',
				loadMsg: '正在载入数据，请稍候……',
				queryParams: {
					searchType: $('#searchValue').searchbox('getName'),
					searchValue: '%',
					onlyValid: 'true'
				},
				onSelect: function (index,row){
					update_msg(row['email']);
				},
				onLoadError: function(){
					$.messager.alert('错误','从服务器载入数据时出现错误！');
				},
				rowStyler: function(index,row){
					if (row.valid=='0'){
						return 'color:#f00;text-decoration:line-through;';
					}else if (row.valid=='1'){
						return 'color:#000;';
					}else if(row.valid=='2'){
						return 'text-decoration:underline;';
					}
				}
			});

			// 初始化待跟进客户总表的datagrid
			$('#dg_followup').edatagrid({
				url: 'mycrm/get_followup_customer.php',
				saveUrl: 'mycrm/add_customer.php',
				updateUrl: 'mycrm/update_customer.php',
				destroyUrl: 'mycrm/del_customer.php',
				loadMsg: '正在载入数据，请稍候……',
				onLoadError: function(){
					$.messager.alert('错误','从服务器载入数据时出现错误！');
				},
				onSelect: function (index,row){
					update_msg(row['email']);
				},
				rowStyler: function(index,row){
					if (row.valid=='0'){
						return 'color:#f00;text-decoration:line-through;';
					}else if (row.valid=='1'){
						return 'color:#000;';
					}else if(row.valid=='2'){
						return 'text-decoration:underline;';
					}
				}
			});

			// 初始化待跟进客户评级的比较符选择
			$('#followup_importance_operators').combobox({
				editable:false,
				panelHeight:'110px',
				onChange: function(newValue,oldValue){
					update_dg_followup();
				}
			});

			// 初始化待跟进客户评级选择
			$('#followup_importance').combobox({
				editable:false,
				panelHeight:'180px',
				onChange: function(newValue,oldValue){
					update_dg_followup();
				}
			});
			
			// 初始化待跟进客户的间隔天数选择
			$('#followup_days').slider({
				min:1,
				max:100,
				showTip:true,
				rule:[1,25,50,75,100],
				tipFormatter: function(value){
					return '<b>'+ value + '&nbsp;天</b>';
				},
				onChange: function(oldValue,newValue){
					delay_run(update_dg_followup,1000);
				}
			});

			// 初始化批量客户上传文件框
			$('#csv_file').filebox({
				buttonText: '选择文件',
				buttonAlign: 'right',
				accept: 'text/csv'
			});
            
            //更新参数配置中的当前设定值
            $.getJSON("mycrm/get_config.php",function(result){
                $.each(result, function(i, para){
					if(para.parameter=='followup_importance_operators'){
						$('#followup_importance_operators').combobox('setValue',result['followup_importance_operators']);
					}else if(para.parameter=='followup_importance'){
						$('#followup_importance').combobox('setValue',result['followup_importance']);
					}else if(para.parameter=='followup_days'){
						$('#followup_days').slider('settValue',result['followup_days']);
					}else{
                    	$("#frm_config [name='"+para.parameter+"']").val(para.value);
					}
                });
				$('#frm_config').form('validate');
            });

            //更新客户记录表
			update_dg();
			update_dg_followup();

			//显示系统消息
			update_msg('');
			show_msg();
		});


		// 延迟执行程序，如果重复触发的话只有最后一次生效
		var timeOutId=null;
		function delay_run(func,timeout){
			clearTimeout(timeOutId);
			timeOutId=setTimeout(func,timeout);
		}

		//打开右侧侧栏并显示系统消息
		function show_msg(){
			$('#main_app').layout('expand','east');
		}

		//更新客户来往邮件消息的分页及显示内容
		function update_msg(email,page=1,rows=10,force=false){
			if (email!=$('#msg_address').val() || force==true){
				$('#msg_address').val(email);
				$.getJSON(
					'mycrm/get_messages.php?type=count&email='+email,
					function(result){
						$('#notice_count').text(result);
						$('#msg_pp').pagination({
							total:result,
							onSelectPage: function(page,rows){
								update_msg($('#msg_address').val(),page,rows,true)
							}
						});
						$('#message_list').panel(
							'refresh',
							'mycrm/get_messages.php?&email='+email+'&page='+page+'&rows='+rows
						);
					}
				);
			}
		}
		
		// 保存富文本编辑器中的内容
		function save_comment(){
			var dg=$('#edit_grid').val();
			var index = $(dg).datagrid('getRowIndex', $(dg).datagrid('getSelected'));
			var row=$(dg).datagrid('getSelected')
			row['comment']=$('#rich_texteditor').texteditor('getValue');
			
			$.post(
				'mycrm/update_customer.php',
				row,
				function(data){
					$($('#edit_grid').val()).edatagrid('reload');
					$('#dlg_texteditor').dialog('close');
				},
				'json'
			);
		}

		// 打开富文本编辑器
		function show_comment_editor(dg){
			var index = $(dg).datagrid('getRowIndex', $(dg).datagrid('getSelected'));
			var row=$(dg).datagrid('getSelected')
			if(row){
				$('#edit_grid').val(dg);
				$('#rich_texteditor').texteditor('setValue',row['comment']);
				$('#dlg_texteditor').dialog('open');
			}
		}

		// 刷新客户管理的数据表
		function update_dg(){
			value=$('#searchValue').searchbox('getValue');
			$('#dg').edatagrid({
				queryParams: {
					searchType: $('#searchValue').searchbox('getName'),
					searchValue: !value?'%':value+'%',
					onlyValid: $('#onlyValid').switchbutton('options')['checked']
				}
			});
			$('#dg').edatagrid('reload');
		}

		// 刷新待跟进客户记录表
		function update_dg_followup(){
			$.post(
				'mycrm/update_config.php',
				{
					'followup_importance_operators' : $('#followup_importance_operators').combobox('getValue'),
					'followup_importance' : $('#followup_importance').combobox('getValue'),
					'followup_days': $('#followup_days').slider('getValue')
				},
				function (data){
					$('#dg_followup').edatagrid('reload');
				}
			);
		}

		//下载当前筛选条件下的客户记录
		function download_dg(){
			value=$('#searchValue').searchbox('getValue');
			searchType = $('#searchValue').searchbox('getName');
			searchValue = !value?'%':value+'%';
			onlyValid = $('#onlyValid').switchbutton('options')['checked']
			window.open('mycrm/get_customer.php?type=csv&searchType='+searchType+'&searchValue='+searchValue+'&onlyValid='+onlyValid);
		}
		
		//HTML标签转义（< -> &lt;）
		function html2Escape(sHtml) {
			return sHtml.replace(/(\r\n)|[\r\n<>&"]/g,function(c){
				return {'\r\n':'<br>','\r':'<br>','\n':'<br>','<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];
			});
		}

		// 格式化客户的星级
		function formatStars(val,row){
			return new Array(parseInt(val)+1).join('⭐');
		};

		// 格式化布尔值，显示为✅和❌
		function formatBool(val,row){
			if (val=='0'){
				return '❌';
			}else if(val=='1'){
				return '✅';
			}else if(val=='2'){
				return '❓';
			}else{
				return '';
			}
		};
		
		function formatComment(val){
			val=$('<div>'+val+'</div>').text();
			return val.replace(/\r|\n/g,' ')
		}

		// 打开增加客户记录的输入表单
		function add_customer(){
			$('#frm_add_customer').form('clear');
			$('#dlg_add_customer').dialog('open');
		}

		// 新增客户的表单提交处理
		function submit_add_customer(){
			$.messager.progress();	// 显示进度条
			$('#frm_add_customer').form('submit', {
				url: 'mycrm/add_customer.php',
				onSubmit: function(){
					var isValid = $(this).form('validate');
					if (!isValid){
						$.messager.progress('close');	// 表单验证失败时隐藏进度条
					}
					return isValid;	// 返回false将会取消表单提交
				},
				success: function(data){
					$.messager.progress('close');	// 提交完成后隐藏进度表
					$('#dlg_add_customer').dialog('close');
					if (data=='1'){
						$('#dg').edatagrid('reload');
						$('#dg_followup').edatagrid('reload');
					}else{
						$.messager.alert('客户添加失败',data);
					}
				}
			});
        };
        
        //提交参数设置表单
        function submit_config(){
			$.messager.progress();	// 显示进度条
			$('#frm_config').form('submit', {
				url: 'mycrm/update_config.php',
				onSubmit: function(){
					var isValid = $(this).form('validate');
					if (!isValid){
						$.messager.progress('close');	// 表单验证失败时隐藏进度条
					}
					return isValid;	// 返回false将会取消表单提交
				},
				success: function(data){
					$.messager.progress('close');	// 提交完成后隐藏进度表
					$('#dlg_config').dialog('close');
					if (data=='1'){
						$.messager.alert('已保存','参数设置已保存完毕。');
					}else{
						$.messager.alert('保存失败','无法保存当前的参数设置：\n'+data);
					}
				}
			});
        };

        //提交批量客户上传表单
        function submit_upload(){
			$.messager.progress();	// 显示进度条
			$('#frm_upload').form('submit', {
				url: 'mycrm/upload_customers.php',
				onSubmit: function(){
					var isValid = $(this).form('validate');
					if (!isValid){
						$.messager.progress('close');	// 表单验证失败时隐藏进度条
					}
					return isValid;	// 返回false将会取消表单提交
				},
				success: function(data){
					$.messager.progress('close');	// 提交完成后隐藏进度表
					$('#dlg_upload').dialog('close');
					if (/^\d+$/.test(data)){
						$.messager.alert('上传完毕','共有 '+data+' 条有效客户记录已经上传至数据库中。');
						update_dg();
						$('#dg_followup').edatagrid('reload');
					}else{
						$.messager.alert('上传失败',data);
					}
				}
			});
        };
	</script>

</head>
<body id="main_app" class="easyui-layout" data-options="fit:true">
	<div data-options="region:'north',title:'CRM管理系统',collapsible:false" style="height:65px;">
		<!--顶部菜单项-->
		<div id="menubar" style="text-align:right;padding:0px;height:30px;width:100%;border:1px solid #ccc">
			<a href="#" class="easyui-menubutton" menu="#menu_db" iconCls="icon-db_config">数据管理</a>
			<a href="#" class="easyui-menubutton" menu="#menu_log" iconCls="icon-log">系统日志</a>
			<a href="#" class="easyui-menubutton" menu="#menu_setting" iconCls="icon-config">设置</a>
			<a href="#" class="easyui-menubutton" menu="#menu_help" iconCls="icon-help">帮助</a>
			<a href="javascript:show_msg();" class="easyui-linkbutton" data-options="iconCls:'icon-message',plain:true">我的消息(<span id="notice_count">0</span>)</a>
		</div>
		<div id="menu_db" style="width:150px;">
			<div iconCls="icon-csv">导出所有客户记录</div>
			<div iconCls="icon-csv">导出有效客户记录</div>
			<div iconCls="icon-csv">导出无效客户记录</div>
			<div iconCls="icon-csv">导出待跟进客户记录</div>
			<div class="menu-sep"></div>
			<div iconCls="icon-upload">批量上传客户记录</div>
		</div>
		<div id="menu_log" style="width:180px;">
			<div iconCls="icon-message">系统通知消息</div>
			<div class="menu-sep"></div>
			<div iconCls="icon-log">客户邮箱有效性检查日志</div>
			<div iconCls="icon-log">邮箱近期邮件的监控日志</div>
		</div>
		<div id="menu_setting" style="width:100px;">
			<div iconCls="icon-property">参数设置</div>
			<div class="menu-sep"></div>
			<div iconCls="icon-theme">
				<span>外观主题</span>
				<div style="width:180px">
					<div iconCls="icon-theme">Default 主题</div>
					<div iconCls="icon-theme">Bootstrap 主题</div>
					<div iconCls="icon-theme">Material 主题</div>
					<div iconCls="icon-theme">Material-teal 主题</div>
					<div iconCls="icon-theme">Metro 主题</div>
					<div iconCls="icon-theme">Gray 主题</div>
					<div iconCls="icon-theme">Black 主题</div>
				</div>
			</div>
		</div>
		<div id="menu_help" style="width:100px;">
			<div iconCls="icon-help">帮助</div>
			<div class="menu-sep"></div>
			<div iconCls="icon-about">关于...</div>
		</div>
		
    </div>

    <div id="customers_dg" data-options="region:'center'" >
		<!--Tabs页面布局-->
		<div id="main_tabs" class="easyui-tabs">
			<!--客户记录查询Tab-->
			<div title="客户管理" data-options="iconCls:'icon-customer'" style="padding:10px">
				<!--客户筛选查询框-->
				<div style="margin-top:10px;margin-bottom:12px;">
				<div id="searchType" style="width:120px">
					<div data-options="name:'email'">邮箱</div>
					<div data-options="name:'name'">联系人</div>
					<div data-options="name:'importance'">客户评级</div>
					<div data-options="name:'company'">公司名</div>
					<div data-options="name:'country'">国家</div>
					<div data-options="name:'address'">地址</div>
					<div data-options="name:'phone'">电话</div>
					<div data-options="name:'website'">网站</div>
					<div data-options="name:'comment'">备注</div>
				</div>
				<input id="searchValue" class="easyui-searchbox" style="width:300px"
					data-options="searcher:update_dg,prompt:'请输入要筛选的值',menu:'#searchType'">
				</input>
				<span style="margin-left:40px;">只显示有效记录：</span>
				<input id="onlyValid" class="easyui-switchbutton" data-options="onText:'Yes',offText:'No'" checked>
				</div>
				<!--分隔线-->
				<!--<hr style="border:1px dashed gray">-->

				<!--客户记录查询总表的菜单项-->
				<div id="toolbar">
						<a href="#" class="easyui-linkbutton" iconCls="icon-add" plain="true"  onclick="javascript:add_customer()">增加</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-remove" plain="true" onclick="javascript:$('#dg').edatagrid('destroyRow')">删除</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-save" plain="true" onclick="javascript:$('#dg').edatagrid('saveRow')">保存</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-undo" plain="true" onclick="javascript:$('#dg').edatagrid('cancelRow')">取消</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-property" plain="true" onclick="javascript:show_comment_editor('#dg');">修改客户备注信息</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-csv" plain="true" onclick="javascript:download_dg();">导出当前记录</a>
				</div>
				<!--客户记录数据绑定的datagrid表格-->
				<table id="dg" title="我的客户" 
						toolbar="#toolbar" pagination="true" idField="id"
						rownumbers="true" fitColumns="true" singleSelect="true">
					<thead>
						<tr>
							<th field="name" width="80px" sortable="true" editor="text">联系人</th>
							<th field="email" width="120px" sortable="true" editor="{type:'validatebox',options:{validType:'email'}}">邮箱</th>
							<th field="importance" width="80px" sortable="true" editor="{type:'validatebox',options:{required:true,validType:'stars'}}" data-options="formatter:formatStars">客户评级</th>
							<th field="company" width="120px" sortable="true" editor="text">公司</th>
							<th field="country" width="60px" sortable="true" editor="text">国家</th>
							<th field="address" width="120px" sortable="true" editor="text">地址</th>
							<th field="phone" width="120px" sortable="true" editor="text">电话</th>
							<th field="website" width="60px" sortable="true" editor="text">网站</th>
							<th field="comment" width="120px" sortable="true" formatter="formatComment">备注</th>
							<th field="last_contact_date" width="120px" sortable="true" >最近联系时间</th>
							<th field="valid" width="50px" sortable="true" align="center" editor="{type:'validatebox',options:{required:true,validType:'bool'}}" data-options="formatter:formatBool">有效性</th>
						</tr>
					</thead>
				</table>
			</div>

			<!--待跟进客户的显示Tab-->
			<div title="待跟进客户" data-options="iconCls:'icon-followup'" style="padding:10px">
				<!--客户筛选查询框-->
				<div style="margin-top:0px;margin-bottom:12px;">
				<table>
					<tr style="height:50px">
						<td style="width:110px">客户跟进评级：</td>
						<td>
							<select id="followup_importance_operators" style="width:100px;">
								<option value=">=">大于等于</option>
								<option value=">">大于</option>
								<option value="=">等于</option>
							</select>
							
							<select id="followup_importance" style="width:140px;">
								<option value="1">⭐</option>
								<option value="2">⭐⭐</option>
								<option value="3">⭐⭐⭐</option>
								<option value="4">⭐⭐⭐⭐</option>
								<option value="5">⭐⭐⭐⭐⭐</option>
							</select>
							</td>
						<td style="width:40px"></td>
						<td>客户跟进间隔时间：</td>
						<td>
							<input id="followup_days" name="followup_days" class="easyui-slider" value="30" style="width:250px">
						</td>
					</tr>
				</table>
				</div>
				<!--分隔线-->
				<!--<hr style="border:1px dashed gray">-->

				<!--待跟进的客户列表工具栏-->
				<div id="toolbar_followup">
						<a href="#" class="easyui-linkbutton" iconCls="icon-add" plain="true"  onclick="javascript:add_customer()">增加</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-remove" plain="true" onclick="javascript:$('#dg_followup').edatagrid('destroyRow')">删除</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-save" plain="true" onclick="javascript:$('#dg_followup').edatagrid('saveRow')">保存</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-undo" plain="true" onclick="javascript:$('#dg_followup').edatagrid('cancelRow')">取消</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-property" plain="true" onclick="javascript:show_comment_editor('#dg_followup');">修改客户备注信息</a>
						<a href="#" class="easyui-linkbutton" iconCls="icon-csv" plain="true" onclick="javascript:window.open('mycrm/export_customers.php?type=followup');">导出待跟进客户</a>
				</div>
				<!--待跟进客户列表-->
				<table id="dg_followup" title="待跟进客户清单" 
						toolbar="#toolbar_followup" pagination="true" idField="id"
						rownumbers="true" fitColumns="true" singleSelect="true">
					<thead>
						<tr>
							<th field="name" width="80px" sortable="true" editor="text">联系人</th>
							<th field="email" width="120px" sortable="true" editor="{type:'validatebox',options:{validType:'email'}}">邮箱</th>
							<th field="importance" width="80px" sortable="true" editor="{type:'validatebox',options:{required:true,validType:'stars'}}" data-options="formatter:formatStars">客户评级</th>
							<th field="company" width="120px" sortable="true" editor="text">公司</th>
							<th field="country" width="60px" sortable="true" editor="text">国家</th>
							<th field="address" width="120px" sortable="true" editor="text">地址</th>
							<th field="phone" width="120px" sortable="true" editor="text">电话</th>
							<th field="website" width="60px" sortable="true" editor="text">网站</th>
							<th field="comment" width="120px" sortable="true" editor="text" data-options="formatter:formatComment">备注</th>
							<th field="last_contact_date" width="120px" sortable="true">最近联系时间</th>
							<th field="valid" width="50px" sortable="true" align="center" editor="{type:'validatebox',options:{required:true,validType:'bool'}}" data-options="formatter:formatBool">有效性</th>
						</tr>
					</thead>
				</table>
			</div>
			
		</div>

		<!--富文本编辑器对话框-->
		<div id="dlg_texteditor" title="备注" align="center" class="easyui-dialog" iconCls="icon-property" modal=true style="padding:0px;height:400px; width:650px;" closed=true resizable=true buttons="#dlg_texteditor_buttons">
			<input id="edit_grid" type="hidden" value=""></input>
			<div id="rich_texteditor" class="easyui-texteditor" style="width:100%;height:100%;padding:20px">
			</div>
		</div>
		<!--富文本编辑器对话框底部按钮-->
		<div id="dlg_texteditor_buttons">
			<a href="#" class="easyui-linkbutton" iconCls="icon-ok" onclick="javascript:save_comment();">确定</a>
			<a href="#" class="easyui-linkbutton" iconCls="icon-cancel" onclick="javascript:$('#dlg_texteditor').dialog('close')">取消</a>
		</div>

		<!--参数设置对话框-->
		<div id="dlg_config" title="参数设置" align="center" class="easyui-dialog" iconCls="icon-property" style="padding:10px;width:450px;" closed=true resizable=true buttons="#dlg_config_buttons">
			<form id="frm_config" class="frm" method="post">
				<table>
					<tr>
						<td align="right">
							<label for="monitor_mail_days">
								<span title="设定后台程序只检查邮箱中发件时间距今多长时间的<br>邮件，超过此天数的邮件停止检查。" class="easyui-tooltip">邮箱监控天数上限:</span>
							</label>
						</td>
						<td>
							<input class="easyui-validatebox" type="text" name="monitor_mail_days" data-options="required:true,validType:'positiveInt'" />
						</td>
					</tr>
					<tr>
						<td align="right">
							<label for="mail_reserved_days">
								<span title="CRM系统中邮件消息的保存期限，超过此时间的消息会自动删除。<br><b>注意：</b>此设置不会删除邮箱中的原始邮件！" class="easyui-tooltip">邮箱消息保存天数:</span>
							</label>
						</td>
						<td>
							<input class="easyui-validatebox" type="text" name="mail_reserved_days" data-options="required:true,validType:'positiveInt'" />
						</td>
					</tr>
					<tr>
						<td align="right">
							<label for="mail_host">
								<span title="您的公司邮箱使用IMAP协议登陆时服务器地址。" class="easyui-tooltip">邮箱服务器地址:</span>
							</label>
						</td>
						<td>
							<input class="easyui-validatebox" type="text" name="mail_host" data-options="required:true" />
						</td>
					</tr>
					<tr>
						<td align="right">
							<label for="mail_port">
								<span title="您的公司邮箱使用IMAP协议登陆的SSL连接端口号，一般为默认993。" class="easyui-tooltip">邮箱服务器端口:</span>
							</label>
						</td>
						<td>
							<input class="easyui-validatebox" type="text" name="mail_port" data-options="required:true,validType:'positiveInt'" />
						</td>
					</tr>
					<tr>
						<td align="right">
							<label for="mail_username">
								<span title="您的公司邮箱使用IMAP协议登陆时的账号名。" class="easyui-tooltip">邮箱服务器账号:</span>
							</label>
						</td>
						<td>
							<input class="easyui-validatebox" type="text" name="mail_username" data-options="required:true" />
						</td>
					</tr>
					<tr>
						<td align="right">
							<label for="mail_password">
								<span title="您的公司邮箱使用IMAP协议登陆时的账号密码。" class="easyui-tooltip">邮箱服务器密码:</span>
							</label>
						</td>
						<td>
							<input class="easyui-passwordbox" prompt="Password" revealed=true name="mail_password"/>
						</td>
					</tr>
				</table>
			</form>
		</div>
		
		<!--参数设置的对话框底部按钮-->
		<div id="dlg_config_buttons">
			<a href="#" class="easyui-linkbutton" iconCls="icon-ok" onclick="javascript:submit_config();">确定</a>
			<a href="#" class="easyui-linkbutton" iconCls="icon-cancel" onclick="javascript:$('#dlg_config').dialog('close')">取消</a>
		</div>

		<!--增加客户的对话框界面-->
		<div id="dlg_add_customer" iconCls="icon-add" class="easyui-dialog" style="padding:5px;width:520px;height:350px;" title="增加客户" closed=true resizable=true buttons="#dlg_add_customer_buttons">
			<form id="frm_add_customer" class="frm" method="post">
				<table align="center">
					<tr>
						<td><label for="name">联系人:</label></td>
						<td><input class="easyui-validatebox" type="text" name="name" data-options="" /></td>
						<td><label for="email">邮箱:</label></td>
						<td><input class="easyui-validatebox" type="text" name="email" data-options="required:true,validType:'email',formatter:formatStars" /></td>
					</tr>
					<tr>
							<td><label for="name">公司:</label></td>
							<td><input class="easyui-validatebox" type="text" name="company" data-options="" /></td>
							<td><label for="name">评级:</label></td>
							<td><input class="easyui-validatebox" type="text" name="importance" data-options="validType:'stars'" /></td>
					</tr>
					<tr>
							<td><label for="name">国家:</label></td>
							<td><input class="easyui-validatebox" type="text" name="country" data-options="" /></td>
							<td><label for="name">地址:</label></td>
							<td><input class="easyui-validatebox" type="text" name="address" data-options="" /></td>
					</tr>
					<tr>
							<td><label for="name">电话:</label></td>
							<td><input class="easyui-validatebox" type="text" name="phone" data-options="" /></td>
							<td><label for="name">网站:</label></td>
							<td><input class="easyui-validatebox" type="text" name="website" data-options="" /></td>
					</tr>
					<tr>
							<td><label for="name">备注:</label></td>
							<td colspan=3><input class="easyui-validatebox" type="text" name="comment" data-options="" /></td>
					</tr>
				</table>
			</form>
		</div>
        <!--增加客户的对话框底部按钮-->
		<div id="dlg_add_customer_buttons">
			<a href="#" class="easyui-linkbutton" iconCls="icon-ok" onclick="javascript:submit_add_customer();">确定</a>
			<a href="#" class="easyui-linkbutton" iconCls="icon-cancel" onclick="javascript:$('#dlg_add_customer').dialog('close')">取消</a>
		</div>

		<!--批量上传客户记录对话框-->
		<div id="dlg_upload" title="批量上传客户记录" align="center" class="easyui-dialog" iconCls="icon-upload" style="padding:10px;width:450px;" closed=true resizable=true buttons="#dlg_upload_buttons">
			<form id="frm_upload" class="frm" method="post" enctype="multipart/form-data">
				<div><input id="csv_file" name="csv_file" type="text" style="width:350px;"></div>
			</form>
		</div>
		<!--批量上传客户记录对话框底部按钮-->
		<div id="dlg_upload_buttons">
			<a href="#" class="easyui-linkbutton" iconCls="icon-ok" onclick="javascript:submit_upload()">确定</a>
			<a href="#" class="easyui-linkbutton" iconCls="icon-cancel" onclick="javascript:$('#dlg_upload').dialog('close')">取消</a>
		</div>


	</div>
	
	<!--右边侧栏显示的来往消息列表-->
    <div id="message_layout" data-options="region:'east',title:'消息',iconCls:'icon-message',split:true,collapsed:true,expandMode:'float'" style="width:500px">
		<div class="easyui-layout" data-options="fit:true">
			<div data-options="region:'north'">
				<div id="msg_pp" class="easyui-pagination"></div>
				<input id="msg_address" type='hidden' value="None"></input>
			</div>

			<div data-options="region:'center'" >
				<!--显示的客户来往邮件内容-->
				<div id="message_list" class="easyui-panel" style="width:100%;" data-options="href:'mycrm/get_messages.php'"></div>
			</div>
		</div>
	</div>

	<!--底部版权信息-->
	<div class="footer" data-options="region:'south'">
		<div>Copyright © 2014-2019 Datton Technology Co.,LTD.  All Rights Reserved. </div>
	</div>
</body>
</html>