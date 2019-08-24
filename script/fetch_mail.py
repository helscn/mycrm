#!/usr/bin/env python3
# -*- encoding:utf-8 -*-

import csv
import html
import imaplib
import smtplib
import pymysql
import json
from os.path import join,realpath,split,basename
from email import message_from_bytes
from email.header import decode_header, Header
from email.utils import parseaddr
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.image import MIMEImage
from datetime import datetime, timezone, timedelta
import re
import time
import logging
import sys


# 默认的数据库配置参数
config={
    "db": {
        "port": 3306,
        "charset": "utf8",
        "host": "localhost",
        "user": "root",
        "db": "my_crm",
        "passwd": "root"
    },
    "mailbox":{
        "admin": "AdminName",
        "address": "admin@mailbox.com",
        "outbox":['"Sent Messages"', '"Sent"'],
        "exclude":['"Drafts"','"Templates"']
    }
}


# Json配置文件路径，默认读取脚本程序所在路径中的 config.json 文件,此配置会覆盖config字典中的默认设置
json_file=join(split(realpath(__file__))[0],'config.json')

# Logger的配置设置, log_file 为logger的输出文件路径
log_file=join(split(realpath(__file__))[0],'fetch_mail.log')
logger = logging.getLogger('FetchMail')
logging.basicConfig(
    handlers=[logging.FileHandler(encoding='utf-8', mode='a', filename=log_file)],
    format='[%(asctime)s] %(levelname)s: %(message)s',
    datefmt='%Y/%m/%d %H:%M:%S',
    level=logging.INFO
)
logger.debug('<程序开始运行>')

# 读取数据库的json配置文件，并更新至默认配置的 config 字典中
try:
    logger.debug('正在读取json配置文件：{0}'.format(json_file))
    with open(json_file,mode='r',encoding='utf-8') as f:
        config.update(json.load(f))
except Exception as e:
    logger.error('未找到json配置文件:{0}'.format(json_file))

# 将数据库配置写入json文件
try:    
    logger.debug('正在将配置信息写入json文件：{0}'.format(json_file))
    with open(json_file,mode='w',encoding='utf-8') as f:
        f.write(json.dumps(config,indent=4))
except Exception as e:
    logger.error('将配置参数写入json配置文件时出现错误:{0}'.format(json_file))


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

