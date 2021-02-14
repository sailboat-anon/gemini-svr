# gemini-svr.php - geminispace web server
  
## Overview
gemini-svr is a [geminispace](https://gemini.circumlunar.space/) hosting server and can support many hosts on a single instance.  
visit my capsule at gemini://sailboat-anon.space!

## Requirements
```
  - php 8 (cli is fine)
  - git
  - openssl
```

## Install
```
git clone git://github.com/sailboat-anon/gemini-svr.git ; cd gemini-svr;
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -subj '/CN=yourdomain.space'
cp cert.pem certs/yourdomain.space.pem
cat key.pem >> certs/yourdomain.space.pem
```

## Configure
```
nano gemini-svr.php
modify $config array
```

## Use
```
php gemini-svr.php <cert_password> 
                       _       _                             _           
                      (_)     (_)                           | |          
   __ _  ___ _ __ ___  _ _ __  _ ______ _____   ___ __ _ __ | |__  _ __  
  / _` |/ _ \ '_ ` _ \| | '_ \| |______/ __\ \ / / '__| '_ \| '_ \| '_ \ 
 | (_| |  __/ | | | | | | | | | |      \__ \\ V /| |_ | |_) | | | | |_) |
  \__, |\___|_| |_| |_|_|_| |_|_|      |___/ \_/ |_(_)| .__/|_| |_| .__/ 
  __/  | gemini://sailboat-anon.space                 | |         | |    
 |___ /                                               |_|         |_|                     
```
