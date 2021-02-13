FROM php:8.0.2-cli-alpine
RUN sudo apt install git
RUN git pull 

COPY server.php /server.php
COPY var/lib/bind/* /var/lib/bind/
COPY var/log/bind/* /var/log/bind/
COPY etc/bind/zones/* /etc/bind/zones/
RUN ["named", "-g", "-p", "53"]