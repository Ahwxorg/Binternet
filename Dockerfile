FROM debian:11
LABEL maintainer="ahwx@ahwx.org" 
RUN  apt-get -y update && apt-get -y install php php-fpm php-curl nginx nginx-common git
RUN  git clone https://github.com/Ahwxorg/pinternet /var/www/html/pinternet
COPY nginx.conf /etc/nginx/sites-enabled/pinternet
EXPOSE 8009
CMD service php$(php -v | grep PHP | head -n1 | cut -d " " -f2 | cut -d "." -f1-2)-fpm start && /usr/sbin/nginx -g "daemon off;"