class IMAP_Client():
    def __init__(self, host="", port=953, user='', password='', ssl=True, tzinfo=8):
        self.host = host
        self.port = port
        self.ssl = ssl
        self.user = user
        self.password = password
        self.timezone=tzinfo if type(tzinfo) is timezone else timezone(timedelta(hours=tzinfo))
        self.__folder = 'INBOX'

    def search(self, pattern='ALL', folder=None):
        try:
            conn = self.connect()
        except Exception as e:
            logger.error('连接邮件服务器出现错误，正在重试...')
            conn.logout()
            conn = self.connect()

        if folder:
            self.__folder = folder
        if self.__folder:
            conn.select(mailbox=self.__folder)
        typ, data = conn.search(None, pattern)
        conn.logout()
        return data[0].split()

    def del_emails(self, uids):
        try:
            conn = self.connect()
        except Exception as e:
            logger.error('连接邮件服务器出现错误，正在重试...')
            conn.logout()
            conn = self.connect()

        if self.__folder:
            conn.select(mailbox=self.__folder)
        if type(uids) not in (list, tuple):
            conn.store(uids, '+FLAGS', '\\Deleted')
        else:
            for uid in uids:
                try:
                    conn.store(uid, '+FLAGS', '\\Deleted')
                except Exception as e:
                    conn.logout()
                    conn = self.connect()
                    conn.store(uid, '+FLAGS', '\\Deleted')
        conn.expunge()
        conn.close()
        conn.logout()

    def select_folder(self, folder=None):
        if folder:
            self.__folder = folder

    def get_emails(self, uids, parts='(RFC822)'):
        try:
            conn = self.connect()
        except Exception as e:
            logger.error('连接邮件服务器出现错误，正在重试...')
            conn.logout()
            conn = self.connect()
            
        if type(uids) not in (list, tuple, set):
            try:
                typ, data = conn.fetch(uids, parts)
                data = data[0][1]
            except Exception as e:
                logger.error(
                    '获取 {folder} 文件夹中编号为 {uid} 的邮件出现错误！尝试重新连接！'.format(folder=self.__folder or '默认', uid=str(uids)))
                conn.logout()
                conn = self.connect()
                typ, data = conn.fetch(uids, parts)
                data = data[0][1]
            conn.logout()
            return Email(data, attrs={'uid': uids})
        for uid in uids:
            try:
                typ, data = conn.fetch(uid, parts)
                data = data[0][1]
            except Exception as e:
                logger.error(
                    '获取 {folder} 文件夹中编号为 {uid} 的邮件出现错误！尝试重新连接！'.format(folder=self.__folder or '默认', uid=str(uid)))
                conn.logout()
                conn = self.connect()
                typ, data = conn.fetch(uid, parts)
                data = data[0][1]
            yield Email(data, attrs={'uid': uid})
        conn.logout()

    def get_recent_emails(self,days=30,parts='(RFC822)'):
        try:
            conn = self.connect()
            typ, data = conn.search(None, 'ALL')
        except Exception as e:
            logger.error('连接邮件服务器出现错误，正在重试...')
            conn.logout()
            conn = self.connect()
            typ, data = conn.search(None, 'ALL')
            
        uids=data[0].split()
        uids.reverse()
        now=datetime.now().replace(tzinfo=self.timezone)
        for uid in uids:
            try:
                typ, data = conn.fetch(uid, parts)
                data = data[0][1]
            except Exception as e:
                logger.error(
                    '获取 {folder} 文件夹中编号为 {uid} 的邮件出现错误！尝试重新连接！'.format(folder=self.__folder or '默认', uid=str(uid))
                )
                conn.logout()
                conn = self.connect()
                typ, data = conn.fetch(uid, parts)
                data = data[0][1]
            email=Email(data, attrs={'uid': uid})
            senddate=email.get_date(self.timezone)
            logger.debug('已经获取id为 %s 的邮件，发件日期为 %s',uid,senddate)
            if ((now-senddate).days>days):
                break
            else:
                yield email
        conn.logout()

    def list_folders(self, directory='""', pattern='*'):
        conn = self.connect()
        folders = conn.list(directory=directory, pattern=pattern)
        conn.logout()
        return folders

    def connect(self):
        Max_Retry_Count = 10
        Retry_Delay = 10
        retry_count = 0
        while True:
            if self.ssl:
                conn = imaplib.IMAP4_SSL(host=self.host, port=self.port)
            else:
                conn = imaplib.IMAP4(host=self.host, port=self.port)
            try:
                conn.login(user=self.user, password=self.password)
                conn.select(mailbox=self.__folder)
            except Exception as e:
                conn.logout()
                retry_count += 1
                if retry_count > Max_Retry_Count:
                    raise e
                time.sleep(Retry_Delay)
                logger.error('连接IMAP服务器失败，正在尝试第 %s 次重新连接...',retry_count)
                continue
            break
        return conn

class SMTP_Client():
    def __init__(self, host='', port=465, user='', password='', ssl=True):
        self.host = host
        self.user = user
        self.password = password
        self.port = port
        self.ssl = ssl
        if self.ssl:
            self.conn = smtplib.SMTP_SSL(self.host, self.port)
        else:
            self.conn = smtplib.SMTP(self.host, self.port)
        
    def __enter__(self):
        self.connect()
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.conn.quit()

    def sendmail(self, sender, recipient, email):
        self.conn.sendmail(sender, recipient, email.as_string())

    def connect(self):
        self.conn.login(self.user, self.password)
        return self.conn


