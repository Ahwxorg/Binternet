FROM alpine:3.20

RUN apk add php83 php83-fpm php83-dom php83-curl php83-json php83-openssl nginx --no-cache
RUN sed -i '/user nginx;/d' /etc/nginx/nginx.conf \
    && sed -i 's/user = nobody/; user = nobody/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/group = nobody/; group = nobody/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php-fpm83.sock/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.owner = nobody/listen.owner = nginx/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.group = nobody/listen.group = nginx/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.mode/listen.mode/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.allowed_clients/listen.allowed_clients/' /etc/php83/php-fpm.d/www.conf

RUN mkdir -p /var/www/binternet
COPY . /var/www/binternet
COPY nginx.conf /etc/nginx/http.d/binternet.conf
RUN rm /var/www/binternet/nginx.conf /etc/nginx/http.d/default.conf \
    && chown -R nginx:nginx /var/log/php83/ /run

USER nginx
EXPOSE 8080
ENTRYPOINT ["/bin/sh", "-c" , "/usr/sbin/php-fpm83 -D && /usr/sbin/nginx -c /etc/nginx/nginx.conf -g 'daemon off;'"]
HEALTHCHECK --timeout=5s CMD wget --no-verbose --tries=1 --spider 127.0.0.1:8080 || exit 1
