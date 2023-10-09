FROM nginx:mainline-alpine-slim

RUN apk add php82 php82-fpm php82-dom php82-curl php82-json php82-openssl --no-cache
RUN sed -i 's/user  nginx;/user  nobody;/' /etc/nginx/nginx.conf \
    && sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php-fpm82.sock/' /etc/php82/php-fpm.d/www.conf \
    && sed -i 's/;listen.owner/listen.owner/' /etc/php82/php-fpm.d/www.conf \
    && sed -i 's/;listen.group/listen.group/' /etc/php82/php-fpm.d/www.conf \
    && sed -i 's/;listen.mode/listen.mode/' /etc/php82/php-fpm.d/www.conf \
    && sed -i 's/;listen.allowed_clients/listen.allowed_clients/' /etc/php82/php-fpm.d/www.conf

RUN mkdir -p /var/www/binternet
COPY . /var/www/binternet
COPY nginx.conf /etc/nginx/conf.d/binternet.conf
RUN rm /var/www/binternet/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
ENTRYPOINT ["/bin/sh", "-c" , "/usr/sbin/php-fpm82 -D && /usr/sbin/nginx -c /etc/nginx/nginx.conf -g 'daemon off;'"]