class Email():
    def __init__(self, data=None, headers={}, attrs={}):
        if not data:
            self.__mail = MIMEMultipart('mixed')
        else:
            self.__mail = message_from_bytes(data)

        for key, value in headers.items():
            self.__mail[key] = Header(value, 'utf-8')
        for key, value in attrs.items():
            self.__dict__[key] = value

    def __getitem__(self, key):
        return self.__mail[key]

    def __setitem__(self, key, value):
        self.__mail[key] = Header(value, 'utf-8')

    def get(self, key):
        return self.__mail.get(key)

    def parse_header(self, header_bytes):
        headers = decode_header(header_bytes)
        data = ''
        for header in headers:
            if type(header[0]) is bytes:
                code = header[1]
                if (not code) or ('unknown' in code):
                    code = 'utf-8'
                data += header[0].decode(encoding=code, errors='ignore')
            else:
                data += header[0]
        return data

    def get_subject(self):
        return self.parse_header(self.__mail['subject'])

    def parse_address(self, header_bytes):
        addrs=[]
        if header_bytes:
            for addr in header_bytes.split(','):
                name, mailbox = parseaddr(addr)
                name=self.parse_header(name)
                mailbox=self.parse_header(mailbox)
                if re.search(r'[a-z_0-9.-]{1,64}@(?:[a-z0-9-]{1,200}.){1,5}[a-z]{1,6}',mailbox,flags=re.M | re.S | re.I):
                    addrs.append((
                        name,
                        re.findall(r'[a-z_0-9.-]{1,64}@(?:[a-z0-9-]{1,200}.){1,5}[a-z]{1,6}',mailbox,flags=re.M | re.S | re.I)[0]
                    ))
                else:
                    # 当mailbox地址不合法时，将mailbox合并至name中，mailbox返回''
                    addrs.append(((name+' '+mailbox).strip(),''))
        return addrs

    def get_address(self, column='from'):
        # 获取邮件中的指定地址字段
        data = self.__mail.get(column)
        return self.parse_address(data)
    
    def get_sender(self):
        # 获取邮件中的发件人
        return self.get_address('from')
    
    def get_recipients(self):
        # 获取邮件中的收件人
        addrs=[]
        addrs+=self.get_address('to')
        addrs+=self.get_address('cc')
        addrs+=self.get_address('bcc')
        return addrs

    def get_all_addresses(self):
        # 获取邮件中所有的收、发件地址
        addrs=[]
        addrs+=self.get_address('from')
        addrs+=self.get_address('to')
        addrs+=self.get_address('cc')
        addrs+=self.get_address('bcc')
        return addrs

    def parse_date(self, date_str=None):
        # 解析邮件发件时间
        s=date_str.split()
        if re.search(r'Mon|Tue|Wed|Thu|Fri|Sat|Sun',s[0]):
            s=s[1:]
        try:
            date = datetime.strptime(' '.join(s[:5]), '%d %b %Y %H:%M:%S %z')
        except Exception as e:
            logger.error('解析邮件时间出现错误：[%s] ，尝试将时区设为+0000重新解析',' '.join(s))
            s[4]='+0000'
            date = datetime.strptime(' '.join(s[:5]), '%d %b %Y %H:%M:%S %z')
        return date

    def get_date(self,tzinfo=8):
        # 获取邮件中的发件时间，并转换为tzinfo指定的时区时间
        date=self.parse_date(self.__mail.get('date'))
        return date.astimezone(tzinfo if type(tzinfo) is timezone else timezone(timedelta(hours=tzinfo))) if tzinfo else date

    def __guess_charset(self, msg):
        charset = msg.get_charset()
        if charset is None:
            content_type = msg.get('Content-Type', '')
            result=re.findall(r'(charset\s*=\s*)([a-z0-9\-]+)',content_type,re.I | re.M | re.S)
            if result:
                charset = result[0][1]
        return charset

    def get_html_message(self, msg=None):
        html_message = ''
        if not msg:
            msg = self.__mail

        for part in msg.walk():
            if not part.is_multipart():
                content_type = part.get_content_type()
                if content_type == 'text/html':
                    content = part.get_payload(decode=True)
                    charset = self.__guess_charset(part)
                    content = content.decode(charset or 'utf-8',errors='ignore')
                    html_message += content
        return html_message

    def get_text_message(self, msg=None):
        text = ''
        if not msg:
            msg = self.__mail

        for part in msg.walk():
            if not part.is_multipart():
                content_type = part.get_content_type()
                if content_type == 'text/plain':
                    content = part.get_payload(decode=True)
                    charset = self.__guess_charset(part)
                    try:
                        content = content.decode(charset or 'utf8',errors='ignore')
                    except Exception as e:
                        print(charset,':',repr(content))
                    text += content
        return text

    def attach_html(self, html_message):
        self.__mail.attach(MIMEText(html_message, 'html', 'utf-8'))

    def attach_text(self, text):
        self.__mail.attach(MIMEText(text, 'plain', 'utf-8'))

    def attach_file(self, file_path):
        file_name = basename(file_path)
        file = MIMEText(open(file_path, 'rb').read(), 'base64', 'utf-8')
        file["Content-Type"] = 'application/octet-stream'
        file["Content-Disposition"] = 'attachment; filename="' + file_name + '"'
        self.__mail.attach(file)

    def attach_image(self, file_path, cid):
        image = MIMEImage(open(file_path, 'rb').read())
        image.add_header('Content-ID', '<' + cid + '>')
        self.__mail.attach(image)

    def get_mail(self):
        return self.__mail

    def as_string(self):
        return self.__mail.as_string()

