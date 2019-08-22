#!/usr/bin/env python3
# -*- encoding:utf-8 -*-

import pymysql
import requests
import json
import re
import logging
import sys
from os.path import join,realpath,split
from datetime import datetime, timezone, timedelta
from time import sleep


# 默认的数据库配置参数
config={
    'db':{
        'host':'localhost',
        'port':3306,
        'db':'my_crm',
        'user':'root',
        'passwd':'root',
        'charset':'utf8'
    }
}

# Json配置文件路径，默认读取脚本程序所在路径中的 config.json 文件,此配置会覆盖config字典中的默认设置
json_file=join(split(realpath(__file__))[0],'config.json')

# Logger的配置设置, log_file 为logger的输出文件路径
log_file=join(split(realpath(__file__))[0],'verify_mail.log')
logger = logging.getLogger('MailVerification')
logging.basicConfig(
    handlers=[logging.FileHandler(encoding='utf-8', mode='a', filename=log_file)],
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%Y/%m/%d %H:%M:%S',
    level=logging.INFO
)
logger.debug('<程序开始运行>')

class DataBase():
    """数据库的封装类，conn为连接对象，cursor为游标对象"""
    def __init__(self, host='localhost', port=3306, db='', user='root', passwd='root', charset='utf8'):
        # 建立连接对象
        self.conn = pymysql.connect(host=host, port=port, db=db, user=user, passwd=passwd, charset=charset)
        # 创建游标，操作设置为字典类型
        self.cursor = self.conn.cursor(cursor = pymysql.cursors.DictCursor)
        # self.cursor = self.conn.cursor()   #操作设置为元组类型

    def __enter__(self):
        # 返回游标
        return self.cursor

    def __exit__(self, exc_type, exc_val, exc_tb):
        # 提交数据库并执行
        self.conn.commit()
        # 关闭游标
        self.cursor.close()
        # 关闭数据库连接
        self.conn.close()

    
# 连接数据库
try:
    # 读取数据库的json配置文件，并更新至默认配置的 db 字典中
    try:
        logger.debug('正在读取json配置文件：{0}'.format(json_file))
        with open(json_file,mode='r',encoding='utf-8') as f:
            config.update(json.load(f))
    except FileNotFoundError as e:
        logger.error('未找到json配置文件:{0}'.format(json_file))

    # 将数据库配置写入json文件
    try:    
        logger.debug('正在将配置信息写入json文件：{0}'.format(json_file))
        with open(json_file,mode='w',encoding='utf-8') as f:
            f.write(json.dumps(config,indent=4))
    except Exception as e:
        logger.error('将配置参数写入json配置文件时出现错误:{0}'.format(json_file))

    with DataBase(**config['db']) as cursor:
        logger.debug('连接数据库成功，正在检查数据库中的上次运行时间...')
        min_interval_seconds=5*60   # 上次程序运行时间距今小于此值时程序停止
        interval_seconds=61*60      # 上次程序运行时间距今小于此值时程序自动延时

        # 检查上次程序运行时的邮箱检查时间
        sql="SELECT value FROM config WHERE parameter='mail_checked_date'"
        if cursor.execute(sql)>0:
            value=cursor.fetchone()['value']
            mail_checked_date=datetime.strptime(value,'%Y-%m-%d %H:%M:%S')
        
            # 计算上次检查时间与当前时间的差值
            time_delta=(datetime.now()-mail_checked_date).seconds
            logger.debug('上次程序运行时间为{0}。'.format(mail_checked_date))
        else:
            # 未找到mail_checked_date配置项，自动插入参数配置项到数据库中
            logger.error('数据库中未找到mail_checked_date配置项，自动插入此配置项到数据库中...')
            sql="INSERT INTO config(parameter,value) VALUES ('mail_checked_date','{0}')".format(datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            cursor.execute(sql)
            time_delta=interval_seconds+1
            
    if time_delta<min_interval_seconds:
        # 上次检查时间与当前程序运行时间过近
        logger.debug('上次检查时间距目前仅{0}秒，间隔时间过短程序自动停止。'.format(time_delta))
        sys.exit(0)
    elif time_delta<(interval_seconds):
        # 程序自动延时以保证时间间隔大于interval_seconds
        logger.debug('程序自动延时{0}分钟以确保邮箱检查的间隔时间。'.format(int((interval_seconds-time_delta)/60)))
        sleep(interval_seconds-time_delta)
        
    with DataBase(**config['db']) as cursor:
        logger.debug('正在从服务器获取待检查的邮箱列表...')
        sql='SELECT email FROM customers WHERE valid=1 ORDER BY last_checked_date ASC LIMIT 5 OFFSET 0'
        #sql='SELECT email FROM customers ORDER BY last_checked_date ASC LIMIT 5 OFFSET 0'
        cursor.execute(sql)
        results=cursor.fetchall()
        records=[]
        for row in results:
            email=row['email'].strip()
            logger.debug('正在检查邮箱：{0}'.format(email))
            try:
                resp=requests.get(r'https://verify-email.org/home/verify-as-guest/{0}'.format(email),timeout=30)
                if 'response' in resp.json():
                    logger.info('邮箱 %s 检查结果：%s',email,re.sub(r'\r|\n','',resp.json()['response']['log']))
                    records.append((
                        1 if resp.json()['response']['log']=='Success' else 0,
                        datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                        email
                    ))
                else:
                    logger.error('verify-email网站拒绝了邮箱检查服务：{0}'.format(resp.text))
                    break
            except Exception as e:
                logger.error('检查邮箱 %s 时出现网络错误: %s',email,e)
        if records:
            # 更新邮箱地址验证结果
            logger.debug('正在将邮箱有效性结果写入数据库...')
            sql="UPDATE customers SET valid=%s,last_checked_date=%s WHERE email=%s"
            cursor.executemany(sql,records)

            # 更新mail_checked_date配置项为当前时间
            logger.debug('正在将本次程序检查时间写入数据库...')
            sql="UPDATE config SET value='{0}' WHERE parameter='mail_checked_date'".format(datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            cursor.execute(sql)
    logger.debug('程序执行完毕！')
except Exception as e:
    logger.error('程序运行错误: %s',e)
    logger.critical('程序异常停止!',exc_info=True)
    sys.stderr.write(str(e)+'\n')
    raise e


        
