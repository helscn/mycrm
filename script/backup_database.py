#!/usr/bin/env python3
# -*- encoding:utf-8 -*-

import subprocess
import time
import json
import sys
import logging
from os.path import join,split,realpath
from datetime import datetime,timedelta
from ftplib import FTP


# 默认的配置参数
config={
    "db": {
        "port": 3306,
        "charset": "utf8",
        "host": "localhost",
        "user": "root",
        "db": "my_crm",
        "passwd": "root",
    },
    "sqldump_path":"/volume1/@appstore/MariaDB10/usr/local/mariadb10/bin/mysqldump",
    "ftp":{
        "host": "hk2.minyo.us",
        "port": 21,
        "user":"dtntechc",
        "passwd":"@sf#8024",
        "local_file":"/volume1/script/mycrm.sql",
        "remote_file":"backup/mycrm-%Y%m%d.sql",
        "days_saved":10,
        "sftp":False
    }
}

# Logger的配置设置, log_file 为logger的输出文件路径
log_file=join(split(realpath(__file__))[0],'backup_database.log')

logging.basicConfig(
    handlers=[logging.FileHandler(encoding='utf-8', mode='a', filename=log_file)],
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%Y/%m/%d %H:%M:%S',
    level=logging.INFO
)
logger = logging.getLogger('BackupDatabase')
logger.debug('<程序开始运行>')

# Json配置文件路径，默认读取脚本程序所在路径中的 config.json 文件,此配置会覆盖config字典中的默认设置
json_file=join(split(realpath(__file__))[0],'config.json')

# 读取json配置文件，并更新至默认配置的 config 字典中
try:
    logger.debug('正在读取json配置文件：%s',json_file)
    with open(json_file,mode='r',encoding='utf-8') as f:
        config.update(json.load(f))
except Exception as e:
    logger.error('无法读取json文件中的配置信息，使用默认配置运行。')
    sys.stderr.write('Can not import the config of json file.\n')

# 将配置写入json文件
try:
    logger.debug('正在将配置信息写入json文件：%s',json_file)
    with open(json_file,mode='w',encoding='utf-8') as f:
        f.write(json.dumps(config,indent=4))
except Exception as e:
    logger.error('将配置信息保存至json文件时出现错误！')
    sys.stderr.write('Can not save the config to json file.\n')

def connect_ftp(host,port,user,passwd,sftp=False,**kargs):
    Max_Retry_Count = 3     # 最大重连次数
    Retry_Delay = 10        # 重连时的延迟时间
    retry_count = 0         # 当前重连计数
    if sftp:
        import pysftp
        cnopts=pysftp.CnOpts()
        cnopts.hostkeys=None
        while True:
            try:
                ftp=pysftp.Connection(host=host,port=port,username=user,password=passwd,cnopts=cnopts)
            except Exception as e:
                retry_count += 1
                if retry_count > Max_Retry_Count:
                    logger.error('经过多次尝试连接FTP服务器仍然失败!')
                    sys.stderr.write('Can not connect to the ftp server.\n')
                    raise e
                logger.info('连接FTP服务器失败，正在进行第 {0} 次重试...'.format(str(retry_count)))
                time.sleep(Retry_Delay)
                continue
            break
    else:
        while True:
            ftp = FTP()
            try:
                ftp.set_debuglevel(1)           #打开调试级别1，显示详细信息
                logger.debug('正在连接远程FTP服务器...')
                ftp.connect(host, port, 15)     #连接FTP服务器
                logger.debug('FTP服务器连接成功，正在使用账号密码登陆...')
                ftp.login(user, passwd)         #登录FTP服务器，如果匿名登录则用空串代替即可
            except Exception as e:
                ftp.close()
                retry_count += 1
                if retry_count > Max_Retry_Count:
                    logger.error('经过多次尝试连接FTP服务器仍然失败!')
                    sys.stderr.write('Can not connect to the ftp server.\n')
                    raise e
                logger.info('连接FTP服务器失败，正在进行第 {0} 次重试...'.format(str(retry_count)))
                time.sleep(Retry_Delay)
                continue
            break

    logger.debug('连接FTP服务器成功！')
    return ftp