def stat_send_date(email, results={}):
    # 分析邮件的发送时间，保存在格式为{收件人:天数}的results字典中，所有邮件地址均转换为小写
    date = email.get_date()
    addrs = email.get_recipients()
    for name,addr in addrs:
        addr = addr.lower()
        if addr in results:
            if date > results[addr]:
                results[addr] = date
        else:
            results[addr] = date
    return results

def get_parameter(cursor,parameter,default_value=''):
    sql="SELECT value FROM config WHERE parameter=%s"
    if cursor.execute(sql,(parameter,))>0:
        value=cursor.fetchone()['value']
    else:
        sql="INSERT INTO config(parameter,value) VALUES (%s,%s)"
        cursor.execute(sql,(parameter,default_value))
        value=default_value
    return value

def is_new_message(cursor,email):
    name,addr=email.get_sender()[0]
    sender=name if name else addr
    date=email.get_date().strftime('%Y-%m-%d %H:%M:%S')
    sql=r'SELECT sender FROM messages WHERE sender=%s and date=%s'
    return bool(cursor.execute(sql,(sender,date))==0)

def save_message(cursor,email,msg_type):
    name,addr=email.get_sender()[0]
    sender=name if name else addr
    recipients=email.get_recipients()
    addrs=[v[1] for v in recipients if v[1]]
    if addr:
        addrs.insert(0,addr)
    date=email.get_date().strftime('%Y-%m-%d %H:%M:%S')
    subject=email.get_subject()
    content=email.get_html_message().strip()
    content=re.sub(r'<!doc[^>]*>|</?html[^>]*>|</?body[^>]*>|<head[^>]*>(.*?)</head[^>]*>|<script[^>]*>(.*?)</script[^>]*>','',content,flags=re.M | re.S | re.I)
    if not content:
        content=html.escape(email.get_text_message().strip())
        content=re.sub(r'(\r\n)|\r|\n',r'<br>',content,flags=re.M | re.S | re.I)
    if subject=='来自dtn-tech.com的退信':
        # 如果邮件为QQ企业邮退信邮件，将邮件正文中的退信地址加至receivers中
        logger.info('找到邮件服务器在 %s 退回的邮件，退信地址: %s',date,reject_addrs[0])
        reject_addrs = re.findall(r'[a-z_0-9.-]{1,64}@(?:[a-z0-9-]{1,200}.){1,5}[a-z]{1,6}', content,flags=re.M | re.S | re.I)
        addrs.append(reject_addrs[0])
        msg_type='system'
    elif name==config['mailbox']['admin']:
        # 如果发件人为管理员，则将消息类型改为system
        logger.info('找到管理员 %s 在 %s 发送的公告邮件，标题为: %s',addr,date,subject)
        msg_type='system'
    else:
        logger.info('正在将 %s 在 %s 发送的邮件 %s 写入数据库，邮件主题为：%s',sender,date,int(email.uid),subject)
    sql=r'INSERT IGNORE INTO messages(sender,date,type,subject,content) VALUES (%s,%s,%s,%s,%s)'
    if cursor.execute(sql,(sender,date,msg_type,subject,content))>0:
        logger.debug('向数据库写入邮件成功，正在将所有邮件地址保存在数据库中...')
        sql=r'INSERT IGNORE INTO msg_addrs(msg_id,address) VALUES (%s,%s)'
        msg_id=int(cursor.lastrowid)
        for addr in set(addrs):
            logger.debug('正在将id为 %s 的邮件地址<%s>写入数据库...',msg_id,addr.strip())
            cursor.execute(sql,(msg_id,addr.strip()))

