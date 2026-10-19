FROM alpine:3.24

RUN apk add php85 php85-fpm php85-dom php85-curl php85-openssl nginx ffmpeg --no-cache
RUN sed -i '/user nginx;/d' /etc/nginx/nginx.conf \
    && sed -i 's/^; user = nobody/user = nobody/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/^; group = nobody/group = nobody/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php\/php-fpm85.sock/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/;listen.owner = nobody/listen.owner = nginx/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/;listen.group = nobody/listen.group = nginx/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/;listen.mode/listen.mode/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/;listen.allowed_clients/listen.allowed_clients/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/^pm.max_children = .*/pm.max_children = 16/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' /etc/php85/php-fpm.d/www.conf \
    && sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' /etc/php85/php-fpm.d/www.conf

RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.enable_cli=0' \
    'opcache.memory_consumption=64' \
    'opcache.interned_strings_buffer=8' \
    'opcache.max_accelerated_files=4000' \
    'opcache.validate_timestamps=0' \
    > /etc/php85/conf.d/99-opcache.ini

RUN mkdir -p /var/www/binternet /run/php
COPY . /var/www/binternet
COPY nginx.conf /etc/nginx/http.d/binternet.conf
RUN rm /var/www/binternet/nginx.conf /etc/nginx/http.d/default.conf \
    && chown -R nginx:nginx /var/log/php85/ /run

USER nginx
EXPOSE 8080
ENTRYPOINT ["/bin/sh", "-c" , "/usr/sbin/php-fpm85 -D && /usr/sbin/nginx -c /etc/nginx/nginx.conf -g 'daemon off;'"]
HEALTHCHECK --timeout=5s CMD wget --no-verbose --tries=1 --spider 127.0.0.1:8080 || exit 1
