FROM debian:latest
LABEL maintainer="ahwx@ahwx.org" 
RUN  apt-get -y update && apt-get -y install php php-curl nginx nginx-common git
RUN  git clone https://github.com/Ahwxorg/pinternet /var/www/html/pinternet
RUN  systemctl enable --now nginx php-fpm
COPY nginx.conf /etc/nginx/sites-available/pinternet
EXPOSE 8009
CMD ["/usr/sbin/nginx", "-g", "daemon off;"]