if __name__ == '__main__':
    try:
        # 从数据库中读取参数设置设置
        logger.debug('正在读取数据库中的参数设置...')
        with DataBase(**config['db']) as cursor:
            monitor_days=int(get_parameter(cursor,'monitor_mail_days',10))
            logger.debug('设置为读取 %s 天内的邮件消息。',monitor_days)
            mail_reserved_days=int(get_parameter(cursor,'mail_reserved_days',365))
            imap_config={
                "host": get_parameter(cursor,'mail_host','imap.exmail.qq.com'),
                "port": get_parameter(cursor,'mail_port',993),
                "user": get_parameter(cursor,'mail_username','username'),
                "password": get_parameter(cursor,'mail_password','password')
            }
            logger.debug('正在准备删除 %s 天前的邮件消息...',mail_reserved_days)
            sql=r'DELETE FROM messages WHERE datediff(CURRENT_TIMESTAMP,date)>%s'
            deleted_rows=cursor.execute(sql,(mail_reserved_days,))
            if deleted_rows:
                logger.info('已删除 %s 封距今超过 %s 天的过期邮件...',deleted_rows,mail_reserved_days)
        
        # 读取发件箱中的最近邮件
        send_info={}    # 保存发送邮件的收件人地址信息及发送时间
        admin_addr=config['mailbox']['address'].lower().strip()
        imap = IMAP_Client(**imap_config)
        folders=imap.list_folders()[1]
        folders=[v.decode('utf-8').split(' "/" ') for v in folders]
        for isChild,folder in folders:
            if isChild != '(\\HasNoChildren)'  or folder in config['mailbox']['exclude']:
                continue
            logger.debug('正在读取邮箱文件夹 %s 中的邮件消息...',folder)
            imap.select_folder(folder)
            with DataBase(**config['db']) as cursor:
                uids=[]
                for email in imap.get_recent_emails(monitor_days,'(BODY.PEEK[HEADER])'):
                    # 如果发件人是邮箱地址则为发送的邮件，否则为收取的邮件
                    addr=email.get_sender()[0][1]
                    if addr.lower().strip()==admin_addr:
                        send_info=stat_send_date(email,send_info)
                    if is_new_message(cursor,email):
                        # 将新邮件的uid保存到uids列表中
                        uids.append(email.uid)
                if uids:
                    logger.info('在 %s 文件夹中找到 %s 封新邮件消息，正在保存邮件内容...',folder,len(uids))
                    for email in imap.get_emails(uids,'(RFC822)'):
                        # 保存uids中的新邮件至数据库中
                        addr=email.get_sender()[0][1]
                        if addr.lower().strip()==admin_addr:
                            msg_type='sendto'
                        else:
                            msg_type='receive'
                        save_message(cursor,email,msg_type)

        # 将发件箱中最近邮件的最新发送时间更新到数据库中
        values=[(send_info[addr],addr,send_info[addr]) for addr in send_info]
        sql=r'UPDATE customers SET last_contact_date=%s WHERE email=%s AND (last_contact_date IS NULL OR last_contact_date<%s)'
        if values:
            with DataBase(**config['db']) as cursor:
                logger.debug('已更新发件箱中 %s 个客户的最近联系时间。',cursor.executemany(sql,values))

        logger.debug('程序执行完毕！')

    except Exception as e:
        logger.error('程序运行错误: %s',e)
        logger.critical('程序异常停止!',exc_info=True)
        sys.stderr.write(str(e)+'\n')
        raise e