def uploadFile(ftp,localPath,remotePath):
    try:
        if type(ftp) is FTP:
            dirs = str(remotePath).split("/")
            current_dir=ftp.pwd()
            if dirs[0]=='':     # Absolute address
                curdir=''
            else:
                curdir=current_dir
            if curdir=='/':
                curdir=''
            for i,d in enumerate(dirs):
                if(i==len(dirs)-1):
                    break
                curdir = curdir + "/"+ d
                try:
                    ftp.cwd(curdir)
                except Exception as e:
                    logger.info('FTP服务器上文件夹不存在，正在创建文件夹：%s',curdir)
                    ftp.mkd(curdir)

            ftp.cwd(current_dir)
            bufsize=8192
            with open(localPath,"rb") as f:
                logger.debug('正在上传文件...')
                ftp.storbinary("STOR %s" % remotePath,f,bufsize)
        else:
            remote_path,remote_name=split(remotePath)
            if remote_path:
                if not ftp.isdir(remote_path):
                    logger.info('FTP服务器上文件夹不存在，正在创建文件夹：%s',remote_path)
                    ftp.makedirs(remote_path)
            logger.debug('正在上传文件...')
            ftp.put(localpath=localPath,remotepath=remotePath,preserve_mtime=True)
    except Exception as e:
        logger.error('上传文件至FTP服务器出现错误上传失败！')
        raise e
    
def downloadFile(ftp,remotePath,localPath):
    try:
        if type(ftp) is FTP:
            bufsize=8192
            with open(localPath,"wb") as f:
                ftp.retrbinary("RETR %s" % remotePath,f.write,bufsize)
        else:
            ftp.get(remotepath=remotePath,localpath=localPath,preserve_mtime=True)
    except Exception as e:
        logger.error('下载文件出现错误: %s', e)
        raise e

if __name__ == '__main__':
    try:  
        # 获取当前日期
        today=datetime.today()

        # 获取Database备份文件名
        local_file=config['ftp']['local_file']
        local_file=today.strftime(local_file)
        logger.debug('本地数据库备份文件路径：%s',local_file)
        remote_file=config['ftp']['remote_file']
        remote_path,remote_name=split(remote_file)
        remote_path=today.strftime(remote_path)
        remote_file=remote_path+'/'+today.strftime(remote_name)

        # 导出Database数据至备份文件中
        logger.debug('正在导出 %s 数据库中的数据至备份文件中...',config['db']['db'])
        sqldump="%s --host={host} --port={port} --user={user} --password={passwd} --databases {db}" % config['sqldump_path']
        sqldump=sqldump.format(**config['db']).split(' ')
        with open(local_file,'w') as f:
            subprocess.check_call(sqldump, stdout=f)

        # 连接FTP服务器
        ftp=connect_ftp(**config['ftp'])

        # 删除FTP服务器中旧的备份文件
        logger.debug('远程FTP服务器备份文件路径：%s',remote_file)
        if config['ftp']['sftp']:
            for name in ftp.listdir(remote_path):
                if ftp.isfile(remote_path+'/'+name):
                    try:
                        d=datetime.strptime(name,remote_name).date()
                        if d < (today-timedelta(days=config['ftp']['days_saved'])).date():
                            try:
                                logger.info('正在删除FTP服务器上的过期备份文件：%s',name)
                                ftp.remove(remote_path+'/'+name)
                            except Exception as e:
                                logger.error('删除FTP服务器上的过期备份文件出现错误!')
                    except Exception as e:
                        pass
        else:
            for name,attr in ftp.mlsd(remote_path):
                if attr['type']=='file':
                    try:
                        d=datetime.strptime(name,remote_name).date()
                        d=datetime.strptime(attr['modify'][:8],'%Y%m%d').date()
                        if d < (today-timedelta(days=config['ftp']['days_saved'])).date():
                            try:
                                logger.info('正在删除FTP服务器上的过期备份文件：%s',name)
                                ftp.delete(remote_path+'/'+name)
                            except Exception as e:
                                logger.error('删除FTP服务器上的过期备份文件出现错误!')
                    except Exception as e:
                        pass

        # 上传Database备份文件至FTP服务器
        logger.debug('正在上传备份文件至FTP服务器中...')
        uploadFile(ftp,local_file,remote_file)

        # 关闭FTP连接
        if type(ftp) is FTP:
            ftp.quit()
        else:
            ftp.close()
        logger.info('数据库数据备份完成。')
    except Exception as e:
        logger.error('程序运行错误: %s',e)
        logger.critical('程序异常停止!',exc_info=True)
        sys.stderr.write(str(e)+'\n')
        raise e
